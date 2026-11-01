<?php
declare(strict_types=1);
require __DIR__.'/../app/services/CourseBookings.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE rooms (id INTEGER PRIMARY KEY,name TEXT); CREATE TABLE courses (id INTEGER PRIMARY KEY,title TEXT,room_id INTEGER,starts_at TEXT,ends_at TEXT,status TEXT); CREATE TABLE members (id INTEGER PRIMARY KEY,first_name TEXT,last_name TEXT); CREATE TABLE course_bookings (id INTEGER PRIMARY KEY,course_id INTEGER,member_id INTEGER,status TEXT,created_at TEXT,updated_at TEXT); INSERT INTO rooms VALUES (1,'Salle'); INSERT INTO courses VALUES (1,'Yoga',1,'2030-01-01 10:00:00','2030-01-01 11:00:00','cancelled'),(2,'Pilates',1,'2030-01-02 10:00:00','2030-01-02 11:00:00','scheduled')");
$member=$db->prepare('INSERT INTO members VALUES (?,?,?)'); $booking=$db->prepare('INSERT INTO course_bookings VALUES (?,?,?,?,?,?)');
for ($i=1;$i<=45;$i++) { $member->execute([$i,'Membre '.$i,'Test']); $booking->execute([$i,1,$i,($i%2 ? 'cancelled' : 'confirmed'),'2029-12-01 12:00:00','2029-12-02 12:00:00']); }
$booking->execute([46,2,1,'waiting','2029-12-01 12:00:00','2029-12-02 12:00:00']);
$service=new CourseBookings($db); if ($service->course(999)!==null || $service->course(1)['status']!=='cancelled') throw new RuntimeException('Cours incorrect');
$all=$service->page(1,CourseBookings::status([]),1); $last=$service->page(1,'',999); if ($all['total']!==45 || $all['pages']!==3 || count($all['rows'])!==20 || $all['rows'][0]['id']!==45 || $last['page']!==3 || count($last['rows'])!==5) throw new RuntimeException('Pagination ou tri incorrect');
$cancelled=$service->page(1,CourseBookings::status(['status'=>'cancelled']),1); if ($cancelled['total']!==23 || $cancelled['rows'][0]['status']!=='cancelled') throw new RuntimeException('Annulations non trouvées');
if ($service->page(1,'waiting',1)['total']!==0 || $service->page(2,'waiting',1)['total']!==1) throw new RuntimeException('Isolation des cours incorrecte');
foreach ([['status'=>'bad'],['status'=>['x']],['status'=>"cancelled' OR 1=1 --"]] as $bad) { try { CourseBookings::status($bad); throw new RuntimeException('Filtre invalide accepté'); } catch (DomainException $ex) {} }
$db->exec("UPDATE course_bookings SET status='confirmed',created_at='2029-12-10 10:00:00',updated_at='2029-12-10 10:00:00' WHERE id=45");
if ($service->page(1,'cancelled',1)['total']!==22 || $service->page(1,'confirmed',1)['rows'][0]['id']!==45) throw new RuntimeException('Réinscription : état actuel incorrect');
echo "Cours : registre paginé, annulations, filtres et réinscription OK\n";
