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
            $alternative = 'alt_' . bin2hex(random_bytes(16));
            $related = 'related_' . bin2hex(random_bytes(16));
            $cid = 'qr_' . bin2hex(random_bytes(12)) . '@primefit.local';
            $subject = 'Votre carte de membre Prime Fit';
            $name = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
            $card = $member['card_number'] ?: 'Non renseigné';
            $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string)($member['created_at'] ?? ''));
            $dateLabel = $date ? $date->format('d/m/Y') : 'Non renseignée';
            $bodyText = "Bonjour " . $name . ",\r\n\r\nBienvenue chez Prime Fit ! Voici votre carte de membre.\r\n" .
                'Nom : ' . $name . "\r\nNuméro de carte : " . $card . "\r\nDate d’enregistrement : " . $dateLabel .
                "\r\n\r\nPrésentez le QR joint à l’accueil pour votre pointage. Ce code est personnel : ne le partagez pas. En cas de perte, contactez votre salle.\r\n\r\nL’équipe Prime Fit";
            $html = self::renderCardEmail($name, $card, $dateLabel, $cid);
            $headers = "From: " . $from . "\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"" . $boundary . "\"";
            // HTML avec image CID intégrée, version texte et pièce jointe indépendante.
            $body = "--$boundary\r\nContent-Type: multipart/alternative; boundary=\"$alternative\"\r\n\r\n" .
                "--$alternative\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($bodyText)) .
                "\r\n--$alternative\r\nContent-Type: multipart/related; boundary=\"$related\"\r\n\r\n" .
                "--$related\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) .
                "\r\n--$related\r\nContent-Type: image/png\r\nContent-ID: <$cid>\r\nContent-Disposition: inline; filename=\"qr-inline.png\"\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($png)) .
                "\r\n--$related--\r\n--$alternative--\r\n" .
                "--$boundary\r\nContent-Type: image/png; name=\"carte-prime-fit.png\"\r\nContent-Disposition: attachment; filename=\"carte-prime-fit.png\"\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($png)) . "\r\n--$boundary--\r\n";
            return ($this->transport)($member['email'], $subject, $body, $headers) ? 'accepted' : 'failed';
        } catch (Throwable $ex) {
            // Ne jamais journaliser l'adresse du membre ni le jeton QR.
            error_log('Prime Fit : impossible de préparer ou envoyer la carte QR par e-mail.');
            return 'failed';
        }
    }
    /** Mise en page HTML compatible avec les clients e-mail courants. */
    private static function renderCardEmail(string $name, string $card, string $date, string $cid): string {
        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $name=$escape($name); $card=$escape($card); $date=$escape($date); $cid=$escape($cid);
        return <<<HTML
<!doctype html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Votre carte Prime Fit</title></head>
<body style="margin:0;padding:0;background:#0a0a0a;color:#ffffff;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#0a0a0a;"><tr><td align="center" style="padding:32px 14px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;background:#151515;border:1px solid #303030;">
<tr><td style="background:#000000;padding:24px 28px;border-bottom:4px solid #ffd400;">
<span style="font-family:Arial,sans-serif;font-size:25px;font-weight:900;letter-spacing:1px;color:#ffffff;">PRIME<span style="color:#ffd400;">FIT</span></span><br>
<span style="font-size:10px;letter-spacing:2.5px;color:#a3a3a3;">MANAGEMENT · TUNISIE</span>
</td></tr>
<tr><td style="padding:32px 28px 12px;">
<p style="margin:0 0 12px;color:#ffd400;font-size:11px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;">BIENVENUE DANS L’ÉQUIPE</p>
<h1 style="margin:0 0 16px;font-size:30px;line-height:1.15;text-transform:uppercase;color:#ffffff;">Votre carte est prête.</h1>
<p style="margin:0;color:#d1d1d1;font-size:15px;line-height:1.6;">Bonjour {$name},<br>Nous sommes ravis de vous accueillir chez Prime Fit. Votre carte personnelle vous accompagne à chaque séance.</p>
</td></tr>
<tr><td align="center" style="padding:20px 28px 12px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:450px;background:#202020;border:1px solid #444444;">
<tr><td style="height:5px;line-height:5px;background:#ffd400;font-size:1px;">&nbsp;</td></tr>
<tr><td align="center" style="padding:24px 18px 26px;">
<p style="margin:0 0 7px;color:#ffd400;font-size:10px;font-weight:bold;letter-spacing:2px;">CARTE D’ADHÉRENT</p>
<p style="margin:0 0 18px;color:#ffffff;font-size:22px;font-weight:bold;line-height:1.2;">{$name}</p>
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="background:#ffffff;"><tr><td style="padding:16px;"><img src="cid:{$cid}" width="210" height="210" alt="QR code personnel de votre carte Prime Fit" style="display:block;width:210px;height:210px;max-width:100%;border:0;"></td></tr></table>
<p style="margin:20px 0 5px;color:#a3a3a3;font-size:10px;font-weight:bold;letter-spacing:1.3px;">NUMÉRO DE CARTE</p>
<p style="margin:0;color:#ffffff;font-size:16px;font-weight:bold;word-break:break-all;">{$card}</p>
<p style="margin:16px 0 5px;color:#a3a3a3;font-size:10px;font-weight:bold;letter-spacing:1.3px;">DATE D’ENREGISTREMENT</p>
<p style="margin:0;color:#ffffff;font-size:15px;font-weight:bold;">{$date}</p>
</td></tr></table></td></tr>
<tr><td style="padding:16px 28px 30px;"><p style="margin:0 0 12px;color:#ffffff;font-size:14px;font-weight:bold;">COMMENT UTILISER VOTRE CARTE ?</p>
<p style="margin:0;color:#bfbfbf;font-size:14px;line-height:1.6;">Présentez votre QR code à l’accueil depuis votre téléphone. Vous pouvez aussi retrouver le QR en pièce jointe si l’image ne s’affiche pas.</p>
<p style="margin:18px 0 0;padding:14px;border-left:3px solid #ffd400;background:#252525;color:#cccccc;font-size:13px;line-height:1.5;">Ce code est personnel. Ne le partagez pas et contactez votre salle en cas de perte.</p></td></tr>
<tr><td style="padding:20px 28px;border-top:1px solid #333333;color:#8f8f8f;font-size:12px;line-height:1.5;">À très bientôt,<br><strong style="color:#ffffff;">L’équipe PRIME FIT</strong><br><span style="font-size:10px;letter-spacing:1px;">ENSEMBLE, PLUS LOIN.</span></td></tr>
</table></td></tr></table></body></html>
HTML;
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
