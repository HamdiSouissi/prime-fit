<?php
declare(strict_types=1);
require __DIR__.'/../app/services/SubscriptionPaymentService.php';
class PriceTestPDO extends PDO { public function prepare(string $query,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); } }
$db=new PriceTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE subscriptions (id INTEGER PRIMARY KEY,member_id INTEGER,agreed_price TEXT,plan_id INTEGER); CREATE TABLE members(id INTEGER PRIMARY KEY,first_name TEXT,last_name TEXT,email TEXT,card_number TEXT); CREATE TABLE plans(id INTEGER PRIMARY KEY,name TEXT); CREATE TABLE payment_receipts(payment_id INTEGER PRIMARY KEY,pdf BLOB,recipient_email TEXT); INSERT INTO members VALUES(1,'Amine','Test','test@example.tn','CARTE1'),(2,'Test','Deux',NULL,'CARTE2'); INSERT INTO plans VALUES(1,'Mensuel'); CREATE TABLE payment_refunds (id INTEGER PRIMARY KEY,payment_id INTEGER,amount DECIMAL(10,3),refunded_at TEXT); CREATE TABLE payments (id INTEGER PRIMARY KEY AUTOINCREMENT,member_id INTEGER,subscription_id INTEGER,amount DECIMAL(10,3),method TEXT,paid_at TEXT DEFAULT '2030-01-01 10:00:00'); CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT); INSERT INTO subscriptions VALUES (1,1,NULL,1),(2,2,'50.000',1); INSERT INTO payments (member_id,subscription_id,amount,method) VALUES (1,1,30.125,'espèces');");
$verify=new SubscriptionPriceService($db,1,'127.0.0.1'); $payment=new SubscriptionPaymentService($db,1,'127.0.0.1');
try { $payment->pay(1,'1.000','espèces'); throw new RuntimeException('Paiement sans prix accepté'); } catch (DomainException $ex) {}
try { $verify->verify(1,'30.124'); throw new RuntimeException('Prix inférieur aux paiements accepté'); } catch (DomainException $ex) {}
try { $verify->verify(1,'5.0000'); throw new RuntimeException('Précision invalide acceptée'); } catch (DomainException $ex) {}
if ($db->query('SELECT agreed_price FROM subscriptions WHERE id=1')->fetchColumn()!==null) throw new RuntimeException('Prix fixé sans vérification');
$verify->verify(1,'90,250');
try { $verify->verify(1,'100'); throw new RuntimeException('Prix déjà fixé modifiable'); } catch (DomainException $ex) {}
try { $payment->pay(1,'60.126','espèces'); throw new RuntimeException('Surpaiement accepté'); } catch (DomainException $ex) {}
$payment->pay(1,'60.125','D17');
if ((int)$db->query('SELECT COUNT(*) FROM payments WHERE subscription_id=1')->fetchColumn()!==2) throw new RuntimeException('Paiement non enregistré');
try { $payment->pay(1,'0.001','espèces'); throw new RuntimeException('Paiement après solde accepté'); } catch (DomainException $ex) {}
$db->exec('DROP TABLE activity_logs');
try { $payment->pay(2,'1','espèces'); throw new RuntimeException('Échec audit ignoré'); } catch (PDOException $ex) {}
if ((int)$db->query('SELECT COUNT(*) FROM payments WHERE subscription_id=2')->fetchColumn()!==0) throw new RuntimeException('Rollback paiement incorrect');
echo "Tarifs : vérification historique, paiement au prix figé et rollback OK\n";
