<?php
declare(strict_types=1);
require __DIR__.'/../app/services/CoachProfileService.php';
class CoachProfilePDO extends PDO { public function prepare(string $sql,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$sql),$options); } }
$db=new CoachProfilePDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY,name TEXT,email TEXT,role TEXT,is_active INTEGER); CREATE TABLE coach_profiles (user_id INTEGER PRIMARY KEY,phone TEXT,specialties TEXT,commission_rate TEXT); CREATE TABLE private_sessions (id INTEGER PRIMARY KEY,coach_id INTEGER,commission_rate TEXT); CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT); INSERT INTO users VALUES (1,'Coach <Ali>','coach@example.tn','Coach',1),(2,'Autre','a@example.tn','Admin',1); INSERT INTO coach_profiles VALUES (1,'+216','Fitness','10.00'); INSERT INTO private_sessions VALUES (1,1,'10.00')");
$service=new CoachProfileService($db,2,'127.0.0.1'); $coach=$service->get(1); $version=CoachProfileService::version($coach); $input=['phone'=>'  +216 12345678  ','specialties'=>' Pilates ','commission_rate'=>'25,5'];
$service->update(1,$version,$input); $coach=$service->get(1);
if ($coach['phone']!=='+216 12345678' || $coach['specialties']!=='Pilates' || $coach['commission_rate']!=='25.50' || $db->query('SELECT commission_rate FROM private_sessions')->fetchColumn()!=='10.00') throw new RuntimeException('Profil ou ancienne séance incorrect');
if ((int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn()!==1 || $db->inTransaction()) throw new RuntimeException('Audit ou transaction incorrect');
try { $service->update(1,$version,$input); throw new RuntimeException('Version obsolète acceptée'); } catch (DomainException $ex) {}
$version=CoachProfileService::version($coach);
foreach (['100.01','-1','10.999','1e2',''] as $rate) { $invalid=$input; $invalid['commission_rate']=$rate; try { $service->update(1,$version,$invalid); throw new RuntimeException('Commission invalide acceptée'); } catch (DomainException $ex) {} }
$valid=$input; $valid['phone']=''; $valid['commission_rate']='100'; $service->update(1,$version,$valid); $coach=$service->get(1);
if ($coach['phone']!==null || $coach['commission_rate']!=='100.00') throw new RuntimeException('Valeurs limites incorrectes');
$version=CoachProfileService::version($coach); $db->exec("UPDATE users SET email='nouveau@example.tn' WHERE id=1");
try { $service->update(1,$version,$input); throw new RuntimeException('Compte modifié accepté'); } catch (DomainException $ex) {}
$version=CoachProfileService::version($service->get(1)); $db->exec('DROP TABLE activity_logs');
try { $service->update(1,$version,$input); throw new RuntimeException('Erreur audit ignorée'); } catch (PDOException $ex) {}
if ($db->inTransaction() || $service->get(1)['commission_rate']!=='100.00') throw new RuntimeException('Rollback incorrect');
if ($service->get(2)!==null) throw new RuntimeException('Compte non coach affiché');
echo "Profil coach : validation, version, commission historique et rollback OK\n";
