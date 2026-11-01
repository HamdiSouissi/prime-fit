<?php
declare(strict_types=1);
require __DIR__.'/../app/services/MemberPortalInvitations.php';
class PortalTestPDO extends PDO { public function prepare(string $query,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); } }
$db=new PortalTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE members(id INTEGER PRIMARY KEY,first_name TEXT,last_name TEXT,email TEXT,status TEXT); CREATE TABLE member_portal_accounts(member_id INTEGER PRIMARY KEY,email TEXT UNIQUE,password_hash TEXT,invitation_hash TEXT UNIQUE,invitation_expires_at TEXT);");
$db->exec("INSERT INTO members VALUES(1,'Amine','Ben Ali','amine@example.tn','active'),(2,'Sara','Ben Ali','sara@example.tn','active'),(3,'Inactif','Test','inactive@example.tn','inactive'),(4,'Sans','Email',NULL,'active'),(5,'Doublon','Test','amine@example.tn','active')");
$service=new MemberPortalInvitations($db); $now=new DateTimeImmutable('2026-02-01 09:00:00');
$expectFailure=static function(callable $f): void { try { $f(); } catch (DomainException $e) { return; } throw new RuntimeException('Opération invalide acceptée'); };
$expectFailure(fn()=>$service->issue(3,$now)); $expectFailure(fn()=>$service->issue(4,$now));
$invite=$service->issue(1,$now); $row=$db->query('SELECT * FROM member_portal_accounts WHERE member_id=1')->fetch();
if ($row['invitation_hash']!==hash('sha256',$invite['token']) || $row['invitation_hash']===$invite['token'] || $row['password_hash']!==null || $invite['expires_at']!=='2026-02-02 09:00:00') throw new RuntimeException('Invitation incorrecte');
try { $service->issue(5,$now); throw new RuntimeException('Adresse en double acceptée'); } catch (PDOException $e) {}
if ($db->inTransaction()) throw new RuntimeException('Transaction non annulée');
$second=$service->issue(1,$now); $password='Mot de passe très sûr 2026';
$expectFailure(fn()=>$service->activate($invite['token'],$password,$password,$now));
$expectFailure(fn()=>$service->activate($second['token'],'court','court',$now));
$expectFailure(fn()=>$service->activate($second['token'],$password,'différent',$now));
$expectFailure(fn()=>$service->activate($second['token'],$password,$password,$now->modify('+24 hours')));
$db->exec("UPDATE members SET email='change@example.tn' WHERE id=1");
$expectFailure(fn()=>$service->activate($second['token'],$password,$password,$now));
$db->exec("UPDATE members SET email='amine@example.tn',status='blacklisted' WHERE id=1");
$expectFailure(fn()=>$service->activate($second['token'],$password,$password,$now));
$db->exec("UPDATE members SET status='active' WHERE id=1");
$service->activate($second['token'],$password,$password,$now);
$row=$db->query('SELECT * FROM member_portal_accounts WHERE member_id=1')->fetch();
if (!password_verify($password,$row['password_hash']) || $row['invitation_hash']!==null || $row['invitation_expires_at']!==null) throw new RuntimeException('Activation incorrecte');
$expectFailure(fn()=>$service->activate($second['token'],$password,$password,$now)); $expectFailure(fn()=>$service->issue(1,$now));
$other=$service->issue(2,$now); if ($other['email']!=='sara@example.tn') throw new RuntimeException('Mauvais destinataire');
echo "Espace membre : invitations, expiration et activation à usage unique OK\n";
