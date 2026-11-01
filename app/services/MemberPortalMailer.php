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
    public function send(array $invite): string { return $this->sendAction($invite,'activation'); }
    public function sendReset(array $invite): string { return $this->sendAction($invite,'reset'); }
    public function sendQrLogin(array $invite): string { return $this->sendAction($invite,'qr'); }
    private function sendAction(array $invite,string $kind): string {
        if (!$this->configured()) return 'not_configured';
        $email=$invite['email'] ?? ''; $token=$invite['token'] ?? '';
        if (!is_string($email) || !filter_var($email,FILTER_VALIDATE_EMAIL) || !is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/D',$token)) return 'failed';
        $gym=GymBrand::clean($this->config['gym_name'] ?? null) ?? 'Horizon Sportif de Bir El Hafey';
        $name=trim((string)($invite['first_name'] ?? '').' '.(string)($invite['last_name'] ?? ''));
        $reset=$kind==='reset'; $qr=$kind==='qr';
        $link=rtrim($this->config['base_url'],'/').($qr ? '/member_qr_confirm?token=' : ($reset ? '/member_reset?token=' : '/member_activate?token=')).$token;
        $subject=($qr ? 'Confirmez votre connexion — ' : ($reset ? 'Réinitialisez votre mot de passe — ' : 'Activez votre espace membre — ')).$gym;
        if (preg_match('/[^\x20-\x7e]/',$subject)) $subject=mb_encode_mimeheader($subject,'UTF-8','B',"\r\n");
        $title=$qr ? 'Confirmez votre connexion.' : ($reset ? 'Réinitialisez votre mot de passe.' : 'Votre espace membre est prêt.');
        $intro=$qr ? 'Une connexion a été demandée avec votre carte QR. Ne confirmez que si vous êtes à l’origine de cette demande.' : ($reset ? 'Vous avez demandé à choisir un nouveau mot de passe.' : 'Bienvenue ! Votre identifiant est votre adresse e-mail.');
        $button=$qr ? 'Confirmer ma connexion' : ($reset ? 'Choisir un nouveau mot de passe' : 'Activer mon espace');
        $validity=$qr ? '15 minutes' : ($reset ? '1 heure' : '24 heures');
        $text="Bonjour $name,\r\n\r\n$intro\r\nIdentifiant : $email\r\n\r\n$button :\r\n$link\r\n\r\nCe lien personnel est valable $validity et utilisable une seule fois. Ne le partagez pas. Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail ou contactez votre salle.\r\n\r\nL’équipe $gym";
        $escape=static fn(string $value): string => htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $n=$escape($name); $g=$escape($gym); $u=$escape($link); $em=$escape($email); $t=$escape($title); $introHtml=$escape($intro); $b=$escape($button); $v=$escape($validity);
        $html=<<<HTML
<!doctype html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{$t}</title></head>
<body style="margin:0;padding:0;background:#0a0a0a;color:#ffffff;font-family:Arial,Helvetica,sans-serif;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#0a0a0a;"><tr><td align="center" style="padding:32px 14px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#151515;border:1px solid #303030;">
<tr><td style="background:#000000;padding:24px 28px;border-bottom:4px solid #ffd400;"><span style="font-size:25px;font-weight:900;color:#ffffff;">{$g}</span><br><span style="font-size:13px;font-weight:bold;letter-spacing:1px;color:#ffd400;">CLUB DE SPORT</span><br><span style="font-size:10px;letter-spacing:2.5px;color:#a3a3a3;">ESPACE MEMBRE · TUNISIE</span></td></tr>
<tr><td style="padding:32px 28px 12px;"><p style="margin:0 0 12px;color:#ffd400;font-size:11px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;">VOTRE ESPACE PERSONNEL</p><h1 style="margin:0 0 16px;font-size:30px;line-height:1.15;text-transform:uppercase;color:#ffffff;">{$t}</h1><p style="margin:0;color:#d1d1d1;font-size:15px;line-height:1.6;">Bonjour {$n},<br>{$introHtml}</p></td></tr>
<tr><td style="padding:20px 28px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#202020;border:1px solid #444444;"><tr><td style="height:5px;background:#ffd400;font-size:1px;">&nbsp;</td></tr><tr><td style="padding:24px;color:#ffffff;"><p style="margin:0 0 8px;color:#a3a3a3;font-size:10px;font-weight:bold;letter-spacing:1px;">ADRESSE DE CONNEXION</p><p style="margin:0 0 22px;word-break:break-all;">{$em}</p><a href="{$u}" style="display:inline-block;background:#ffd400;color:#0a0a0a;padding:14px 20px;text-decoration:none;font-weight:bold;">{$b} →</a><p style="margin:20px 0 0;color:#bfbfbf;font-size:13px;line-height:1.5;">Lien personnel valable {$v}, utilisable une seule fois. Ne le partagez pas.</p></td></tr></table></td></tr>
<tr><td style="padding:12px 28px 30px;color:#bfbfbf;font-size:13px;line-height:1.6;">Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail ou contactez votre salle.</td></tr><tr><td style="padding:20px 28px;border-top:1px solid #333333;color:#8f8f8f;font-size:12px;">À très bientôt,<br><strong style="color:#ffffff;">L’équipe {$g}</strong></td></tr></table></td></tr></table></body></html>
HTML;
        $boundary='portal_'.bin2hex(random_bytes(16));
        $headers='From: '.$this->config['from']."\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$boundary\"";
        $body="--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($text))."\r\n--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($html))."\r\n--$boundary--\r\n";
        try { return ($this->transport)($email,$subject,$body,$headers) ? 'accepted' : 'failed'; }
        catch (Throwable $e) { error_log('Envoi de l’e-mail membre impossible.'); return 'failed'; }
    }
}
