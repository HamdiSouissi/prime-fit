<?php
declare(strict_types=1);
require __DIR__.'/../app/services/MemberPortalPayments.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE members(id INTEGER,first_name TEXT,last_name TEXT,card_number TEXT); CREATE TABLE plans(id INTEGER,name TEXT); CREATE TABLE subscriptions(id INTEGER,member_id INTEGER,plan_id INTEGER); CREATE TABLE payments(id INTEGER PRIMARY KEY,member_id INTEGER,subscription_id INTEGER,amount TEXT,method TEXT,paid_at TEXT); CREATE TABLE payment_receipts(payment_id INTEGER,pdf BLOB); INSERT INTO members VALUES(1,'Ali','Un','C1'),(2,'Sara','Deux','C2'); INSERT INTO plans VALUES(1,'Mensuel'); INSERT INTO subscriptions VALUES(1,1,1),(2,2,1); INSERT INTO payments VALUES(50,2,2,'99.000','D17','2030-02-01 12:00:00'); INSERT INTO payment_receipts VALUES(50,'%PDF-secret-deux');");
for ($i=1;$i<=23;$i++) $db->prepare('INSERT INTO payments VALUES(?,1,1,?,?,?)')->execute([$i,'20.500','espèces',sprintf('2030-01-%02d 12:00:00',$i)]);
$db->exec("INSERT INTO payment_receipts VALUES(23,'%PDF-membre-un');");
$svc=new MemberPortalPayments($db); $first=$svc->listing(1,1); $last=$svc->listing(1,99);
if ($first['total']!==23 || $first['pages']!==3 || count($first['rows'])!==10 || $first['rows'][0]['id']!==23 || $last['page']!==3 || count($last['rows'])!==3 || $last['rows'][2]['id']!==1) throw new RuntimeException('Historique incomplet ou pagination incorrecte');
if ($svc->listing(2,1)['total']!==1 || $svc->pdf(1,50,'Club')!==null || $svc->pdf(2,23,'Club')!==null || $svc->pdf(1,999,'Club')!==null) throw new RuntimeException('Isolation des membres incorrecte');
if ($svc->pdf(1,23,'Club')!=='%PDF-membre-un' || $svc->pdf(2,50,'Club')!=='%PDF-secret-deux') throw new RuntimeException('Reçu archivé incorrect');
$legacy=$svc->pdf(1,1,'Club'); if (!is_string($legacy) || !str_starts_with($legacy,'%PDF-') || !str_contains($legacy,'%%EOF')) throw new RuntimeException('Reçu historique indisponible');
$db->exec("UPDATE payment_receipts SET pdf='contenu invalide' WHERE payment_id=23");
try { $svc->pdf(1,23,'Club'); throw new RuntimeException('Reçu corrompu accepté'); } catch (RuntimeException $e) { if ($e->getMessage()==='Reçu corrompu accepté') throw $e; }
$code=file_get_contents(__DIR__.'/../app/controllers/MemberPortalController.php');
$receipt=substr($code,strpos($code,'public function receiptPdf()'),strpos($code,'public function card()')-strpos($code,'public function receiptPdf()'));
if (!str_contains($receipt,'validSession') || !str_contains($receipt,"['member_portal']['member_id']") || !str_contains($receipt,'secureHeaders()') || !str_contains($receipt,'404')) throw new RuntimeException('Route insuffisamment protégée');
echo "Paiements membre : historique complet, PDF et isolation OK\n";
