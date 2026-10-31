<?php
declare(strict_types=1);
require __DIR__.'/../app/services/MemberProfile.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE members (id INTEGER PRIMARY KEY,first_name TEXT,last_name TEXT,phone TEXT,card_number TEXT,governorate TEXT,status TEXT,created_at TEXT,email TEXT,birth_date TEXT,qr_token TEXT); CREATE TABLE plans (id INTEGER PRIMARY KEY,name TEXT); CREATE TABLE subscriptions (id INTEGER PRIMARY KEY,member_id INTEGER,plan_id INTEGER,start_date TEXT,end_date TEXT,status TEXT); CREATE TABLE payments (id INTEGER PRIMARY KEY,member_id INTEGER,subscription_id INTEGER,amount TEXT,method TEXT,paid_at TEXT); CREATE TABLE attendance (id INTEGER PRIMARY KEY,member_id INTEGER,checked_in_at TEXT,result TEXT); INSERT INTO members VALUES (1,'Amina','Test','+21612345678','PF-123','Tunis','active','2025-01-01','test@example.com','2000-01-01','secret-qr'), (2,'Autre','Test',NULL,'PF-456',NULL,'active','2025-01-01',NULL,NULL,'autre-qr'); INSERT INTO plans VALUES (1,'Mensuel');");
for ($i=1;$i<=11;$i++) { $db->prepare('INSERT INTO subscriptions VALUES (?,?,?,?,?,?)')->execute([$i,1,1,sprintf('2025-01-%02d',$i),'2025-02-28','active']); $db->prepare('INSERT INTO payments VALUES (?,?,?,?,?,?)')->execute([$i,1,$i,'10.000','espèces',sprintf('2025-01-%02d 12:00:00',$i)]); $db->prepare('INSERT INTO attendance VALUES (?,?,?,?)')->execute([$i,1,sprintf('2025-01-%02d 12:00:00',$i),'accepted']); }
$db->exec("INSERT INTO payments VALUES (99,2,NULL,'99.000','espèces','2025-01-01 12:00:00')");
$service=new MemberProfile($db);
$admin=$service->get(1,'Admin');
if ($admin['subscriptionCount']!==11 || count($admin['subscriptions'])!==10 || $admin['paymentCount']!==11 || $admin['attendanceCount']!==11 || (int)$admin['subscriptions'][0]['id']!==11 || isset($admin['member']['qr_token'])) throw new RuntimeException('Historique ou données incorrects');
$coach=$service->get(1,'Coach'); if (isset($coach['payments']) || isset($coach['member']['email']) || isset($coach['member']['birth_date']) || !isset($coach['attendance'])) throw new RuntimeException('Permissions coach incorrectes');
$accountant=$service->get(1,'Comptable'); if (isset($accountant['attendance']) || isset($accountant['member']['email']) || !isset($accountant['payments'])) throw new RuntimeException('Permissions comptable incorrectes');
$reception=$service->get(1,'Réceptionniste'); if (!$reception['canPayments'] || !$reception['canAttendance'] || !$reception['canContacts']) throw new RuntimeException('Permissions réception incorrectes');
if ($service->get(123,'Admin')!==null) throw new RuntimeException('Membre manquant accepté');
try { $service->get(1,'Inconnu'); throw new RuntimeException('Rôle invalide accepté'); } catch (DomainException $ex) {}
echo "Fiche membre : filtres par rôle, séparation et limite historique OK\n";
