<?php
declare(strict_types=1);
class ScheduleControllerPDO extends PDO {
    public function prepare(string $sql,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$sql),$options); }
}
$db=new ScheduleControllerPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE members (id INTEGER PRIMARY KEY,first_name TEXT,last_name TEXT,card_number TEXT,status TEXT); CREATE TABLE users (id INTEGER PRIMARY KEY,name TEXT,email TEXT,role TEXT,is_active INTEGER); CREATE TABLE coach_profiles (user_id INTEGER PRIMARY KEY,phone TEXT,specialties TEXT,commission_rate TEXT); CREATE TABLE rooms (id INTEGER PRIMARY KEY,name TEXT); CREATE TABLE courses (id INTEGER PRIMARY KEY,title TEXT,room_id INTEGER,coach_id INTEGER,status TEXT,starts_at TEXT,ends_at TEXT,capacity INTEGER); CREATE TABLE course_bookings (id INTEGER PRIMARY KEY AUTOINCREMENT,course_id INTEGER,member_id INTEGER,status TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP); CREATE TABLE subscriptions (id INTEGER PRIMARY KEY,member_id INTEGER,status TEXT,start_date TEXT,end_date TEXT); CREATE TABLE private_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT,coach_id INTEGER,member_id INTEGER,starts_at TEXT,ends_at TEXT,price TEXT,commission_rate TEXT,status TEXT DEFAULT 'scheduled'); CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT); INSERT INTO members VALUES (1,'Ali','A','PF1','active'),(2,'Sami','B','PF2','active'); INSERT INTO users VALUES (1,'Coach A','a@x','Coach',1),(2,'Coach B','b@x','Coach',1); INSERT INTO coach_profiles VALUES (1,'','',0),(2,'','',0); INSERT INTO rooms VALUES (1,'Studio'); INSERT INTO courses VALUES (1,'Yoga',1,1,'scheduled','2090-05-01 10:00:00','2090-05-01 11:00:00',5); INSERT INTO subscriptions VALUES (1,1,'active','2090-01-01','2090-12-31'),(2,2,'active','2090-01-01','2090-12-31');");
function db(): PDO { global $db; return $db; } function verify_csrf(): void {} function csrf(): string { return 'csrf'; } function e($s): string { return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); } function gym_name(): string { return 'Salle'; } function gym_brand_markup(): string { return 'Salle'; } function dt($x): string { return number_format((float)$x,3,',',' ').' DT'; }
class RedirectTest extends Exception {} function redirect(string $route): void { throw new RedirectTest($route); }
require __DIR__.'/../app/controllers/CoachController.php'; require __DIR__.'/../app/controllers/CourseController.php';
$_SESSION=['user'=>['id'=>42,'name'=>'Réception','role'=>'Réceptionniste']]; $_SERVER['REQUEST_METHOD']='POST'; $_SERVER['REMOTE_ADDR']='127.0.0.1'; $_GET=[];
$_POST=['action'=>'session','coach_id'=>'2','member_id'=>'1','starts_at'=>'2090-05-01T10:00','ends_at'=>'2090-05-01T11:00','price'=>'10'];
try { (new CoachController())->index(); throw new RuntimeException('Séance non créée'); } catch (RedirectTest $ex) {}
$_POST=['action'=>'book','course_id'=>'1','member_id'=>'1']; ob_start(); (new CourseController())->index(); $html=ob_get_clean();
if (!str_contains($html,'déjà une séance privée') || (int)$db->query('SELECT COUNT(*) FROM course_bookings')->fetchColumn()!==0) throw new RuntimeException('Conflit réservation non rejeté');
$_POST=['action'=>'book','course_id'=>'1','member_id'=>'2']; try { (new CourseController())->index(); throw new RuntimeException('Inscription non créée'); } catch (RedirectTest $ex) {}
$_POST=['action'=>'session','coach_id'=>'2','member_id'=>'2','starts_at'=>'2090-05-01T10:00','ends_at'=>'2090-05-01T11:00','price'=>'10']; ob_start(); (new CoachController())->index(); $html=ob_get_clean();
if (!str_contains($html,'déjà inscrit à un cours collectif') || (int)$db->query('SELECT COUNT(*) FROM private_sessions')->fetchColumn()!==1) throw new RuntimeException('Conflit séance non rejeté');
// Deux cours différents ne peuvent pas être confirmés sur des créneaux qui se chevauchent.
$db->exec("INSERT INTO members VALUES (3,'Nour','C','PF3','active'),(4,'Meriem','D','PF4','active'); INSERT INTO subscriptions VALUES (3,3,'active','2090-01-01','2090-12-31'),(4,4,'active','2090-01-01','2090-12-31'); INSERT INTO courses VALUES (2,'Boxe',1,NULL,'scheduled','2090-05-01 10:30:00','2090-05-01 11:30:00',1),(3,'Pilates',1,NULL,'scheduled','2090-05-01 11:00:00','2090-05-01 12:00:00',5);");
$_POST=['action'=>'book','course_id'=>'2','member_id'=>'2']; ob_start(); (new CourseController())->index(); $html=ob_get_clean();
if (!str_contains($html,'déjà inscrit à un autre cours collectif')) throw new RuntimeException('Chevauchement de cours accepté');
$_POST=['action'=>'book','course_id'=>'3','member_id'=>'2']; try { (new CourseController())->index(); throw new RuntimeException('Cours adjacent refusé'); } catch (RedirectTest $ex) {}
$_POST=['action'=>'book','course_id'=>'2','member_id'=>'3']; try { (new CourseController())->index(); throw new RuntimeException('Réservation non créée'); } catch (RedirectTest $ex) {}
foreach ([2,4] as $member) { $_POST=['action'=>'book','course_id'=>'2','member_id'=>(string)$member]; try { (new CourseController())->index(); throw new RuntimeException('Attente non créée'); } catch (RedirectTest $ex) {} }
$cancel=(int)$db->query('SELECT id FROM course_bookings WHERE course_id=2 AND member_id=3')->fetchColumn();
$_POST=['action'=>'cancel_booking','course_id'=>'2','booking_id'=>(string)$cancel]; try { (new CourseController())->index(); throw new RuntimeException('Annulation non effectuée'); } catch (RedirectTest $ex) {}
if ($db->query('SELECT status FROM course_bookings WHERE course_id=2 AND member_id=2')->fetchColumn()!=='waiting' || $db->query('SELECT status FROM course_bookings WHERE course_id=2 AND member_id=4')->fetchColumn()!=='confirmed') throw new RuntimeException('Promotion sans tenir compte du cours confirmé');
// La liste d'attente ne bloque pas une séance privée et la promotion refuse le conflit.
$db->exec("UPDATE courses SET capacity=1 WHERE id=1");
$_POST=['action'=>'book','course_id'=>'1','member_id'=>'1']; try { (new CourseController())->index(); throw new RuntimeException('Liste d’attente non créée'); } catch (RedirectTest $ex) {}
if ($db->query("SELECT status FROM course_bookings WHERE member_id=1")->fetchColumn()!=='waiting') throw new RuntimeException('Liste d’attente incorrecte');
$booking=(int)$db->query("SELECT id FROM course_bookings WHERE course_id=1 AND member_id=2")->fetchColumn();
$_POST=['action'=>'cancel_booking','course_id'=>'1','booking_id'=>(string)$booking]; try { (new CourseController())->index(); throw new RuntimeException('Annulation non enregistrée'); } catch (RedirectTest $ex) {}
if ($db->query("SELECT status FROM course_bookings WHERE member_id=1")->fetchColumn()!=='waiting') throw new RuntimeException('Promotion malgré séance privée');
echo "Planning membre : contrôleurs cours et séance appliquent les deux contrôles OK\n";
