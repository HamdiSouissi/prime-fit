<?php
declare(strict_types=1);
require __DIR__.'/../app/services/CourseEditService.php';
class CourseEditTestPDO extends PDO { public function prepare(string $query,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); } }
$db=new CourseEditTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE rooms (id INTEGER PRIMARY KEY,name TEXT); CREATE TABLE users (id INTEGER PRIMARY KEY,role TEXT,is_active INTEGER); CREATE TABLE courses (id INTEGER PRIMARY KEY,title TEXT,room_id INTEGER,coach_id INTEGER,starts_at TEXT,ends_at TEXT,capacity INTEGER,status TEXT); CREATE TABLE private_sessions (id INTEGER PRIMARY KEY,coach_id INTEGER,status TEXT,starts_at TEXT,ends_at TEXT); CREATE TABLE members (id INTEGER PRIMARY KEY,status TEXT); CREATE TABLE subscriptions (id INTEGER PRIMARY KEY,member_id INTEGER,status TEXT,start_date TEXT,end_date TEXT); CREATE TABLE course_bookings (id INTEGER PRIMARY KEY,course_id INTEGER,member_id INTEGER,status TEXT,created_at TEXT); CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT);
INSERT INTO rooms VALUES (1,'A'),(2,'B'); INSERT INTO users VALUES (1,'Coach',1),(2,'Coach',1); INSERT INTO courses VALUES (1,'Yoga',1,1,'2030-05-01 10:00:00','2030-05-01 11:00:00',1,'scheduled'),(2,'Boxe',2,2,'2030-05-01 10:00:00','2030-05-01 11:00:00',5,'scheduled'); INSERT INTO private_sessions VALUES (1,1,'scheduled','2030-05-01 15:00:00','2030-05-01 16:00:00'); INSERT INTO members VALUES (1,'active'),(2,'inactive'),(3,'active'); INSERT INTO subscriptions VALUES (1,1,'active','2030-01-01','2030-12-31'),(2,2,'active','2030-01-01','2030-12-31'),(3,3,'active','2030-01-01','2030-12-31'); INSERT INTO course_bookings VALUES (1,1,1,'confirmed','2029-01-01'),(2,1,2,'waiting','2029-01-02'),(3,1,3,'waiting','2029-01-03');");
$get=static fn()=> $db->query('SELECT * FROM courses WHERE id=1')->fetch();
$service=new CourseEditService($db,1,'127.0.0.1',new DateTimeImmutable('2029-01-01'));
$input=['title'=>'Yoga','room_id'=>'1','coach_id'=>'1','starts_at'=>'2030-05-01T10:00','ends_at'=>'2030-05-01T11:00','capacity'=>'1']; $version=CourseEditService::version($get());
foreach ([['starts_at'=>'2030-05-01T11:00','ends_at'=>'2030-05-01T12:00'],['room_id'=>'2'],['coach_id'=>'2'],['capacity'=>'0'],['starts_at'=>"2030\0"]] as $invalid) {
    try { $service->edit(1,$version,array_replace($input,$invalid)); throw new RuntimeException('Modification invalide acceptée'); } catch (DomainException $ex) {}
}
$input['title']='Yoga doux'; $input['capacity']='2';
if ($service->edit(1,$version,$input)!==1 || $get()['title']!=='Yoga doux') throw new RuntimeException('Modification ou promotion incorrecte');
if ($db->query('SELECT status FROM course_bookings WHERE id=2')->fetchColumn()!=='waiting' || $db->query('SELECT status FROM course_bookings WHERE id=3')->fetchColumn()!=='confirmed') throw new RuntimeException('Priorité/éligibilité incorrecte');
try { $service->edit(1,$version,$input); throw new RuntimeException('Version obsolète acceptée'); } catch (DomainException $ex) {}
try { $service->edit(1,CourseEditService::version($get()),array_replace($input,['capacity'=>'1'])); throw new RuntimeException('Capacité insuffisante acceptée'); } catch (DomainException $ex) {}
$db->exec("UPDATE course_bookings SET status='cancelled' WHERE course_id=1");
$version=CourseEditService::version($get());
try { $service->edit(1,$version,array_replace($input,['starts_at'=>'2030-05-01T15:00','ends_at'=>'2030-05-01T16:00'])); throw new RuntimeException('Conflit séance privée accepté'); } catch (DomainException $ex) {}
$service->edit(1,$version,array_replace($input,['starts_at'=>'2030-05-01T11:00','ends_at'=>'2030-05-01T12:00']));
if ($get()['starts_at']!=='2030-05-01 11:00:00') throw new RuntimeException('Créneau adjacent refusé');
$db->exec('DROP TABLE activity_logs'); $before=$get();
try { $service->edit(1,CourseEditService::version($before),array_replace($input,['starts_at'=>'2030-05-01T11:00','ends_at'=>'2030-05-01T12:00','title'=>'Échec audit'])); throw new RuntimeException('Échec audit ignoré'); } catch (PDOException $ex) {}
if ($get()['title']!==$before['title']) throw new RuntimeException('Rollback incorrect');
echo "Cours : édition, conflits, inscriptions, attente et rollback OK\n";
