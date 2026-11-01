<?php
declare(strict_types=1);
require_once __DIR__.'/GymBrand.php';
/** Envoi après commit uniquement ; l'acceptation par mail() n'est pas une preuve de livraison. */
final class PaymentReceiptMailer {
    private $transport;
    public function __construct(private array $config, ?callable $transport=null) {
        $this->transport=$transport ?? static fn(string $to,string $subject,string $body,string $headers): bool => @mail($to,$subject,$body,$headers);
    }
    public function send(array $receipt): string {
        $payment=$receipt['payment']; $email=$payment['email'] ?? null; $from=$this->config['from'] ?? null;
        if (!is_string($email) || !filter_var($email,FILTER_VALIDATE_EMAIL)) return 'missing_email';
        if (empty($this->config['enabled']) || !is_string($from) || !filter_var($from,FILTER_VALIDATE_EMAIL)) return 'not_configured';
        try {
            $gym=GymBrand::clean($receipt['gym'] ?? null) ?? 'Horizon Sportif de Bir El Hafey';
            $name=trim($payment['first_name'].' '.$payment['last_name']); $date=date('d/m/Y à H:i',strtotime($payment['paid_at']));
            $amount=number_format((float)$payment['amount'],3,',',' ').' DT'; $id=(int)$payment['id'];
            $subject='Confirmation de paiement — '.$gym; if (preg_match('/[^\x20-\x7e]/',$subject)) $subject=mb_encode_mimeheader($subject,'UTF-8','B',"\r\n");
            $text="Bonjour $name,\r\n\r\nNous confirmons l’enregistrement de votre paiement.\r\nReçu n° $id\r\nDate : $date\r\nMontant : $amount\r\nMoyen : {$payment['method']}\r\nVotre reçu est joint au format PDF. Ce document est un justificatif, pas une facture fiscale.\r\n\r\nL’équipe $gym";
            $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); $g=$e($gym); $n=$e($name); $d=$e($date); $a=$e($amount); $m=$e($payment['method']);
            $html=<<<HTML
<!doctype html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Confirmation de paiement</title></head><body style="margin:0;padding:0;background:#0a0a0a;color:#fff;font-family:Arial,Helvetica,sans-serif;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#0a0a0a;"><tr><td align="center" style="padding:32px 14px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#151515;border:1px solid #303030;"><tr><td style="background:#000;padding:24px 28px;border-bottom:4px solid #ffd400;"><span style="font-size:25px;font-weight:900;color:#fff;">{$g}</span><br><span style="font-size:13px;font-weight:bold;letter-spacing:1px;color:#ffd400;">CLUB DE SPORT</span><br><span style="font-size:10px;letter-spacing:2.5px;color:#a3a3a3;">ESPACE MEMBRE · TUNISIE</span></td></tr><tr><td style="padding:32px 28px 12px;"><p style="color:#ffd400;font-size:11px;font-weight:bold;letter-spacing:2px;">VOTRE PAIEMENT</p><h1 style="font-size:30px;color:#fff;">Paiement confirmé.</h1><p style="color:#d1d1d1;line-height:1.6;">Bonjour {$n},<br>Nous confirmons l’enregistrement de votre paiement. Votre reçu PDF est joint à cet e-mail.</p></td></tr><tr><td style="padding:20px 28px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#202020;border:1px solid #444;"><tr><td style="height:5px;background:#ffd400;"></td></tr><tr><td style="padding:24px;color:#fff;line-height:1.8;">Reçu n° {$id}<br>Date : {$d}<br>Moyen : {$m}<br><strong style="color:#ffd400;font-size:22px;">{$a}</strong></td></tr></table></td></tr><tr><td style="padding:12px 28px 30px;color:#bfbfbf;font-size:13px;line-height:1.6;">Ce reçu est un justificatif d’encaissement, pas une facture fiscale. Pour toute question, contactez votre salle.</td></tr><tr><td style="padding:20px 28px;border-top:1px solid #333;color:#8f8f8f;font-size:12px;">À très bientôt,<br><strong style="color:#fff;">L’équipe {$g}</strong></td></tr></table></td></tr></table></body></html>
HTML;
            $boundary='payment_'.bin2hex(random_bytes(16)); $alt='alt_'.bin2hex(random_bytes(16)); $file='recu-paiement-'.$id.'.pdf';
            $headers="From: $from\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"$boundary\"";
            $body="--$boundary\r\nContent-Type: multipart/alternative; boundary=\"$alt\"\r\n\r\n--$alt\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($text))."\r\n--$alt\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($html))."\r\n--$alt--\r\n--$boundary\r\nContent-Type: application/pdf; name=\"$file\"\r\nContent-Disposition: attachment; filename=\"$file\"\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($receipt['pdf']))."\r\n--$boundary--\r\n";
            return ($this->transport)($email,$subject,$body,$headers) ? 'accepted' : 'failed';
        } catch (Throwable $e) { error_log('Envoi de confirmation de paiement impossible.'); return 'failed'; }
    }
}
