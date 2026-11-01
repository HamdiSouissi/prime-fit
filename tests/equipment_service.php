<?php
declare(strict_types=1);
require __DIR__.'/../app/services/EquipmentService.php';
class EquipmentTestPDO extends PDO { public function prepare(string $query,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); } }
$db=new EquipmentTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE equipment (id INTEGER PRIMARY KEY,name TEXT,reference_code TEXT UNIQUE,location TEXT,purchased_on TEXT,status TEXT,next_maintenance TEXT); CREATE TABLE equipment_interventions (id INTEGER PRIMARY KEY AUTOINCREMENT,equipment_id INTEGER,intervention_date TEXT,type TEXT,description TEXT,cost TEXT,created_by INTEGER); CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT); INSERT INTO equipment VALUES (1,'Tapis','T-1','Salle',NULL,'operational',NULL),(2,'Vélo','V-1','Salle',NULL,'operational',NULL)");
$service=new EquipmentService($db,1,'127.0.0.1');
$service->edit(1,['name'=>'Tapis roulant','reference_code'=>'T-1','location'=>'Zone A','purchased_on'=>'2024-01-01']);
$p=$db->query('SELECT * FROM equipment WHERE id=1')->fetch(); if ($p['name']!=='Tapis roulant' || $p['status']!=='operational') throw new RuntimeException('Fiche incorrecte');
try { $service->edit(1,['name'=>'Autre','reference_code'=>'V-1','location'=>'','purchased_on'=>'']); throw new RuntimeException('Référence dupliquée acceptée'); } catch (PDOException $ex) {}
$entry=['intervention_date'=>'2025-01-15','type'=>'breakdown','description'=>'Panne','status'=>'out_of_service','next_maintenance'=>'2025-01-20','cost'=>'12,500','expected_status'=>'operational','expected_next'=>'','expected_intervention'=>'0'];
$service->intervention(1,$entry);
$p=$db->query('SELECT * FROM equipment WHERE id=1')->fetch(); $history=$db->query('SELECT * FROM equipment_interventions')->fetch();
if ($p['status']!=='out_of_service' || $p['next_maintenance']!=='2025-01-20' || $history['cost']!=='12.500') throw new RuntimeException('Intervention incorrecte');
try { $service->intervention(1,$entry); throw new RuntimeException('Fiche obsolète acceptée'); } catch (DomainException $ex) {}
$next=$entry; $next['expected_status']='out_of_service'; $next['expected_next']='2025-01-20'; $next['expected_intervention']='1'; $next['intervention_date']='2025-01-14';
try { $service->intervention(1,$next); throw new RuntimeException('Date antérieure acceptée'); } catch (DomainException $ex) {}
$next['intervention_date']='2025-01-16'; $next['type']='repair'; $next['status']='operational'; $next['next_maintenance']=''; $next['cost']=''; $service->intervention(1,$next);
if ((int)$db->query('SELECT COUNT(*) FROM equipment_interventions')->fetchColumn()!==2 || $db->query('SELECT status FROM equipment WHERE id=1')->fetchColumn()!=='operational') throw new RuntimeException('Réparation incorrecte');
$db->exec('DROP TABLE activity_logs'); $next['expected_intervention']='2'; $next['expected_status']='operational'; $next['expected_next']='';
try { $service->intervention(1,$next); throw new RuntimeException('Erreur audit ignorée'); } catch (PDOException $ex) {}
if ((int)$db->query('SELECT COUNT(*) FROM equipment_interventions')->fetchColumn()!==2) throw new RuntimeException('Rollback incorrect');
echo "Équipements : édition, intervention, conflit, chronologie et rollback OK\n";
