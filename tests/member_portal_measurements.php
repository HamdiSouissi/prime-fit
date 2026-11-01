<?php
declare(strict_types=1);
require __DIR__.'/../app/services/MemberPortalMeasurements.php';
class MeasurePDO extends PDO { public function prepare(string $query,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); } }
$db=new MeasurePDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE members(id INTEGER PRIMARY KEY,email TEXT,status TEXT); CREATE TABLE member_portal_accounts(member_id INTEGER,email TEXT,password_hash TEXT); CREATE TABLE fitness_measurements(id INTEGER PRIMARY KEY,member_id INTEGER,measured_on TEXT,weight_kg TEXT,height_cm TEXT,waist_cm TEXT,notes TEXT,created_by INTEGER); CREATE TABLE member_measurement_revisions(measurement_id INTEGER,member_id INTEGER,previous_date TEXT,previous_weight TEXT,previous_height TEXT,previous_waist TEXT,new_date TEXT,new_weight TEXT,new_height TEXT,new_waist TEXT); CREATE TABLE activity_logs(user_id INTEGER,action TEXT,ip_address TEXT)');
$hash=password_hash('Mon mot de passe 123!',PASSWORD_DEFAULT); $db->exec("INSERT INTO members VALUES(1,'one@example.tn','active'),(2,'two@example.tn','active'); INSERT INTO fitness_measurements VALUES(1,1,'2026-01-01','80.00','180.00','90.00','note confidentielle coach',5),(2,2,'2026-01-01','60.00','160.00',NULL,'autre note',5)");
$db->prepare('INSERT INTO member_portal_accounts VALUES(1,?,?)')->execute(['one@example.tn',$hash]);
$session=['member_id'=>1,'fingerprint'=>hash('sha256',$hash)]; $row=$db->query('SELECT * FROM fitness_measurements WHERE id=1')->fetch();
$input=['measurement_id'=>'1','version'=>MemberPortalMeasurements::version($row),'measured_on'=>'2026-02-01','weight_kg'=>'78,5','height_cm'=>'180','waist_cm'=>'','current_password'=>'Mon mot de passe 123!']; $svc=new MemberPortalMeasurements($db); $now=new DateTimeImmutable('2026-03-01');
$reject=static function(callable $f): void { try { $f(); } catch (DomainException $e) { return; } throw new RuntimeException('Modification invalide acceptée'); };
foreach ([['measurement_id'=>'2'],['current_password'=>'wrong'],['weight_kg'=>'999'],['height_cm'=>'0'],['waist_cm'=>'x'],['measured_on'=>'2026-02-30'],['measured_on'=>'2030-01-01'],['version'=>str_repeat('0',64)],['weight_kg'=>[]]] as $change) $reject(fn()=>$svc->update($session,array_replace($input,$change),'',$now));
$svc->update($session,$input,'127.0.0.1',$now); $after=$db->query('SELECT * FROM fitness_measurements WHERE id=1')->fetch();
if ($after['weight_kg']!=='78.5' || $after['waist_cm']!==null || $after['notes']!=='note confidentielle coach' || $after['created_by']!==5 || (int)$db->query('SELECT COUNT(*) FROM member_measurement_revisions')->fetchColumn()!==1 || (int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn()!==1) throw new RuntimeException('Modification incorrecte');
$reject(fn()=>$svc->update($session,$input,'',$now));
$db->exec('DROP TABLE activity_logs'); $next=array_replace($input,['version'=>MemberPortalMeasurements::version($after),'weight_kg'=>'79']);
try { $svc->update($session,$next,'',$now); throw new RuntimeException('Audit manquant accepté'); } catch (PDOException $e) {}
if ($db->query('SELECT weight_kg FROM fitness_measurements WHERE id=1')->fetchColumn()!=='78.5' || (int)$db->query('SELECT COUNT(*) FROM member_measurement_revisions')->fetchColumn()!==1) throw new RuntimeException('Rollback incomplet');
echo "Espace membre : mesures, contrôle d’accès, traçabilité et rollback OK\n";
