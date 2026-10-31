<?php
declare(strict_types=1);
require __DIR__.'/../app/services/SubscriptionLookup.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->sqliteCreateFunction('CONCAT',static fn(...$parts)=>implode('',$parts),-1);
$db->exec("CREATE TABLE members (id INTEGER PRIMARY KEY,first_name TEXT,last_name TEXT,card_number TEXT); CREATE TABLE plans (id INTEGER PRIMARY KEY,name TEXT); CREATE TABLE subscriptions (id INTEGER PRIMARY KEY,member_id INTEGER,plan_id INTEGER,start_date TEXT,end_date TEXT,status TEXT,agreed_price TEXT,created_at TEXT); CREATE TABLE users (id INTEGER PRIMARY KEY,name TEXT); CREATE TABLE subscription_freezes (id INTEGER PRIMARY KEY,subscription_id INTEGER,frozen_on TEXT,resumed_on TEXT,credited_days INTEGER,state TEXT,created_by INTEGER,closed_by INTEGER); INSERT INTO members VALUES (1,'Amine','Salah','PF-123'),(2,'Meriem','Test','PF-456'); INSERT INTO plans VALUES (1,'Mensuel'); INSERT INTO users VALUES (1,'Admin'); INSERT INTO subscriptions VALUES (1,1,1,'2026-01-01','2026-01-30','active','90.000','2026-01-01'),(2,1,1,'2026-02-01','2026-03-01','active','90.000','2026-01-02'),(3,2,1,'2026-01-01','2026-03-01','frozen',NULL,'2026-01-03'),(4,2,1,'2026-01-01','2026-03-01','cancelled','90.000','2026-01-04'),(5,2,1,'2026-01-01','2026-03-01','active','90.000','2026-01-05');");
for ($i=1;$i<=11;$i++) $db->prepare('INSERT INTO subscription_freezes VALUES (?,?,?,?,?,?,?,?)')->execute([$i,1,sprintf('2026-01-%02d',$i),'2026-01-20',2,'resumed',1,1]);
$db->exec("INSERT INTO subscription_freezes VALUES (12,3,'2026-01-10',NULL,NULL,'open',1,NULL)");
$lookup=new SubscriptionLookup($db); $today='2026-02-01';
foreach (['current'=>2,'upcoming'=>0,'expired'=>1,'frozen'=>1,'cancelled'=>1] as $filter=>$expected) { $r=$lookup->search('',$filter,1,$today); if ($r['total']!==$expected) throw new RuntimeException('Filtre incorrect : '.$filter); }
if ($lookup->search('Amine Salah','',1,$today)['total']!==2 || $lookup->search('PF-456','',1,$today)['total']!==3 || $lookup->search('Mensuel','',1,$today)['total']!==5) throw new RuntimeException('Recherche incorrecte');
if ($lookup->search('Meriem','frozen',1,$today)['total']!==1 || $lookup->search('Inexistant','',1,$today)['total']!==0) throw new RuntimeException('Combinaison de filtres incorrecte');
$r=$lookup->detail(1,99); if ($r['total']!==11 || $r['page']!==2 || count($r['freezes'])!==1 || $r['freezes'][0]['created_name']!=='Admin') throw new RuntimeException('Historique incorrect');
if ($lookup->detail(3,1)['openCount']!==1 || $lookup->detail(999,1)!==null) throw new RuntimeException('Gel ouvert ou absence incorrecte');
echo "Abonnements : recherche, filtres et historique paginé OK\n";
