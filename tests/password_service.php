<?php
declare(strict_types=1);
require __DIR__.'/../app/services/PasswordService.php';
class PasswordTestPDO extends PDO {
    public function prepare(string $query,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); }
}
$db=new PasswordTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY,password_hash TEXT,is_active INTEGER); CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT)');
$old='Ancien mot de passe 123!'; $new='Nouvelle phrase secrète 456!';
$originalHash=password_hash($old,PASSWORD_DEFAULT); $db->prepare('INSERT INTO users VALUES (?,?,1)')->execute([1,$originalHash]);
$service=new PasswordService($db);
foreach ([['incorrect',$new,$new],[$old,'court','court'],[$old,$new,'autre confirmation'],[$old,$old,$old],[$old,str_repeat('a',73),str_repeat('a',73)],[$old,"une phrase\0nulle", "une phrase\0nulle"]] as $args) {
    try { $service->change(1,$args[0],$args[1],$args[2],'127.0.0.1'); throw new RuntimeException('Changement invalide accepté'); } catch (DomainException $ex) {}
    if ($db->query('SELECT password_hash FROM users WHERE id=1')->fetchColumn()!==$originalHash) throw new RuntimeException('Mot de passe modifié en cas d’échec');
}
$fingerprint=$service->change(1,$old,$new,$new,'127.0.0.1');
$stored=$db->query('SELECT password_hash FROM users WHERE id=1')->fetchColumn();
if (!password_verify($new,$stored) || password_verify($old,$stored) || !hash_equals(hash('sha256',$stored),$fingerprint) || hash_equals(hash('sha256',$originalHash),$fingerprint)) throw new RuntimeException('Nouveau mot de passe ou révocation incorrects');
if ((int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn()!==1) throw new RuntimeException('Audit incorrect');
$db->exec('DROP TABLE activity_logs');
try { $service->change(1,$new,'Troisième mot de passe 789!','Troisième mot de passe 789!','127.0.0.1'); throw new RuntimeException('Échec audit ignoré'); } catch (PDOException $ex) {}
if ($db->query('SELECT password_hash FROM users WHERE id=1')->fetchColumn()!==$stored) throw new RuntimeException('Rollback incorrect');
$db->exec('UPDATE users SET is_active=0 WHERE id=1');
try { $service->change(1,$new,$old,$old,'127.0.0.1'); throw new RuntimeException('Compte inactif accepté'); } catch (DomainException $ex) {}
echo "Mot de passe : validation, changement, révocation et rollback OK\n";
