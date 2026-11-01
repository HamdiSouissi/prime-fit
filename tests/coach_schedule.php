<?php
declare(strict_types=1);
// Vérifie les règles de chevauchement en SQLite ; les verrous concurrents exigent MySQL.
require __DIR__.'/../app/services/CoachSchedule.php';
class CoachScheduleTestPDO extends PDO {
    public function prepare(string $query,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); }
}
$db=new CoachScheduleTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY,role TEXT,is_active INTEGER); CREATE TABLE courses (id INTEGER PRIMARY KEY,coach_id INTEGER,status TEXT,starts_at TEXT,ends_at TEXT); CREATE TABLE private_sessions (id INTEGER PRIMARY KEY,coach_id INTEGER,status TEXT,starts_at TEXT,ends_at TEXT); INSERT INTO users VALUES (1,'Coach',1),(2,'Coach',1),(3,'Coach',0),(4,'Admin',1); INSERT INTO courses VALUES (1,1,'scheduled','2026-05-01 10:00:00','2026-05-01 11:00:00'),(2,1,'cancelled','2026-05-01 14:00:00','2026-05-01 15:00:00'); INSERT INTO private_sessions VALUES (1,1,'scheduled','2026-05-01 12:00:00','2026-05-01 13:00:00'),(2,1,'cancelled','2026-05-01 15:00:00','2026-05-01 16:00:00');");
$schedule=new CoachSchedule($db);
try { $schedule->lockCoach(1); throw new RuntimeException('Verrou sans transaction accepté'); } catch (LogicException $ex) {}
$db->beginTransaction(); $schedule->lockCoach(1);
foreach ([['2026-05-01 09:30:00','2026-05-01 10:01:00'],['2026-05-01 10:00:00','2026-05-01 11:00:00'],['2026-05-01 10:30:00','2026-05-01 12:30:00'],['2026-05-01 12:30:00','2026-05-01 13:30:00']] as [$start,$end]) {
    try { $schedule->assertAvailable(1,$start,$end); throw new RuntimeException('Chevauchement accepté'); } catch (DomainException $ex) {}
}
foreach ([['2026-05-01 09:00:00','2026-05-01 10:00:00'],['2026-05-01 11:00:00','2026-05-01 12:00:00'],['2026-05-01 13:00:00','2026-05-01 16:00:00']] as [$start,$end]) $schedule->assertAvailable(1,$start,$end);
$schedule->lockCoach(2); $schedule->assertAvailable(2,'2026-05-01 10:00:00','2026-05-01 13:00:00');
foreach ([3,4,999] as $id) { try { $schedule->lockCoach($id); throw new RuntimeException('Coach invalide accepté'); } catch (DomainException $ex) {} }
$db->rollBack();
try { $schedule->assertAvailable(1,'2026-05-01 10:00:00','2026-05-01 11:00:00'); throw new RuntimeException('Contrôle sans transaction accepté'); } catch (LogicException $ex) {}
echo "Planning coach : cours, séances, limites adjacentes et statuts OK\n";
