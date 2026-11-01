<?php
declare(strict_types=1);
require __DIR__.'/../app/services/MemberSchedule.php';
class MemberScheduleTestPDO extends PDO {
    public array $queries=[];
    public function prepare(string $sql,array $options=[]): PDOStatement|false { $this->queries[]=$sql; return parent::prepare(str_replace(' FOR UPDATE','',$sql),$options); }
}
$db=new MemberScheduleTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE members (id INTEGER PRIMARY KEY,status TEXT); CREATE TABLE subscriptions (id INTEGER PRIMARY KEY,member_id INTEGER,status TEXT,start_date TEXT,end_date TEXT); CREATE TABLE courses (id INTEGER PRIMARY KEY,status TEXT,starts_at TEXT,ends_at TEXT); CREATE TABLE course_bookings (id INTEGER PRIMARY KEY,course_id INTEGER,member_id INTEGER,status TEXT,created_at TEXT); CREATE TABLE private_sessions (id INTEGER PRIMARY KEY,member_id INTEGER,status TEXT,starts_at TEXT,ends_at TEXT); INSERT INTO members VALUES (1,'active'),(2,'active'),(3,'active'),(4,'active'),(5,'inactive'); INSERT INTO subscriptions VALUES (1,1,'active','2030-01-01','2030-12-31'),(2,2,'active','2030-01-01','2030-12-31'),(3,3,'active','2029-01-01','2029-12-31'),(4,4,'active','2030-01-01','2030-12-31'),(5,5,'active','2030-01-01','2030-12-31'); INSERT INTO courses VALUES (1,'scheduled','2030-05-01 10:00:00','2030-05-01 11:00:00'),(2,'cancelled','2030-05-01 10:00:00','2030-05-01 11:00:00'); INSERT INTO course_bookings VALUES (1,1,1,'confirmed','2029-01-01'),(2,1,2,'waiting','2029-01-02'),(3,1,3,'waiting','2029-01-03'),(4,1,5,'waiting','2029-01-04'),(5,1,4,'waiting','2029-01-05'),(6,2,2,'confirmed','2029-01-06'); INSERT INTO private_sessions VALUES (1,2,'scheduled','2030-05-01 10:30:00','2030-05-01 11:30:00'),(2,4,'cancelled','2030-05-01 10:00:00','2030-05-01 11:00:00');");
$s=new MemberSchedule($db); $start='2030-05-01 10:00:00'; $end='2030-05-01 11:00:00';
try { $s->lockActiveMember(1); throw new RuntimeException('Verrou sans transaction accepté'); } catch (LogicException $ex) {}
$db->beginTransaction();
if (!$s->lockActiveMember(1) || !$s->hasConfirmedCourseConflict(1,$start,$end) || $s->hasConfirmedCourseConflict(1,$end,'2030-05-01 12:00:00') || $s->hasConfirmedCourseConflict(2,$start,$end)) throw new RuntimeException('Détection cours confirmés, statut ou limites incorrecte');
if (!$s->hasPrivateConflict(2,$start,$end) || $s->hasPrivateConflict(4,$start,$end) || $s->hasPrivateConflict(2,'2030-05-01 09:00:00',$start)) throw new RuntimeException('Détection séance incorrecte');
$promoted=$s->promote(1,$start,$end,1); if ($promoted!==[5]) throw new RuntimeException('Promotion sans contrôle ou ordre incorrect');
$db->exec("UPDATE private_sessions SET status='cancelled' WHERE id=1");
if ($s->promote(1,$start,$end,1)!==[2]) throw new RuntimeException('Candidat redevenu éligible non promu');
if (!$s->hasConfirmedCourseConflict(2,$start,$end)) throw new RuntimeException('Promotion non détectée');
$db->rollBack();
if ($db->query('SELECT status FROM course_bookings WHERE id=5')->fetchColumn()!=='waiting') throw new RuntimeException('Rollback incorrect');
foreach (['FROM subscriptions','FROM private_sessions'] as $check) { $found=false; foreach ($db->queries as $q) if (str_contains($q,$check) && str_contains($q,'FOR UPDATE')) $found=true; if (!$found) throw new RuntimeException('Lecture courante manquante : '.$check); }
// Une file de plus de 100 personnes conserve l'ordre même si les premiers candidats sont inéligibles.
$db->exec("INSERT INTO courses VALUES (3,'scheduled','2030-05-01 10:00:00','2030-05-01 11:00:00')");
for ($i=100; $i<=200; $i++) { $db->prepare("INSERT INTO members VALUES (?,'active')")->execute([$i]); $db->prepare("INSERT INTO course_bookings VALUES (?,3,?,'waiting','2029-01-01')")->execute([$i,$i]); }
$db->exec("INSERT INTO subscriptions VALUES (100,200,'active','2030-01-01','2030-12-31')");
$db->beginTransaction(); if ($s->promote(3,$start,$end,1)!==[200]) throw new RuntimeException('Parcours de file par lots incorrect'); $db->rollBack();
echo "Planning membre : conflits, statuts, attente, limites et rollback OK\n";
