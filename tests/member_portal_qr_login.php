<?php
declare(strict_types=1);
require __DIR__.'/../app/services/MemberPortalQrLogin.php'; require __DIR__.'/../app/services/MemberPortalAuth.php';
class QrLoginPDO extends PDO { public function prepare(string $query,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); } }
$db=new QrLoginPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE members(id INTEGER PRIMARY KEY,first_name TEXT,last_name TEXT,email TEXT,status TEXT,qr_token TEXT); CREATE TABLE member_portal_accounts(member_id INTEGER PRIMARY KEY,email TEXT,password_hash TEXT); CREATE TABLE member_qr_logins(member_id INTEGER PRIMARY KEY,token_hash TEXT,account_fingerprint TEXT,qr_fingerprint TEXT,email TEXT,expires_at TEXT); CREATE TABLE activity_logs(user_id INTEGER,action TEXT,ip_address TEXT)');
$qr=str_repeat('a',64); $hash=password_hash('Mon mot de passe 123!',PASSWORD_DEFAULT);
$db->prepare('INSERT INTO members VALUES (1,?,?,?,?,?)')->execute(['Ali','Test','a@example.tn','active',$qr]);
$db->prepare('INSERT INTO member_portal_accounts VALUES(1,?,?)')->execute(['a@example.tn',$hash]);
$svc=new MemberPortalQrLogin($db); $now=new DateTimeImmutable('2030-01-01 10:00:00');
$reject=static function(callable $fn): void { try { $fn(); } catch (DomainException $e) { return; } throw new RuntimeException('Connexion invalide acceptée'); };
if ($svc->issue('garbage',$now)!==null || $svc->issue('PF1:'.str_repeat('b',64),$now)!==null) throw new RuntimeException('QR invalide accepté');
$a=$svc->issue('PF1:'.$qr,$now); $row=$db->query('SELECT * FROM member_qr_logins')->fetch();
if (!$a || $row['token_hash']!==hash('sha256',$a['token']) || $row['expires_at']!=='2030-01-01 10:15:00' || str_contains(json_encode($row),$qr)) throw new RuntimeException('Demande incorrecte');
$b=$svc->issue('PF1:'.$qr,$now); $reject(fn()=>$svc->confirm($a['token'],'',$now)); $reject(fn()=>$svc->confirm($b['token'],'',$now->modify('+15 minutes')));
$db->exec("UPDATE members SET status='inactive' WHERE id=1"); $reject(fn()=>$svc->confirm($b['token'],'',$now)); $db->exec("UPDATE members SET status='active',qr_token='".str_repeat('c',64)."' WHERE id=1"); $reject(fn()=>$svc->confirm($b['token'],'',$now));
$db->prepare('UPDATE members SET qr_token=? WHERE id=1')->execute([$qr]);
$session=$svc->confirm($b['token'],'127.0.0.1',$now); if (!(new MemberPortalAuth($db))->validSession($session) || (int)$db->query('SELECT COUNT(*) FROM member_qr_logins')->fetchColumn()!==0) throw new RuntimeException('Session incorrecte');
$reject(fn()=>$svc->confirm($b['token'],'',$now));
$c=$svc->issue('PF1:'.$qr,$now); $db->exec('DROP TABLE activity_logs'); try { $svc->confirm($c['token'],'',$now); throw new RuntimeException('Audit manquant accepté'); } catch (PDOException $e) {}
if ((int)$db->query('SELECT COUNT(*) FROM member_qr_logins')->fetchColumn()!==1 || $db->inTransaction()) throw new RuntimeException('Rollback incorrect');
echo "Espace membre : connexion QR confirmée par e-mail, expiration et isolation OK\n";
