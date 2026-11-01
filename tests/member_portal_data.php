<?php
declare(strict_types=1);
require __DIR__.'/../app/services/MemberPortalData.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE members(id INTEGER PRIMARY KEY,first_name TEXT,last_name TEXT,email TEXT,phone TEXT,governorate TEXT,birth_date TEXT,card_number TEXT,status TEXT); CREATE TABLE attendance(id INTEGER,member_id INTEGER,checked_in_at TEXT,result TEXT); CREATE TABLE plans(id INTEGER,name TEXT); CREATE TABLE subscriptions(id INTEGER,member_id INTEGER,plan_id INTEGER,start_date TEXT,end_date TEXT,status TEXT); CREATE TABLE fitness_measurements(id INTEGER,member_id INTEGER,measured_on TEXT,weight_kg REAL,height_cm REAL,waist_cm REAL,notes TEXT); CREATE TABLE fitness_programs(id INTEGER,member_id INTEGER,title TEXT,objective TEXT,content TEXT,status TEXT,created_at TEXT); CREATE TABLE rooms(id INTEGER,name TEXT); CREATE TABLE courses(id INTEGER,title TEXT,starts_at TEXT,ends_at TEXT,capacity INTEGER,room_id INTEGER,status TEXT); CREATE TABLE course_bookings(id INTEGER,course_id INTEGER,member_id INTEGER,status TEXT);");
$db->exec("INSERT INTO members VALUES(1,'Ali','Un','un@example.tn',NULL,NULL,NULL,'UN','active'),(2,'Sara','Deux','deux@example.tn',NULL,NULL,NULL,'DEUX','active'),(3,'X','X','x@example.tn',NULL,NULL,NULL,'X','inactive'); INSERT INTO plans VALUES(1,'Mensuel'); INSERT INTO subscriptions VALUES(1,1,1,'2026-01-01','2026-02-28','active'),(2,2,1,'2026-01-01','2026-02-28','active'); INSERT INTO attendance VALUES(1,1,'2026-02-01 09:00:00','accepted'),(2,1,'2026-02-02 09:00:00','refused'),(3,2,'2026-02-02 09:00:00','accepted'),(4,1,'2026-01-01 09:00:00','accepted'); INSERT INTO fitness_measurements VALUES(1,1,'2026-01-01',75,175,NULL,'personnel un'),(2,2,'2026-01-01',60,160,NULL,'secret deux'); INSERT INTO fitness_programs VALUES(1,1,'Programme un','objectif','contenu','active','2026-01-01'),(2,2,'Programme deux','objectif','secret','active','2026-01-01'); INSERT INTO rooms VALUES(1,'Studio'); INSERT INTO courses VALUES(1,'Yoga','2026-02-02 10:00:00','2026-02-02 11:00:00',2,1,'scheduled'),(2,'Passé','2026-01-01 10:00:00','2026-01-01 11:00:00',2,1,'scheduled'),(3,'Annulé','2026-02-03 10:00:00','2026-02-03 11:00:00',2,1,'cancelled'); INSERT INTO course_bookings VALUES(1,1,1,'confirmed'),(2,1,2,'confirmed');");
$service=new MemberPortalData($db); $data=$service->get(1,new DateTimeImmutable('2026-02-01 10:00:00'));
if ($data['visits']!==2 || $data['monthVisits']!==1 || count($data['attendance'])!==3 || count($data['subscriptions'])!==1 || count($data['bookings'])!==1 || count($data['courses'])!==1 || (int)$data['courses'][0]['booked']!==2 || $data['courses'][0]['own_status']!=='confirmed' || isset($data['measurements'][0]['notes']) || $data['programs'][0]['title']!=='Programme un' || str_contains(json_encode($data),'secret')) throw new RuntimeException('Données incorrectes ou exposées');
try { $service->get(3); throw new RuntimeException('Compte inactif accepté'); } catch (DomainException $e) {}
echo "Espace membre : isolation et lecture des données personnelles OK\n";
function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function csrf(): string { return 'csrf-test'; }
function gym_name(): string { return 'Club <Test>'; }
function gym_brand_markup(): string { return e(gym_name()); }
$data['member']['first_name']='<script>alert(1)</script>';
$data['programs'][0]['content']='<img src=x onerror=alert(1)>';
$card=['qr_token'=>str_repeat('a',64)]; $section='accueil';
ob_start(); require __DIR__.'/../app/views/member_portal.php'; $html=ob_get_clean();
if (str_contains($html,'<script>alert(1)</script>') || !str_contains($html,'&lt;script&gt;alert(1)&lt;/script&gt;') || !str_contains($html,'PF1:'.str_repeat('a',64)) || !str_contains($html,'name="action" value="card_mail"')) throw new RuntimeException('Accueil ou QR non sécurisé');
$section='programmes'; ob_start(); require __DIR__.'/../app/views/member_portal.php'; $html=ob_get_clean();
if (str_contains($html,'<img src=x onerror=alert(1)>') || !str_contains($html,'&lt;img src=x onerror=alert(1)&gt;')) throw new RuntimeException('Programme non échappé');
$section='cours'; ob_start(); require __DIR__.'/../app/views/member_portal.php'; $html=ob_get_clean();
if (!str_contains($html,'Inscription confirmée') || str_contains($html,'name="course_id"')) throw new RuntimeException('État de réservation incorrect');
$db->exec("UPDATE course_bookings SET status='cancelled' WHERE member_id=1"); $data=$service->get(1,new DateTimeImmutable('2026-02-01 10:00:00'));
ob_start(); require __DIR__.'/../app/views/member_portal.php'; $html=ob_get_clean();
if (!str_contains($html,'S’inscrire') || !str_contains($html,'name="csrf"') || !str_contains($html,'name="course_id"')) throw new RuntimeException('Formulaire d’inscription absent');
$section='mesures'; ob_start(); require __DIR__.'/../app/views/member_portal.php'; $html=ob_get_clean(); if (!str_contains($html,'name="action" value="measurement_update"') || !str_contains($html,'name="current_password"')) throw new RuntimeException('Mesures non modifiables');
require_once __DIR__.'/../app/services/MemberPortalProfile.php'; $profile=['first_name'=>'A <B>','last_name'=>'Test','email'=>'a@example.tn','phone'=>'','governorate'=>'','birth_date'=>'','version'=>'test'];
$section='profil'; ob_start(); require __DIR__.'/../app/views/member_portal.php'; $html=ob_get_clean(); if (!str_contains($html,'A &lt;B&gt;') || !str_contains($html,'name="action" value="profile_update"') || !str_contains($html,'readonly')) throw new RuntimeException('Profil ou échappement incorrect');
for ($i=10;$i<35;$i++) $db->prepare('INSERT INTO fitness_measurements VALUES(?,1,?,?,?,?,?)')->execute([$i,'2025-01-01',80,180,null,'interne']);
$second=$service->get(1,new DateTimeImmutable('2026-02-01 10:00:00'),2);
if ($second['measureTotal']!==26 || $second['measurePages']!==2 || $second['measurePage']!==2 || count($second['measurements'])!==6 || isset($second['measurements'][0]['notes'])) throw new RuntimeException('Pagination des mesures incorrecte');
if ($service->get(1,new DateTimeImmutable('2026-02-01'),999)['measurePage']!==2) throw new RuntimeException('Page invalide non bornée');
echo "Espace membre : rendu échappé et actions de réservation OK\n";
