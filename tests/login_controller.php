<?php
declare(strict_types=1);
class AuthTestPDO extends PDO {
    public function prepare(string $query,array $options=[]): PDOStatement|false { return parent::prepare(str_replace([' ON DUPLICATE KEY UPDATE bucket_key=bucket_key',' FOR UPDATE'],[' ON CONFLICT(bucket_key) DO NOTHING',''],$query),$options); }
}
$db=new AuthTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY,name TEXT,email TEXT,password_hash TEXT,role TEXT,is_active INTEGER); CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT); CREATE TABLE auth_rate_limits (bucket_key TEXT PRIMARY KEY,window_started INTEGER NOT NULL,attempts INTEGER NOT NULL)');
$db->prepare('INSERT INTO users VALUES (?,?,?,?,?,?)')->execute([1,'Admin','admin@example.tn',password_hash('test password 123',PASSWORD_BCRYPT,['cost'=>4]),'Admin',1]);
function db(): PDO { global $db; return $db; }
function verify_csrf(): void {} function csrf(): string { return 'csrf'; }
function e($s): string { return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function gym_name(): string { return 'Salle'; } function gym_brand_markup(): string { return 'Salle'; }
class Redirected extends Exception {}
function redirect(string $route): void { throw new Redirected($route); }
require __DIR__.'/../app/controllers/AuthController.php';
session_start(); $_SERVER['REQUEST_METHOD']='POST'; $_SERVER['REMOTE_ADDR']='192.0.2.1';
$_POST=['email'=>'admin@example.tn','password'=>'test password 123'];
try { ob_start(); (new AuthController())->login(); throw new RuntimeException('Connexion correcte refusée'); } catch (Redirected $ex) { ob_end_clean(); if ($ex->getMessage()!=='dashbord') throw new RuntimeException('Redirection après connexion incorrecte'); }
if (($_SESSION['user']['id'] ?? null)!=1 || isset($_SESSION['user']['password_hash']) || $db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn()!=1) throw new RuntimeException('Connexion ou audit incorrect');
// Changer de session ne remet pas les compteurs à zéro.
for ($i=0;$i<4;$i++) { $_SESSION=[]; $_POST=['email'=>'admin@example.tn','password'=>'incorrect']; ob_start(); (new AuthController())->login(); $html=ob_get_clean(); if (!str_contains($html,'Identifiants incorrects.')) throw new RuntimeException('Erreur générique absente'); }
$_SESSION=[]; $_POST=['email'=>'admin@example.tn','password'=>'test password 123']; ob_start(); (new AuthController())->login(); $html=ob_get_clean();
if (!str_contains($html,'Trop de tentatives') || isset($_SESSION['user']) || http_response_code()!==429) throw new RuntimeException('Limite contournée par changement de session');
$_SESSION=[]; $_SERVER['REMOTE_ADDR']='192.0.2.2'; $_POST=['email'=>['bad'],'password'=>['bad']]; ob_start(); (new AuthController())->login(); $html=ob_get_clean();
if (!str_contains($html,'Identifiants incorrects.')) throw new RuntimeException('Entrée invalide mal traitée');
$db->exec('DROP TABLE auth_rate_limits'); $_POST=['email'=>'admin@example.tn','password'=>'test password 123']; ob_start(); (new AuthController())->login(); $html=ob_get_clean();
if (isset($_SESSION['user']) || http_response_code()!==503 || !str_contains($html,'temporairement indisponible')) throw new RuntimeException('Échec non sécurisé sans table');
session_destroy(); echo "Connexion : succès, blocage entre sessions, entrées invalides et échec fermé OK\n";
