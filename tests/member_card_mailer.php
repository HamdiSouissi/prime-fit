<?php
declare(strict_types=1);
require __DIR__.'/../app/services/MemberCardMailer.php';
$member=['first_name'=>'Amine','email'=>'amine@example.tn','card_number'=>'PF-123','qr_token'=>str_repeat('a',64)];
$sent=null;
$transport=static function(string $to,string $subject,string $body,string $headers) use (&$sent): bool { $sent=compact('to','subject','body','headers'); return true; };
$mailer=new MemberCardMailer(['enabled'=>true,'from'=>'contact@example.tn'],$transport);
if ($mailer->send($member)!=='accepted' || !$sent || $sent['to']!==$member['email']) throw new RuntimeException('Envoi non accepté');
if (!str_contains($sent['headers'],'multipart/mixed') || !str_contains($sent['body'],'Content-Type: image/png') || str_contains($sent['body'],$member['qr_token'])) throw new RuntimeException('MIME invalide');
if (!preg_match('/Content-Type: image\/png;[^\r]+\r\nContent-Disposition:[^\r]+\r\nContent-Transfer-Encoding: base64\r\n\r\n([A-Za-z0-9+\/=\r\n]+)/',$sent['body'],$matches)) throw new RuntimeException('Pièce jointe absente');
$png=base64_decode($matches[1],true);
if ($png===false || substr($png,0,8)!=="\x89PNG\r\n\x1a\n") throw new RuntimeException('PNG invalide');
if (isset($argv[1])) file_put_contents($argv[1],$png);
if ((new MemberCardMailer(['enabled'=>false,'from'=>'contact@example.tn'],$transport))->send($member)!=='not_configured') throw new RuntimeException('Configuration manquante');
if ((new MemberCardMailer(['enabled'=>true,'from'=>'contact@example.tn'],static fn()=>false))->send($member)!=='failed') throw new RuntimeException('Échec non traité');
$member['email']="malicious@example.tn\r\nBcc: victim@example.tn";
if ($mailer->send($member)!=='missing_email') throw new RuntimeException('Injection d’en-tête acceptée');
echo "Pièce jointe QR et états d’envoi : OK\n";
