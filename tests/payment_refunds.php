<?php
declare(strict_types=1);
require __DIR__.'/../app/services/PaymentRefundService.php';
require __DIR__.'/../app/services/SubscriptionPaymentService.php';
class RefundTestPDO extends PDO { public function prepare(string $sql,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$sql),$options); } }
$db=new RefundTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE subscriptions (id INTEGER PRIMARY KEY,member_id INTEGER,agreed_price DECIMAL(10,3)); CREATE TABLE payments (id INTEGER PRIMARY KEY AUTOINCREMENT,member_id INTEGER,subscription_id INTEGER,amount DECIMAL(10,3),method TEXT); CREATE TABLE payment_refunds (id INTEGER PRIMARY KEY AUTOINCREMENT,payment_id INTEGER,amount DECIMAL(10,3),reason TEXT,request_token TEXT UNIQUE,created_by INTEGER,refunded_at TEXT DEFAULT CURRENT_TIMESTAMP); CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT); INSERT INTO subscriptions VALUES (1,1,100),(2,2,NULL); INSERT INTO payments (id,member_id,subscription_id,amount,method) VALUES (1,1,1,100,'espèces'),(2,2,2,30,'D17'),(3,1,NULL,10,'espèces');");
$service=new PaymentRefundService($db,1,'127.0.0.1'); $pay=new SubscriptionPaymentService($db,1,'127.0.0.1'); $token=str_repeat('a',64);
try { $pay->pay(1,'1','espèces'); throw new RuntimeException('Surpaiement initial accepté'); } catch (DomainException $ex) {}
foreach ([['0','Motif'],['100.001','Motif'],['1','x'],['1',"abc\n"]] as [$amount,$reason]) { try { $service->record(1,$amount,$reason,$token); throw new RuntimeException('Remboursement invalide accepté'); } catch (DomainException $ex) {} }
try { $service->record(3,'1','Motif',$token); throw new RuntimeException('Paiement sans abonnement accepté'); } catch (DomainException $ex) {}
$service->record(1,'25.125','Correction réelle',$token);
try { $service->record(1,'25.125','Correction réelle',$token); throw new RuntimeException('Double soumission acceptée'); } catch (DomainException $ex) {}
try { $service->record(1,'74.876','Motif',str_repeat('b',64)); throw new RuntimeException('Sur-remboursement accepté'); } catch (DomainException $ex) {}
$pay->pay(1,'25.125','D17');
try { $pay->pay(1,'0.001','D17'); throw new RuntimeException('Solde net mal calculé'); } catch (DomainException $ex) {}
$service->record(2,'10','Remboursement',str_repeat('c',64)); (new SubscriptionPriceService($db,1,''))->verify(2,'20');
$service->record(1,'74.875','Reste remboursé',str_repeat('d',64));
try { $service->record(1,'0.001','Encore',str_repeat('e',64)); throw new RuntimeException('Remboursement après limite accepté'); } catch (DomainException $ex) {}
$db->exec('DROP TABLE activity_logs'); $before=(int)$db->query('SELECT COUNT(*) FROM payment_refunds')->fetchColumn();
try { $service->record(2,'1','Erreur audit',str_repeat('f',64)); throw new RuntimeException('Échec audit ignoré'); } catch (PDOException $ex) {}
if ((int)$db->query('SELECT COUNT(*) FROM payment_refunds')->fetchColumn()!==$before || $db->inTransaction()) throw new RuntimeException('Rollback remboursement incorrect');
echo "Remboursements : partiels, solde, idempotence, plafonds et rollback OK\n";
