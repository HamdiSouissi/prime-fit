<?php
declare(strict_types=1);
require_once __DIR__.'/GymBrand.php';
/** Transport d'invitation ; l'URL provient exclusivement de la configuration fiable. */
final class MemberPortalMailer {
    private $transport;
    public function __construct(private array $config, ?callable $transport=null) {
        $this->transport=$transport ?? static fn(string $to,string $subject,string $body,string $headers): bool => @mail($to,$subject,$body,$headers);
    }
    public function configured(): bool {
        $from=$this->config['from'] ?? null; $base=$this->config['base_url'] ?? null;
        if (empty($this->config['enabled']) || !is_string($from) || !filter_var($from,FILTER_VALIDATE_EMAIL) || !is_string($base) || strlen($base)>500 || preg_match('/[\x00-\x20\x7f]/',$base)) return false;
        $url=parse_url($base);
        if (!$url || !isset($url['scheme'],$url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])) return false;
        if (!in_array($url['scheme'],['https','http'],true) || !filter_var($base,FILTER_VALIDATE_URL)) return false;
        // HTTP n'est admis que pour les essais depuis la même machine.
        if ($url['scheme']==='http' && !in_array(strtolower($url['host']),['localhost','127.0.0.1'],true)) return false;
        return true;
    }
    public function send(array $invite): string {
        if (!$this->configured()) return 'not_configured';
        $email=$invite['email'] ?? ''; $token=$invite['token'] ?? '';
        if (!is_string($email) || !filter_var($email,FILTER_VALIDATE_EMAIL) || !is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/D',$token)) return 'failed';
        $gym=GymBrand::clean($this->config['gym_name'] ?? null) ?? 'Horizon Sportif de Bir El Hafey';
        $name=trim((string)($invite['first_name'] ?? '').' '.(string)($invite['last_name'] ?? ''));
        $link=rtrim($this->config['base_url'],'/').'/member_activate?token='.$token;
        $subject='Activez votre espace membre — '.$gym;
        if (preg_match('/[^\x20-\x7e]/',$subject)) $subject=mb_encode_mimeheader($subject,'UTF-8','B',"\r\n");
        $body="Bonjour $name,\r\n\r\nBienvenue chez $gym ! Votre identifiant est votre adresse e-mail : $email.\r\n\r\nPour choisir votre mot de passe, ouvrez ce lien personnel, valable 24 heures :\r\n$link\r\n\r\nNe partagez pas ce lien. Si vous n'êtes pas à l'origine de cette inscription, contactez la salle.\r\n\r\nL'équipe $gym";
        $headers='From: '.$this->config['from']."\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64";
        try { return ($this->transport)($email,$subject,chunk_split(base64_encode($body)),$headers) ? 'accepted' : 'failed'; }
        catch (Throwable $e) { error_log('Envoi de l’invitation membre impossible.'); return 'failed'; }
    }
}
