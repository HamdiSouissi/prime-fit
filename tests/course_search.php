<?php
declare(strict_types=1);
require __DIR__.'/../app/services/CourseSearch.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE rooms (id INTEGER PRIMARY KEY,name TEXT); CREATE TABLE users (id INTEGER PRIMARY KEY,name TEXT); CREATE TABLE courses (id INTEGER PRIMARY KEY,title TEXT,room_id INTEGER,coach_id INTEGER,starts_at TEXT,ends_at TEXT,capacity INTEGER,status TEXT); CREATE TABLE course_bookings (id INTEGER PRIMARY KEY,course_id INTEGER,status TEXT); INSERT INTO rooms VALUES (1,'Studio 100%'),(2,'Musculation'); INSERT INTO users VALUES (1,'Nadia'),(2,'Amine'); INSERT INTO courses VALUES (1,'Yoga',1,1,'2026-01-01 10:00:00','2026-01-01 11:00:00',10,'scheduled'),(2,'Boxe',2,2,'2026-03-01 10:00:00','2026-03-01 11:00:00',12,'cancelled'),(3,'Zumba',1,NULL,'2026-03-02 10:00:00','2026-03-02 11:00:00',10,'scheduled'); INSERT INTO course_bookings VALUES (1,3,'confirmed'),(2,3,'waiting'),(3,3,'cancelled');");
for ($i=4;$i<=45;$i++) $db->prepare("INSERT INTO courses VALUES (?,'Yoga',1,1,'2026-04-01 10:00:00','2026-04-01 11:00:00',10,'scheduled')")->execute([$i]);
$service=new CourseSearch($db); $today='2026-03-01';
$default=CourseSearch::filters([],$today); $result=$service->search($default,1);
if ($result['total']!==44 || count($result['courses'])!==20 || $result['pages']!==3 || (int)$result['courses'][0]['id']!==2) throw new RuntimeException('Défaut ou pagination incorrects');
$r=$service->search($default,999); if ($r['page']!==3 || count($r['courses'])!==4 || (int)$r['courses'][3]['id']!==45) throw new RuntimeException('Dernière page incorrecte');
$r=$service->search(CourseSearch::filters(['filter'=>'1','from'=>'2026-03-01','to'=>'2026-03-02','status'=>'scheduled'],$today),1);
if ($r['total']!==1 || $r['courses'][0]['confirmed']!=1 || $r['courses'][0]['waiting']!=1) throw new RuntimeException('Filtres ou compte incorrects');
if ($service->search(CourseSearch::filters(['q'=>'Nadia'],$today),1)['total']!==43) throw new RuntimeException('Recherche coach incorrecte');
if ($service->search(CourseSearch::filters(['q'=>'100%'],$today),1)['total']!==44) throw new RuntimeException('Recherche salle littérale incorrecte');
if ($service->search(CourseSearch::filters(['q'=>'%'],$today),1)['total']!==44 || $service->search(CourseSearch::filters(['q'=>'_'],$today),1)['total']!==0) throw new RuntimeException('Joker SQL non échappé');
if ($service->search(CourseSearch::filters(['filter'=>'1','to'=>'2026-01-01'],$today),1)['total']!==1) throw new RuntimeException('Cours historiques manquants');
foreach ([['from'=>'0000-01-01'],['from'=>'2026-02-30'],['from'=>'2026-03-03','to'=>'2026-03-01'],['status'=>'foo'],['q'=>['x']],['q'=>str_repeat('x',121)]] as $invalid) { try { CourseSearch::filters($invalid,$today); throw new RuntimeException('Filtre invalide accepté'); } catch (DomainException $ex) {} }
echo "Cours : recherche, dates, statuts, pagination et jokers littéraux OK\n";
