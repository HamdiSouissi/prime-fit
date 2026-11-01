<?php
declare(strict_types=1);
require __DIR__.'/../app/services/MemberPortalProfile.php';
class PortalProfilePDO extends PDO { public function prepare(string $query,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); } }
$db=new PortalProfilePDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE members(id INTEGER PRIMARY KEY,first_name TEXT,last_name TEXT,phone TEXT,email TEXT,governorate TEXT,birth_date TEXT,status TEXT); CREATE TABLE member_portal_accounts(member_id INTEGER,email TEXT,password_hash TEXT); CREATE TABLE marketing_consent_events(id INTEGER PRIMARY KEY,member_id INTEGER,decision TEXT,phone_value TEXT,evidence TEXT,created_by INTEGER); CREATE TABLE activity_logs(user_id INTEGER,action TEXT,ip_address TEXT)');
$password='Mot de passe personnel 123!'; $hash=password_hash($password,PASSWORD_DEFAULT);
$db->prepare('INSERT INTO members VALUES(?,?,?,?,?,?,?,?)')->execute([1,'Amine','Ben Ali','12345678','un@example.tn','Tunis','1995-03-10','active']);
$db->prepare('INSERT INTO members VALUES(?,?,?,?,?,?,?,?)')->execute([2,'Sara','Test',null,'deux@example.tn',null,null,'active']);
$db->prepare('INSERT INTO member_portal_accounts VALUES(?,?,?)')->execute([1,'un@example.tn',$hash]);
$db->prepare('INSERT INTO member_portal_accounts VALUES(?,?,?)')->execute([2,'deux@example.tn',$hash]);
$service=new MemberPortalProfile($db); $session=['member_id'=>1,'fingerprint'=>hash('sha256',$hash)]; $initial=$service->get($session);
if (isset($initial['password_hash']) || isset($initial['account_email'])) throw new RuntimeException('Secret renvoyé');
$input=['first_name'=>'Amine <test>','last_name'=>'Ben Ali','phone'=>'+216 20 111 222','governorate'=>'Sidi Bouzid','birth_date'=>'1995-03-10','current_password'=>$password,'version'=>$initial['version']];
$reject=static function(callable $fn): void { try { $fn(); } catch (DomainException $e) { return; } throw new RuntimeException('Modification invalide acceptée'); };
foreach ([['current_password'=>'incorrect'],['first_name'=>''],['last_name'=>"Bad\nName"],['phone'=>'---() '],['birth_date'=>'2035-01-01'],['birth_date'=>'1995-02-30'],['governorate'=>'Inconnu'],['version'=>str_repeat('0',64)],['phone'=>['abc']]] as $changes) $reject(fn()=>$service->update($session,array_replace($input,$changes),'127.0.0.1',new DateTimeImmutable('2030-01-01')));
if ($db->query('SELECT first_name FROM members WHERE id=1')->fetchColumn()!=='Amine' || (int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn()!==0) throw new RuntimeException('Modification invalide enregistrée');
$service->update($session,$input,'127.0.0.1',new DateTimeImmutable('2030-01-01')); $updated=$service->get($session);
if ($updated['first_name']!=='Amine <test>' || $updated['phone']!=='+216 20 111 222' || $updated['email']!=='un@example.tn' || (int)$db->query('SELECT COUNT(*) FROM marketing_consent_events WHERE member_id=1 AND decision="invalidated" AND created_by IS NULL')->fetchColumn()!==1 || (int)$db->query('SELECT COUNT(*) FROM activity_logs WHERE user_id IS NULL')->fetchColumn()!==2 || $db->query('SELECT first_name FROM members WHERE id=2')->fetchColumn()!=='Sara') throw new RuntimeException('Mise à jour, consentement, audit ou isolation incorrect');
$reject(fn()=>$service->update($session,$input,'',new DateTimeImmutable('2030-01-01')));
$db->exec("UPDATE members SET status='inactive' WHERE id=1"); $reject(fn()=>$service->get($session)); $reject(fn()=>$service->update($session,array_replace($input,['version'=>$updated['version']]),''));
$db->exec("UPDATE members SET status='active' WHERE id=1"); $db->exec('DROP TABLE activity_logs');
try { $service->update($session,array_replace($input,['version'=>$updated['version'],'phone'=>'12345678']),''); throw new RuntimeException('Journal absent accepté'); } catch (PDOException $e) {}
if ($db->query('SELECT phone FROM members WHERE id=1')->fetchColumn()!=='+216 20 111 222' || (int)$db->query('SELECT COUNT(*) FROM marketing_consent_events')->fetchColumn()!==1 || $db->inTransaction()) throw new RuntimeException('Rollback incomplet');
function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); } function csrf(): string { return 'csrf'; } function gym_name(): string { return 'Club'; } function gym_brand_markup(): string { return 'Club'; }
$profile=$updated; $error=''; $notice=''; ob_start(); require __DIR__.'/../app/views/member_portal_profile.php'; $html=ob_get_clean();
if (!str_contains($html,'Amine &lt;test&gt;') || str_contains($html,'Amine <test>') || str_contains($html,$password) || str_contains($html,$hash) || !str_contains($html,'name="csrf"') || !str_contains($html,'readonly')) throw new RuntimeException('Rendu non sécurisé');
echo "Espace membre : modification de la fiche, version, audit, consentement et isolation OK\n";
