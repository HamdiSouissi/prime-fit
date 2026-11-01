<?php
declare(strict_types=1);
require __DIR__.'/../app/services/MemberPortalBooking.php';
class BookingTestPDO extends PDO {
    public array $queries=[];
    public function prepare(string $sql,array $options=[]): PDOStatement|false { $this->queries[]=$sql; return parent::prepare(str_replace(' FOR UPDATE','',$sql),$options); }
}
$db=new BookingTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE members(id INTEGER PRIMARY KEY,email TEXT,status TEXT); CREATE TABLE member_portal_accounts(member_id INTEGER,email TEXT,password_hash TEXT); CREATE TABLE courses(id INTEGER PRIMARY KEY,status TEXT,starts_at TEXT,ends_at TEXT,capacity INTEGER); CREATE TABLE subscriptions(id INTEGER PRIMARY KEY,member_id INTEGER,status TEXT,start_date TEXT,end_date TEXT); CREATE TABLE course_bookings(id INTEGER PRIMARY KEY,course_id INTEGER,member_id INTEGER,status TEXT,created_at TEXT,UNIQUE(course_id,member_id)); CREATE TABLE private_sessions(id INTEGER PRIMARY KEY,member_id INTEGER,status TEXT,starts_at TEXT,ends_at TEXT); CREATE TABLE activity_logs(id INTEGER PRIMARY KEY,user_id INTEGER,action TEXT,ip_address TEXT);");
$db->exec("INSERT INTO members VALUES(1,'one@example.tn','active'),(2,'two@example.tn','active'),(3,'three@example.tn','active'),(4,'four@example.tn','inactive'); INSERT INTO subscriptions VALUES(1,1,'active','2030-01-01','2030-12-31'),(2,2,'active','2030-01-01','2030-12-31'),(4,4,'active','2030-01-01','2030-12-31'); INSERT INTO courses VALUES(1,'scheduled','2030-05-01 10:00:00','2030-05-01 11:00:00',1),(2,'scheduled','2030-05-01 10:30:00','2030-05-01 11:30:00',10),(3,'cancelled','2030-05-01 12:00:00','2030-05-01 13:00:00',10),(4,'scheduled','2030-04-01 10:00:00','2030-04-01 11:00:00',10),(5,'scheduled','2030-05-02 10:00:00','2030-05-02 11:00:00',10);");
$hash=password_hash('Mot de passe membre 123!',PASSWORD_DEFAULT); foreach ([1=>'one@example.tn',2=>'two@example.tn',3=>'three@example.tn',4=>'four@example.tn'] as $id=>$email) $db->prepare('INSERT INTO member_portal_accounts VALUES (?,?,?)')->execute([$id,$email,$hash]);
$session=static fn(int $id): array=>['member_id'=>$id,'fingerprint'=>hash('sha256',$hash)];
$now=new DateTimeImmutable('2030-04-20 10:00:00'); $svc=new MemberPortalBooking($db);
$reject=static function(callable $fn): void { try { $fn(); } catch (DomainException $e) { return; } throw new RuntimeException('Réservation invalide acceptée'); };
$reject(fn()=>$svc->book($session(1),3,'',$now)); $reject(fn()=>$svc->book($session(1),4,'',$now)); $reject(fn()=>$svc->book($session(1),999,'',$now)); $reject(fn()=>$svc->book($session(3),1,'',$now)); $reject(fn()=>$svc->book($session(4),1,'',$now)); $reject(fn()=>$svc->book(['member_id'=>1,'fingerprint'=>'stale'],1,'',$now));
if ($svc->book($session(1),1,'127.0.0.1',$now)!=='confirmed') throw new RuntimeException('Place libre non confirmée');
$reject(fn()=>$svc->book($session(1),1,'',$now)); $reject(fn()=>$svc->book($session(1),2,'',$now));
if ($svc->book($session(2),1,'',$now)!=='waiting') throw new RuntimeException('Liste d’attente non utilisée');
$db->exec("UPDATE course_bookings SET status='cancelled' WHERE course_id=1 AND member_id=2; INSERT INTO private_sessions VALUES(1,2,'scheduled','2030-05-02 10:30:00','2030-05-02 11:30:00');");
$reject(fn()=>$svc->book($session(2),5,'',$now));
$db->exec("UPDATE course_bookings SET status='cancelled' WHERE course_id=1 AND member_id=1");
if ($svc->book($session(2),1,'',$now)!=='confirmed') throw new RuntimeException('Réinscription non confirmée');
if ((int)$db->query('SELECT COUNT(*) FROM course_bookings WHERE course_id=1 AND member_id=2')->fetchColumn()!==1 || (int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn()!==3) throw new RuntimeException('Doublon ou journal incorrect');
$db->exec("UPDATE members SET email='changed@example.tn' WHERE id=2"); $reject(fn()=>$svc->book($session(2),5,'',$now));
$db->exec('DROP TABLE activity_logs');
try { $svc->book($session(1),5,'',$now); throw new RuntimeException('Audit manquant accepté'); } catch (PDOException $e) {}
if ((int)$db->query('SELECT COUNT(*) FROM course_bookings WHERE course_id=5')->fetchColumn()!==0 || $db->inTransaction()) throw new RuntimeException('Transaction non annulée');
foreach (['FROM courses WHERE id=? FOR UPDATE','FROM members WHERE id=? FOR UPDATE','FROM subscriptions','FROM private_sessions','JOIN courses c'] as $check) { $ok=false; foreach ($db->queries as $sql) if (str_contains($sql,$check) && str_contains($sql,'FOR UPDATE')) $ok=true; if (!$ok) throw new RuntimeException('Lecture sous verrou absente : '.$check); }
echo "Espace membre : réservation, attente, accès, conflits et rollback OK\n";
