<?php
declare(strict_types=1);
// Test autonome : php tests/member_card_number.php (extensions PDO SQLite et mbstring).
class TestRedirect extends Exception {}
function verify_csrf(): void {}
function redirect(string $route): void { throw new TestRedirect($route); }
function db(): PDO { global $pdo; return $pdo; }
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE members (id INTEGER PRIMARY KEY AUTOINCREMENT, first_name TEXT, last_name TEXT, phone TEXT, email TEXT, card_number TEXT UNIQUE, governorate TEXT, birth_date TEXT, status TEXT, qr_token TEXT UNIQUE)');
$pdo->exec('CREATE TABLE activity_logs (user_id INTEGER, action TEXT, ip_address TEXT)');
require __DIR__.'/../app/controllers/ManagementController.php';
$_SESSION=['user'=>['id'=>1,'role'=>'Admin']]; $_SERVER['REQUEST_METHOD']='POST'; $_SERVER['REMOTE_ADDR']='127.0.0.1';
$controller=new ManagementController();
for ($i=0; $i<2; $i++) {
    $_POST=['id'=>'0','first_name'=>'Membre','last_name'=>(string)$i,'status'=>'active','card_number'=>'USURPÉ'];
    try { $controller->members(); throw new RuntimeException('Redirection attendue'); } catch (TestRedirect $redirect) {
        if ($redirect->getMessage() !== 'member_card&id='.($i+1)) throw new RuntimeException('Redirection incorrecte');
    }
}
$members=$pdo->query('SELECT * FROM members ORDER BY id')->fetchAll();
if (count($members)!==2 || !preg_match('/^PF-[0-9]{16}$/D',$members[0]['card_number']) || $members[0]['card_number']===$members[1]['card_number'] || $members[0]['card_number']==='USURPÉ' || !preg_match('/^[0-9a-f]{64}$/D',$members[0]['qr_token'])) throw new RuntimeException('Création ou unicité incorrecte');
$_POST=['id'=>'1','first_name'=>'Modifié','last_name'=>'Membre','status'=>'active','card_number'=>'CARTE-FORGÉE'];
try { $controller->members(); throw new RuntimeException('Redirection attendue'); } catch (TestRedirect $redirect) {
    if ($redirect->getMessage() !== 'members') throw new RuntimeException('Redirection incorrecte');
}
$updated=$pdo->query('SELECT * FROM members WHERE id=1')->fetch();
if ($updated['card_number']!==$members[0]['card_number'] || $updated['qr_token']!==$members[0]['qr_token'] || $updated['first_name']!=='Modifié') throw new RuntimeException('Modification non autorisée du numéro ou du QR');
echo "Création et modification des numéros de carte : OK\n";
// Vérification du diagnostic sans divulguer le message SQL à l'utilisateur.
$missing = new PDOException('Unknown column');
$missing->errorInfo = ['42S22', 1054, "Unknown column 'qr_token' in 'field list'"];
$other = new PDOException('Unknown column');
$other->errorInfo = ['42S22', 1054, "Unknown column 'other' in 'field list'"];
if (!ManagementController::missingQrColumn($missing) || ManagementController::missingQrColumn($other)) throw new RuntimeException('Diagnostic de migration incorrect');
echo "Diagnostic de migration QR : OK\n";
