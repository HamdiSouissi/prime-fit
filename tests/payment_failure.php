<?php
declare(strict_types=1);
require __DIR__.'/../app/services/SubscriptionPaymentService.php';
require __DIR__.'/../app/services/PaymentFailureMessage.php';
class PaymentFailurePDO extends PDO { public function prepare(string $sql,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$sql),$options); } }
$db=new PaymentFailurePDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE subscriptions (id INTEGER,member_id INTEGER,agreed_price TEXT); CREATE TABLE payments (id INTEGER PRIMARY KEY,member_id INTEGER,subscription_id INTEGER,amount TEXT,method TEXT); CREATE TABLE payment_refunds (payment_id INTEGER,amount TEXT); INSERT INTO subscriptions VALUES(1,1,'50.000')");
$service=new SubscriptionPaymentService($db,1,'127.0.0.1');
try { $service->pay(1,'10','espèces'); throw new RuntimeException('Audit absent accepté'); } catch (PDOException $ex) {
    if ($service->failureStage()!=='journal d’activité' || $db->inTransaction() || (int)$db->query('SELECT COUNT(*) FROM payments')->fetchColumn()!==0) throw new RuntimeException('Étape ou rollback incorrect');
    $msg=PaymentFailureMessage::describe($service->failureStage(),$ex);
    if (!str_contains($msg,'journal d’activité') || !str_contains($msg,'SQLSTATE') || str_contains($msg,$ex->getMessage())) throw new RuntimeException('Diagnostic incorrect');
}
$ex=new PDOException('Données personnelles'); $ex->errorInfo=['01000',1265,'Données personnelles'];
$msg=PaymentFailureMessage::describe('enregistrement du paiement',$ex);
if (!str_contains($msg,'1265') || !str_contains($msg,'SHOW CREATE TABLE payments') || str_contains($msg,'Données personnelles')) throw new RuntimeException('Diagnostic du schéma incorrect');
if (!str_contains(PaymentFailureMessage::describe('validation de la transaction',$ex),'avant toute nouvelle tentative')) throw new RuntimeException('Résultat de commit ambigu mal traité');
echo "Paiement : diagnostic sûr, étape et rollback OK\n";
