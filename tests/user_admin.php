<?php
declare(strict_types=1);
require __DIR__.'/../app/services/UserAdminService.php';
class UserAdminTestPDO extends PDO { public function prepare(string $query,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); } }
$db=new UserAdminTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,email TEXT UNIQUE,password_hash TEXT,role TEXT,is_active INTEGER); CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT)');
$password='Ancienne phrase secrète 123!'; $stmt=$db->prepare('INSERT INTO users (id,name,email,password_hash,role,is_active) VALUES (?,?,?,?,?,?)');
foreach ([[1,'Admin 1','a@example.com','Admin',1],[2,'Admin 2','b@example.com','Admin',1],[3,'Coach','c@example.com','Coach',1],[4,'Agent','d@example.com','Réceptionniste',1]] as [$id,$name,$email,$role,$active]) $stmt->execute([$id,$name,$email,password_hash($password,PASSWORD_DEFAULT),$role,$active]);
$get=static function(int $id) use ($db) { $stmt=$db->prepare('SELECT * FROM users WHERE id=?'); $stmt->execute([$id]); return $stmt->fetch(); };
$service=new UserAdminService($db,1,'127.0.0.1'); $other=new UserAdminService($db,4,'127.0.0.1');
$input=['name'=>'Nouvel Agent','email'=>'new@example.com','role'=>'Gérant','password'=>'Mot de passe initial solide!','confirmation'=>'Mot de passe initial solide!'];
try { $other->create($input,$password); throw new RuntimeException('Création par un non-admin'); } catch (DomainException $ex) {}
try { $service->create($input,'incorrect'); throw new RuntimeException('Réauthentification contournée'); } catch (DomainException $ex) {}
$id=$service->create($input,$password); if ($get($id)['role']!=='Gérant' || !password_verify($input['password'],$get($id)['password_hash'])) throw new RuntimeException('Création invalide');
$version=UserAdminService::version($get(2));
$service->edit(2,$version,['name'=>'Admin 2','email'=>'b@example.com','role'=>'Comptable','is_active'=>'1'],$password);
try { $service->edit(2,$version,['name'=>'Ancien','email'=>'b@example.com','role'=>'Admin','is_active'=>'1'],$password); throw new RuntimeException('Version obsolète acceptée'); } catch (DomainException $ex) {}
try { $service->edit(1,UserAdminService::version($get(1)),['name'=>'Admin 1','email'=>'a@example.com','role'=>'Gérant','is_active'=>'1'],$password); throw new RuntimeException('Auto-rétrogradation acceptée'); } catch (DomainException $ex) {}
try { $service->edit(1,UserAdminService::version($get(1)),['name'=>'Admin 1','email'=>'a@example.com','role'=>'Admin','is_active'=>'0'],$password); throw new RuntimeException('Auto-désactivation acceptée'); } catch (DomainException $ex) {}
try { $service->edit(3,UserAdminService::version($get(3)),['name'=>'Coach','email'=>'c@example.com','role'=>'Gérant','is_active'=>'1'],$password); throw new RuntimeException('Changement de rôle coach accepté'); } catch (DomainException $ex) {}
$old=$get(3)['password_hash']; $service->reset(3,UserAdminService::version($get(3)),'Nouveau mot de passe coach!','Nouveau mot de passe coach!',$password);
if (!password_verify('Nouveau mot de passe coach!',$get(3)['password_hash']) || hash_equals(hash('sha256',$old),hash('sha256',$get(3)['password_hash']))) throw new RuntimeException('Réinitialisation invalide');
$before=$get(3)['password_hash'];
$service->edit(3,UserAdminService::version($get(3)),['name'=>'Coach','email'=>'c@example.com','role'=>'Coach','is_active'=>'0'],$password);
$disabled=$get(3)['password_hash']; if ($before===$disabled || password_verify('Nouveau mot de passe coach!',$disabled)) throw new RuntimeException('Ancienne session non révoquée à la désactivation');
$service->edit(3,UserAdminService::version($get(3)),['name'=>'Coach','email'=>'c@example.com','role'=>'Coach','is_active'=>'1'],$password);
if ($get(3)['password_hash']===$disabled) throw new RuntimeException('Réactivation sans révocation');
try { $service->reset(1,UserAdminService::version($get(1)),'Nouveau mot de passe solide!','Nouveau mot de passe solide!',$password); throw new RuntimeException('Auto-réinitialisation acceptée'); } catch (DomainException $ex) {}
$db->exec('DROP TABLE activity_logs');
try { $service->edit(4,UserAdminService::version($get(4)),['name'=>'Autre','email'=>'d@example.com','role'=>'Comptable','is_active'=>'1'],$password); throw new RuntimeException('Échec audit ignoré'); } catch (PDOException $ex) {}
if ($get(4)['name']!=='Agent' || $get(4)['role']!=='Réceptionniste') throw new RuntimeException('Rollback incorrect');
echo "Comptes : autorisation, réauthentification, protections, révocation et rollback OK\n";
