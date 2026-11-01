<?php
declare(strict_types=1);
require __DIR__.'/../app/services/ContactFormService.php';
require __DIR__.'/../app/services/ContactRateLimiter.php';
$sent=[];
$service=new ContactFormService(['enabled'=>true,'from'=>'club@example.tn'],static function($to,$subject,$body,$headers) use (&$sent): bool { $sent=compact('to','subject','body','headers'); return true; });
$input=['name'=>'Amine <test>','email'=>'amine@example.tn','phone'=>'+216 12 345 678','message'=>"Bonjour, je souhaite des informations.\nMerci !"];
[$fields,$error]=$service->validate($input);
if ($error!=='' || !$service->configured('club@example.tn') || !$service->send('club@example.tn',$fields)) throw new RuntimeException('Envoi valide refusé');
if ($sent['to']!=='club@example.tn' || !str_contains($sent['headers'],'Reply-To: amine@example.tn') || !str_contains(base64_decode($sent['body']),'Amine <test>')) throw new RuntimeException('Contenu du message incorrect');
foreach ([['email'=>"a@example.tn\r\nBcc: bad@example.tn"],['name'=>"X\r\nBcc: bad"],['message'=>'court'],['phone'=>'123<script>'],['name'=>['malformé']]] as $invalid) {
    [, $error]=$service->validate(array_replace($input,$invalid)); if ($error==='') throw new RuntimeException('Entrée invalide acceptée');
}
if ((new ContactFormService(['enabled'=>false,'from'=>'club@example.tn']))->configured('club@example.tn') || $service->configured("club@example.tn\r\nBcc:other@example.tn")) throw new RuntimeException('Configuration mail invalide acceptée');
$failure=new ContactFormService(['enabled'=>true,'from'=>'club@example.tn'],static fn()=>false);
if ($failure->send('club@example.tn',$fields)) throw new RuntimeException('Échec du transport ignoré');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE auth_rate_limits (bucket_key TEXT PRIMARY KEY,window_started INTEGER NOT NULL,attempts INTEGER NOT NULL)');
$limiter=new ContactRateLimiter($db);
for($i=0;$i<5;$i++) if (!$limiter->reserve('192.0.2.1',1000)) throw new RuntimeException('Limite prématurée');
if ($limiter->reserve('192.0.2.1',1000) || !$limiter->reserve('192.0.2.2',1000) || !$limiter->reserve('192.0.2.1',1900)) throw new RuntimeException('Limite IP ou fenêtre incorrecte');
echo "Contact : validation, transport et limite de fréquence OK\n";
