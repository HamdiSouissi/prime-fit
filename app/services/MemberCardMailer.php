<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/phpqrcode/qrlib.php';
/** Envoie une carte uniquement via le transport mail() configuré sur le serveur. */
class MemberCardMailer {
    private array $config;
    private $transport;
    public function __construct(array $config, ?callable $transport = null) {
        $this->config = $config;
        $this->transport = $transport ?? static fn(string $to, string $subject, string $body, string $headers): bool => @mail($to, $subject, $body, $headers);
    }
    /** Les résultats décrivent l'acceptation par le serveur, pas la livraison finale. */
    public function send(array $member): string {
        if (empty($member['email']) || !filter_var($member['email'], FILTER_VALIDATE_EMAIL)) return 'missing_email';
        if (empty($member['qr_token']) || !preg_match('/^[0-9a-f]{64}$/D', $member['qr_token'])) return 'missing_qr';
        $from = $this->config['from'] ?? '';
        if (empty($this->config['enabled']) || !is_string($from) || !filter_var($from, FILTER_VALIDATE_EMAIL)) return 'not_configured';
        try {
            $png = self::qrPng('PF1:' . $member['qr_token']);
            $boundary = 'primefit_' . bin2hex(random_bytes(16));
            $subject = 'Votre carte de membre Prime Fit';
            $bodyText = "Bonjour " . $member['first_name'] . ",\r\n\r\nBienvenue chez Prime Fit ! Retrouvez votre code QR personnel en pièce jointe. Présentez-le à l’accueil pour votre pointage.\r\n" .
                'Numéro de carte : ' . ($member['card_number'] ?: 'non renseigné') . "\r\n\r\nCe code est personnel : ne le partagez pas. En cas de perte, contactez votre salle.\r\n\r\nL’équipe Prime Fit";
            $headers = "From: " . $from . "\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"" . $boundary . "\"";
            $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($bodyText)) .
                "\r\n--$boundary\r\nContent-Type: image/png; name=\"carte-prime-fit.png\"\r\nContent-Disposition: attachment; filename=\"carte-prime-fit.png\"\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($png)) . "\r\n--$boundary--\r\n";
            return ($this->transport)($member['email'], $subject, $body, $headers) ? 'accepted' : 'failed';
        } catch (Throwable $ex) {
            // Ne jamais journaliser l'adresse du membre ni le jeton QR.
            error_log('Prime Fit : impossible de préparer ou envoyer la carte QR par e-mail.');
            return 'failed';
        }
    }
    /** PNG monochrome, avec marge de quatre modules, sans fichiers temporaires ni GD. */
    private static function qrPng(string $text): string {
        if (!function_exists('gzcompress')) throw new RuntimeException('Extension zlib absente');
        $matrix = QRcode::text($text, false, QR_ECLEVEL_M);
        if (!$matrix || !is_array($matrix)) throw new RuntimeException('Erreur QR');
        $scale=6; $margin=4; $modules=count($matrix); $width=($modules+2*$margin)*$scale;
        $blank=str_repeat("\xff",$width); $rows='';
        for ($y=-$margin; $y<$modules+$margin; $y++) {
            $line=$y<0 || $y >= $modules ? $blank : str_repeat("\xff",$margin*$scale);
            if ($y>=0 && $y<$modules) {
                for ($x=0; $x<$modules; $x++) $line.=str_repeat($matrix[$y][$x]==='1' ? "\x00" : "\xff",$scale);
                $line.=str_repeat("\xff",$margin*$scale);
            }
            $rows.=str_repeat("\x00".$line,$scale);
        }
        $chunk=static function(string $type,string $data): string { return pack('N',strlen($data)).$type.$data.pack('N',crc32($type.$data)); };
        return "\x89PNG\r\n\x1a\n".$chunk('IHDR',pack('NNCCCCC',$width,$width,8,0,0,0,0)).$chunk('IDAT',gzcompress($rows,9)).$chunk('IEND','');
    }
}
