<?php
declare(strict_types=1);
class CoachControllerPDO extends PDO { public function prepare(string $sql,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$sql),$options); } }
$db=new CoachControllerPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY,name TEXT,email TEXT,role TEXT,is_active INTEGER); CREATE TABLE coach_profiles (user_id INTEGER PRIMARY KEY,phone TEXT,specialties TEXT,commission_rate TEXT); CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT); INSERT INTO users VALUES (1,'<Ali>','coach@example.tn','Coach',1); INSERT INTO coach_profiles VALUES (1,'+216','<Fitness>','10.00')");
function db(): PDO { global $db; return $db; } function verify_csrf(): void {} function csrf(): string { return 'csrf'; } function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); } function gym_name(): string { return 'Salle'; } function gym_brand_markup(): string { return 'Salle'; }
class CoachRedirect extends Exception {} function redirect(string $route): void { throw new CoachRedirect($route); }
require __DIR__.'/../app/controllers/CoachProfileController.php';
$_SESSION=['user'=>['id'=>2,'role'=>'Gérant','name'=>'Gérant']]; $_GET=['id'=>'1']; $_SERVER['REQUEST_METHOD']='GET';
ob_start(); (new CoachProfileController())->index(); $html=ob_get_clean();
foreach (['&lt;Ali&gt;','&lt;Fitness&gt;','name="version"','name="csrf"','10.00','Celles déjà créées'] as $needle) if (!str_contains($html,$needle)) throw new RuntimeException('Formulaire incomplet : '.$needle);
if (str_contains($html,'<Fitness>') || str_contains($html,'Gérer le compte et le mot de passe')) throw new RuntimeException('Protection incorrecte');
$service=new CoachProfileService($db,2,''); $version=CoachProfileService::version($service->get(1)); $_SERVER['REQUEST_METHOD']='POST'; $_POST=['version'=>$version,'phone'=>'','specialties'=>'Pilates','commission_rate'=>'35'];
try { (new CoachProfileController())->index(); throw new RuntimeException('Modification non enregistrée'); } catch (CoachRedirect $ex) { if ($ex->getMessage()!=='coach_profile&id=1') throw $ex; }
if ($service->get(1)['commission_rate']!=='35.00') throw new RuntimeException('Commission non modifiée');
ob_start(); (new CoachProfileController())->index(); $html=ob_get_clean();
if (!str_contains($html,'a changé depuis') || !str_contains($html,'35.00')) throw new RuntimeException('Version obsolète non refusée');
$_SESSION['user']['role']='Admin'; $_SERVER['REQUEST_METHOD']='GET'; ob_start(); (new CoachProfileController())->index(); $html=ob_get_clean(); if (!str_contains($html,'Gérer le compte et le mot de passe')) throw new RuntimeException('Lien admin manquant');
echo "Profil coach : contrôleur, rendu et version obsolète OK\n";
