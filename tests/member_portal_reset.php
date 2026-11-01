<?php
declare(strict_types=1);
require __DIR__.'/../app/services/MemberPortalPasswordReset.php'; require __DIR__.'/../app/services/MemberPortalAuth.php';
class ResetTestPDO extends PDO { public function prepare(string $query,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); } }
$db=new ResetTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE members(id INTEGER PRIMARY KEY,first_name TEXT,last_name TEXT,email TEXT,status TEXT); CREATE TABLE member_portal_accounts(member_id INTEGER PRIMARY KEY,email TEXT UNIQUE,password_hash TEXT); CREATE TABLE member_password_resets(member_id INTEGER PRIMARY KEY,token_hash TEXT UNIQUE,account_fingerprint TEXT,email TEXT,expires_at TEXT); CREATE TABLE activity_logs(user_id INTEGER,action TEXT,ip_address TEXT)');
$password='Ancien mot de passe 123'; $new='Nouveau mot de passe 456'; $hash=password_hash($password,PASSWORD_DEFAULT);
$db->exec("INSERT INTO members VALUES(1,'Ali','Test','ali@example.tn','active'),(2,'Sara','Test','sara@example.tn','active'),(3,'Stop','Test','stop@example.tn','inactive')");
$db->prepare('INSERT INTO member_portal_accounts VALUES(1,?,?)')->execute(['ali@example.tn',$hash]); $db->exec("INSERT INTO member_portal_accounts VALUES(2,'sara@example.tn',NULL)"); $db->prepare('INSERT INTO member_portal_accounts VALUES(3,?,?)')->execute(['stop@example.tn',$hash]);
$svc=new MemberPortalPasswordReset($db); $now=new DateTimeImmutable('2030-01-01 10:00:00');
$reject=static function(callable $fn): void { try { $fn(); } catch (DomainException $e) { return; } throw new RuntimeException('Demande invalide acceptée'); };
if ($svc->issue('missing@example.tn',$now)!==null || $svc->issue('sara@example.tn',$now)!==null || $svc->issue('stop@example.tn',$now)!==null) throw new RuntimeException('Compte non éligible');
$a=$svc->issue('ali@example.tn',$now); $row=$db->query('SELECT * FROM member_password_resets')->fetch();
if (!$a || $row['token_hash']!==hash('sha256',$a['token']) || $row['expires_at']!=='2030-01-01 11:00:00') throw new RuntimeException('Jeton invalide');
$b=$svc->issue('ali@example.tn',$now); $reject(fn()=>$svc->reset($a['token'],$new,$new,'',$now)); $reject(fn()=>$svc->reset($b['token'],'short','short','',$now)); $reject(fn()=>$svc->reset($b['token'],$new,$new,'',$now->modify('+1 hour')));
$db->exec("UPDATE members SET email='changed@example.tn' WHERE id=1"); $reject(fn()=>$svc->reset($b['token'],$new,$new,'',$now)); $db->exec("UPDATE members SET email='ali@example.tn',status='inactive' WHERE id=1"); $reject(fn()=>$svc->reset($b['token'],$new,$new,'',$now)); $db->exec("UPDATE members SET status='active' WHERE id=1");
$oldSession=(new MemberPortalAuth($db))->authenticate('ali@example.tn',$password);
$svc->reset($b['token'],$new,$new,'127.0.0.1',$now);
if ((new MemberPortalAuth($db))->validSession($oldSession) || !(new MemberPortalAuth($db))->authenticate('ali@example.tn',$new) || (int)$db->query('SELECT COUNT(*) FROM member_password_resets')->fetchColumn()!==0) throw new RuntimeException('Réinitialisation ou révocation incorrecte');
$reject(fn()=>$svc->reset($b['token'],$new,$new,'',$now));
$c=$svc->issue('ali@example.tn',$now); $db->exec('DROP TABLE activity_logs'); try { $svc->reset($c['token'],$password,$password,'',$now); throw new RuntimeException('Audit manquant accepté'); } catch (PDOException $e) {}
if (!(new MemberPortalAuth($db))->authenticate('ali@example.tn',$new) || (int)$db->query('SELECT COUNT(*) FROM member_password_resets')->fetchColumn()!==1) throw new RuntimeException('Rollback incorrect');
echo "Espace membre : réinitialisation, expiration, révocation et rollback OK\n";
