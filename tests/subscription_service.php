<?php
declare(strict_types=1);
// PDO SQLite permet de tester la logique ; les verrous MySQL restent à tester sur le serveur réel.
require __DIR__.'/../app/services/SubscriptionService.php';
class TestSubscriptionPDO extends PDO {
    public function prepare(string $query, array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); }
}
$db=new TestSubscriptionPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE members (id INTEGER PRIMARY KEY,status TEXT); CREATE TABLE plans (id INTEGER PRIMARY KEY,duration_days INTEGER,is_active INTEGER,price TEXT,name TEXT); CREATE TABLE subscriptions (id INTEGER PRIMARY KEY AUTOINCREMENT,member_id INTEGER,plan_id INTEGER,start_date TEXT,end_date TEXT,status TEXT,agreed_price TEXT); CREATE TABLE subscription_freezes (id INTEGER PRIMARY KEY AUTOINCREMENT,subscription_id INTEGER,frozen_on TEXT,resumed_on TEXT,credited_days INTEGER,state TEXT,created_by INTEGER,closed_by INTEGER); CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT);");
$db->exec("INSERT INTO members VALUES (1,'active'),(2,'active'); INSERT INTO plans VALUES (1,30,1,'90.000','Mensuel'); INSERT INTO subscriptions VALUES (1,1,1,'2026-01-01','2026-01-30','active','90.000');");
$on=static fn(string $date)=>new SubscriptionService($db,1,'127.0.0.1',new DateTimeImmutable($date));
$on('2026-01-10')->freeze(1);
if ($db->query('SELECT status FROM subscriptions WHERE id=1')->fetchColumn()!=='frozen') throw new RuntimeException('Gel non enregistré');
try { $on('2026-01-10')->freeze(1); throw new RuntimeException('Double gel accepté'); } catch (DomainException $expected) {}
$res=$on('2026-01-15')->resume(1);
if ($res['days']!==5 || $res['end']!=='2026-02-04' || $db->query('SELECT status FROM subscriptions WHERE id=1')->fetchColumn()!=='active') throw new RuntimeException('Réactivation incorrecte');
$db->exec("UPDATE plans SET price='110.000' WHERE id=1");
$next=$on('2026-01-15')->renew(1);
if ($db->query('SELECT agreed_price FROM subscriptions WHERE id=1')->fetchColumn()!=='90.000' || $db->query('SELECT agreed_price FROM subscriptions WHERE id='.$next['id'])->fetchColumn()!=='110.000') throw new RuntimeException('Tarif figé ou renouvellement incorrect');
if ($next['start']!=='2026-02-05' || $next['end']!=='2026-03-06') throw new RuntimeException('Renouvellement incorrect');
try { $on('2026-01-15')->renew(1); throw new RuntimeException('Renouvellement en double accepté'); } catch (DomainException $expected) {}
try { $on('2026-01-15')->create(1,1,'2026-02-01'); throw new RuntimeException('Chevauchement accepté'); } catch (DomainException $expected) {}
$on('2026-01-16')->freeze(1);
try { $on('2026-01-17')->resume(1); throw new RuntimeException('Chevauchement après gel accepté'); } catch (DomainException $expected) {}
if ($db->query('SELECT status FROM subscriptions WHERE id=1')->fetchColumn()!=='frozen') throw new RuntimeException('Transaction non annulée');
$on('2026-01-17')->cancel($next['id']); $res=$on('2026-01-17')->resume(1);
if ($res['days']!==1 || $res['end']!=='2026-02-05') throw new RuntimeException('Réactivation après annulation incorrecte');
$db->exec("INSERT INTO subscriptions VALUES (99,2,1,'2026-01-01','2026-02-01','frozen',NULL)");
try { $on('2026-01-17')->resume(99); throw new RuntimeException('Gel historique sans date accepté'); } catch (DomainException $expected) {}
if ($db->query('SELECT status FROM subscriptions WHERE id=99')->fetchColumn()!=='frozen') throw new RuntimeException('Gel historique modifié');
echo "Abonnements : gel, reprise, chevauchement, renouvellement et transactions OK\n";
