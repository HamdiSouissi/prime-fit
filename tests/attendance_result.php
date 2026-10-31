<?php
declare(strict_types=1);
// Exécuter avec PHP 8, PDO SQLite et mbstring. Les requêtes MySQL sont adaptées pour ce test isolé.
class TestRedirect extends Exception {}
class TestPDO extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); }
}
function verify_csrf(): void {}
function redirect(string $route): void { throw new TestRedirect($route); }
function db(): PDO { global $pdo; return $pdo; }
$pdo=new TestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->sqliteCreateFunction('CURDATE',fn()=>date('Y-m-d'));
$pdo->exec("CREATE TABLE members (id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT, card_number TEXT, phone TEXT, status TEXT, qr_token TEXT);
CREATE TABLE plans (id INTEGER PRIMARY KEY, name TEXT);
CREATE TABLE subscriptions (id INTEGER PRIMARY KEY, member_id INTEGER, plan_id INTEGER, status TEXT, start_date TEXT, end_date TEXT);
CREATE TABLE attendance (id INTEGER PRIMARY KEY AUTOINCREMENT, member_id INTEGER, result TEXT, checked_in_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT);");
$token=str_repeat('a',64);
$pdo->prepare('INSERT INTO members VALUES (1,?,?,?,?,?,?)')->execute(['Amine','Ben Salah','PF-123','+216 22 123 456','active',$token]);
$pdo->exec("INSERT INTO plans VALUES (1,'Mensuel')");
$pdo->prepare('INSERT INTO subscriptions VALUES (1,1,1,?,?,?)')->execute(['active',date('Y-m-d',strtotime('-3 days')),date('Y-m-d',strtotime('+3 days'))]);
require __DIR__.'/../app/controllers/AttendanceController.php';
$_SESSION=['user'=>['id'=>1,'role'=>'Réceptionniste']]; $_SERVER=['REQUEST_METHOD'=>'POST','REMOTE_ADDR'=>'127.0.0.1']; $_POST=['card_number'=>'PF1:'.$token];
$controller=new AttendanceController();
$check=static function() use ($controller): array { try { $controller->index(); throw new RuntimeException('Redirection attendue'); } catch (TestRedirect $ex) { if ($ex->getMessage()!=='attendance') throw $ex; return $_SESSION['attendance_result']; } };
$r=$check();
if (!$r['accepted'] || $r['name']!=='Amine Ben Salah' || $r['card']!=='PF-123' || $r['phone']!=='+216 22 123 456' || $r['subscription_status']!=='Valide' || $r['plan']!=='Mensuel' || !$r['checked_in_at']) throw new RuntimeException('Résultat membre actif incorrect');
$pdo->prepare('UPDATE subscriptions SET end_date=? WHERE id=1')->execute([date('Y-m-d',strtotime('-1 day'))]);
$r=$check(); if ($r['accepted'] || $r['subscription_status']!=='Expiré' || $r['plan']!=='Mensuel') throw new RuntimeException('Abonnement expiré incorrect');
$pdo->exec("UPDATE subscriptions SET status='frozen'"); $r=$check(); if ($r['accepted'] || $r['subscription_status']!=='Gelé') throw new RuntimeException('Abonnement gelé incorrect');
$pdo->exec("UPDATE members SET status='blacklisted'"); $r=$check(); if ($r['accepted'] || $r['reason']!=='Membre sur liste noire') throw new RuntimeException('Liste noire incorrecte');
if ((int)$pdo->query('SELECT COUNT(*) FROM attendance')->fetchColumn()!==4) throw new RuntimeException('Journal incorrect');
echo "Résultats du pointage actif, expiré, gelé et blacklisté : OK\n";
