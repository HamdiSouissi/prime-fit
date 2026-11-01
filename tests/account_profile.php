<?php
declare(strict_types=1);
require __DIR__.'/../app/services/AccountProfileService.php';
require __DIR__.'/../app/services/AccountInitials.php';
class AccountTestPDO extends PDO { public function prepare(string $query,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); } }
$db=new AccountTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE users(id INTEGER PRIMARY KEY,name TEXT,email TEXT UNIQUE,phone TEXT,password_hash TEXT,is_active INTEGER); CREATE TABLE activity_logs(user_id INTEGER,action TEXT,ip_address TEXT)');
$password='Phrase secrète de test 123!'; $hash=password_hash($password,PASSWORD_DEFAULT);
$db->prepare('INSERT INTO users VALUES(?,?,?,?,?,1)')->execute([1,'Amine Ben Salah','old@example.tn',null,$hash]);
$db->prepare('INSERT INTO users VALUES(?,?,?,?,?,1)')->execute([2,'Autre','other@example.tn',null,$hash]);
$svc=new AccountProfileService($db); $version=AccountProfileService::version($svc->get(1));
$data=['name'=>'Amine <Salah>','email'=>'new@example.tn','phone'=>'+216 12 345 678','current_password'=>$password,'version'=>$version];
foreach ([['current_password'=>'mauvais'],['email'=>"x@example.tn\r\nBcc: a@b.tn"],['email'=>'other@example.tn'],['phone'=>'<invalid>'],['name'=>''],['version'=>str_repeat('0',64)]] as $changes) {
    try { $svc->update(1,array_replace($data,$changes),'127.0.0.1'); throw new RuntimeException('Modification invalide acceptée'); } catch (DomainException $ex) {}
    if ($svc->get(1)['email']!=='old@example.tn') throw new RuntimeException('Modification partielle');
}
$result=$svc->update(1,$data,'127.0.0.1'); $saved=$svc->get(1);
if ($saved['name']!=='Amine <Salah>' || $saved['phone']!=='+216 12 345 678' || $result['email']!=='new@example.tn' || (int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn()!==1) throw new RuntimeException('Modification ou audit incorrects');
try { $svc->update(1,$data,'127.0.0.1'); throw new RuntimeException('Version périmée acceptée'); } catch (DomainException $ex) {}
$db->exec('DROP TABLE activity_logs'); $data['version']=AccountProfileService::version($saved); $data['name']='Autre nom';
try { $svc->update(1,$data,'127.0.0.1'); throw new RuntimeException('Audit absent accepté'); } catch (PDOException $ex) {}
if ($svc->get(1)['name']!=='Amine <Salah>') throw new RuntimeException('Rollback absent');
if (AccountInitials::fromName('  Émilie Ben Ali  ')!=='ÉA' || AccountInitials::fromName('Ahmed')!=='A' || AccountInitials::fromName(' ' )!=='?') throw new RuntimeException('Initiales incorrectes');
echo "Compte : coordonnées, sécurité, conflits, audit et initiales OK\n";
