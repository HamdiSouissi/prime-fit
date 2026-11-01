<?php
declare(strict_types=1);
require __DIR__.'/../app/services/MemberPortalMailer.php';
$sent=[]; $transport=static function($to,$subject,$body,$headers) use (&$sent) { $sent=compact('to','subject','body','headers'); return true; };
$config=['enabled'=>true,'from'=>'club@example.tn','base_url'=>'https://club.example.tn/salle','gym_name'=>'Horizon'];
$invite=['email'=>'membre@example.tn','first_name'=>'Ali','last_name'=>'Test','token'=>str_repeat('a',64)];
$mailer=new MemberPortalMailer($config,$transport);
if ($mailer->send($invite)!=='accepted' || $sent['to']!==$invite['email']) throw new RuntimeException('E-mail non envoyé');
$body=base64_decode($sent['body']); if (!str_contains($body,'https://club.example.tn/salle/member_activate?token='.str_repeat('a',64)) || !str_contains($body,$invite['email']) || str_contains($body,'mot de passe temporaire')) throw new RuntimeException('Contenu incorrect');
foreach (['http://exemple.tn','https://evil.tn/?q=1','https://user:pass@exemple.tn','https://exemple.tn/#x',"https://exemple.tn\r\n",'//exemple.tn'] as $bad) if ((new MemberPortalMailer(array_replace($config,['base_url'=>$bad]),$transport))->configured()) throw new RuntimeException('URL non fiable acceptée');
if (!(new MemberPortalMailer(array_replace($config,['base_url'=>'http://localhost/prime-fit/public']),$transport))->configured()) throw new RuntimeException('URL locale refusée');
if ($mailer->send(array_replace($invite,['token'=>'oops']))!=='failed') throw new RuntimeException('Jeton invalide accepté');
echo "Invitation membre : configuration et transport OK\n";
