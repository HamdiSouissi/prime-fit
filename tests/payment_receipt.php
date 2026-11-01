<?php
declare(strict_types=1);
require __DIR__.'/../app/services/SubscriptionPaymentService.php';
require __DIR__.'/../app/services/PaymentReceiptMailer.php';
class ReceiptTestPDO extends PDO { public function prepare(string $sql,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$sql),$options); } }
$db=new ReceiptTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE members(id INTEGER,first_name TEXT,last_name TEXT,email TEXT,card_number TEXT); CREATE TABLE plans(id INTEGER,name TEXT); CREATE TABLE subscriptions(id INTEGER,member_id INTEGER,plan_id INTEGER,agreed_price TEXT); CREATE TABLE payments(id INTEGER PRIMARY KEY,member_id INTEGER,subscription_id INTEGER,amount TEXT,method TEXT,paid_at TEXT DEFAULT '2030-01-02 10:00:00'); CREATE TABLE payment_refunds(payment_id INTEGER,amount TEXT); CREATE TABLE payment_receipts(payment_id INTEGER,pdf BLOB,recipient_email TEXT); CREATE TABLE activity_logs(user_id INTEGER,action TEXT,ip_address TEXT); INSERT INTO members VALUES(1,'Amine','<Test>','amine@example.tn','C1'); INSERT INTO plans VALUES(1,'Formule'); INSERT INTO subscriptions VALUES(1,1,1,'100.000');");
$service=new SubscriptionPaymentService($db,1,'127.0.0.1','Horizon');
$id=$service->pay(1,'15.500','espèces'); $receipt=$service->lastReceipt();
$stmt=$db->prepare('SELECT pdf FROM payment_receipts WHERE payment_id=?'); $stmt->execute([$id]); $pdf=$stmt->fetchColumn();
if (!is_string($pdf) || !str_starts_with($pdf,'%PDF-') || !str_contains($pdf,'%%EOF') || $pdf!==$receipt['pdf'] || strlen($pdf)<1000) throw new RuntimeException('PDF absent ou différent');
$db->exec("UPDATE members SET first_name='Changed' WHERE id=1; UPDATE plans SET name='Changed'"); $stmt->execute([$id]); if ($stmt->fetchColumn()!==$pdf) throw new RuntimeException('PDF historique modifié');
$captured=[]; $mailer=new PaymentReceiptMailer(['enabled'=>true,'from'=>'club@example.tn'],static function($to,$subject,$body,$headers) use (&$captured) { $captured=compact('to','subject','body','headers'); return true; });
if ($mailer->send($receipt)!=='accepted' || $captured['to']!=='amine@example.tn' || !str_contains($captured['body'],'application/pdf') || !str_contains($captured['body'],'recu-paiement-1.pdf') || !str_contains($captured['body'],chunk_split(base64_encode($pdf))) || !str_contains($captured['headers'],'multipart/mixed')) throw new RuntimeException('E-mail ou pièce jointe incorrects');
preg_match('/Content-Type: text\/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n([A-Za-z0-9+\/=\r\n]+)/',$captured['body'],$match); $html=base64_decode($match[1] ?? '',true);
if (!$html || !str_contains($html,'&lt;Test&gt;') || !str_contains($html,'#ffd400') || str_contains($html,'<Test>')) throw new RuntimeException('HTML incorrect ou non échappé');
if ((new PaymentReceiptMailer(['enabled'=>true,'from'=>'club@example.tn'],static fn()=>false))->send($receipt)!=='failed') throw new RuntimeException('Échec du transport ignoré');
if ((new PaymentReceiptMailer(['enabled'=>false]))->send($receipt)!=='not_configured') throw new RuntimeException('Configuration e-mail ignorée');
$receipt['payment']['email']=''; if ($mailer->send($receipt)!=='missing_email') throw new RuntimeException('Adresse manquante ignorée');
$stmt->closeCursor(); $db->exec('DROP TABLE payment_receipts');
try { $service->pay(1,'1','D17'); throw new RuntimeException('Reçu manquant accepté'); } catch (PDOException $ex) {}
if ((int)$db->query('SELECT COUNT(*) FROM payments')->fetchColumn()!==1 || $db->inTransaction()) throw new RuntimeException('Rollback du reçu incorrect');
echo "Paiements : PDF figé, e-mail, échappement et rollback OK\n";
