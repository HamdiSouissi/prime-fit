<?php
declare(strict_types=1);
require __DIR__.'/../app/services/MemberPortalCard.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE members(id INTEGER PRIMARY KEY,first_name TEXT,last_name TEXT,email TEXT,card_number TEXT,qr_token TEXT,created_at TEXT,status TEXT); CREATE TABLE member_portal_accounts(member_id INTEGER,email TEXT,password_hash TEXT)');
$hash=password_hash('Secret membre 123!',PASSWORD_DEFAULT); $qr=str_repeat('a',64);
$db->prepare('INSERT INTO members VALUES (1,?,?,?,?,?,?,?)')->execute(['Ali','<test>','un@example.tn','CARTE',$qr,'2026-01-01 10:00:00','active']);
$db->prepare('INSERT INTO member_portal_accounts VALUES(1,?,?)')->execute(['un@example.tn',$hash]);
$session=['member_id'=>1,'fingerprint'=>hash('sha256',$hash)]; $service=new MemberPortalCard($db); $member=$service->get($session);
if ($member['qr_token']!==$qr || isset($member['password_hash'])) throw new RuntimeException('Carte incorrecte');
try { $service->get(['member_id'=>2,'fingerprint'=>$session['fingerprint']]); throw new RuntimeException('Accès au compte tiers'); } catch (DomainException $e) {}
$db->exec("UPDATE members SET email='autre@example.tn' WHERE id=1"); try { $service->get($session); throw new RuntimeException('Adresse modifiée acceptée'); } catch (DomainException $e) {}
function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); } function csrf(): string { return 'csrf'; } function gym_name(): string { return 'Club'; } function gym_brand_markup(): string { return 'Club'; }
$notice=null; ob_start(); require __DIR__.'/../app/views/member_portal_card.php'; $html=ob_get_clean();
if (!str_contains($html,'&lt;test&gt;') || !str_contains($html,'PF1:'.$qr) || !str_contains($html,'name="csrf"') || str_contains($html,'name="email"')) throw new RuntimeException('Affichage ou destinataire incorrect');
echo "Espace membre : carte personnelle et isolation OK\n";
