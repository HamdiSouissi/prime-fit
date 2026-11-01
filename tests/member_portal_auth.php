<?php
declare(strict_types=1);
require __DIR__.'/../app/services/MemberPortalAuth.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE members(id INTEGER PRIMARY KEY,email TEXT,status TEXT); CREATE TABLE member_portal_accounts(member_id INTEGER PRIMARY KEY,email TEXT,password_hash TEXT)');
$password='Une phrase secrète 123!'; $hash=password_hash($password,PASSWORD_DEFAULT);
$db->prepare('INSERT INTO members VALUES(?,?,?)')->execute([1,'m@example.tn','active']);
$db->prepare('INSERT INTO member_portal_accounts VALUES(?,?,?)')->execute([1,'m@example.tn',$hash]);
$auth=new MemberPortalAuth($db); $session=$auth->authenticate('m@example.tn',$password);
if (!$session || !$auth->validSession($session) || $auth->authenticate('m@example.tn','incorrect') || $auth->authenticate('inconnu@example.tn',$password) || $auth->validSession(['member_id'=>'1','fingerprint'=>$session['fingerprint']])) throw new RuntimeException('Authentification incorrecte');
$db->exec("UPDATE members SET status='inactive' WHERE id=1"); if ($auth->validSession($session) || $auth->authenticate('m@example.tn',$password)) throw new RuntimeException('Compte inactif accepté');
$db->exec("UPDATE members SET status='active',email='autre@example.tn' WHERE id=1"); if ($auth->validSession($session)) throw new RuntimeException('Ancienne adresse acceptée');
$db->exec("UPDATE members SET email='m@example.tn' WHERE id=1"); $db->prepare('UPDATE member_portal_accounts SET password_hash=?')->execute([password_hash('Nouveau mot de passe 456!',PASSWORD_DEFAULT)]); if ($auth->validSession($session)) throw new RuntimeException('Ancienne session acceptée');
echo "Espace membre : authentification et révocation des sessions OK\n";
