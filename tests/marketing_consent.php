<?php
declare(strict_types=1);
require __DIR__.'/../app/services/MarketingConsent.php';
class ConsentTestPDO extends PDO { public function prepare(string $sql,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$sql),$options); } }
$db=new ConsentTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE members (id INTEGER PRIMARY KEY,phone TEXT); CREATE TABLE marketing_consent_events (id INTEGER PRIMARY KEY AUTOINCREMENT,member_id INTEGER,decision TEXT,phone_value TEXT,evidence TEXT,created_by INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP); CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT); INSERT INTO members VALUES (1,'+216 12345678'),(2,'bad');");
$s=new MarketingConsent($db,1,'127.0.0.1');
if ($s->latest(1)!==null || MarketingConsent::phone('+216 12 345 678')!=='21612345678' || MarketingConsent::phone('123456789')!==null) throw new RuntimeException('Défaut ou téléphone invalide');
foreach ([['accepted','',0,'+216 12345678'],['accepted','a',0,'+216 12345678'],['bad','Justificatif',0,'+216 12345678'],['accepted','Justificatif',0,'different']] as [$decision,$evidence,$version,$phone]) { try { $s->record(1,$version,$decision,$evidence,$phone); throw new RuntimeException('Décision invalide acceptée'); } catch (DomainException $ex) {} }
try { $s->record(2,0,'accepted','Consentement','bad'); throw new RuntimeException('Numéro invalide accepté'); } catch (DomainException $ex) {}
$s->record(1,0,'accepted','Formulaire signé','+216 12345678'); $version=(int)$s->latest(1)['id'];
try { $s->record(1,0,'accepted','Ancien formulaire','+216 12345678'); throw new RuntimeException('Version obsolète acceptée'); } catch (DomainException $ex) {}
$s->record(1,$version,'revoked','','+216 12345678'); if ($s->latest(1)['decision']!=='revoked') throw new RuntimeException('Retrait non enregistré');
$s->record(1,(int)$s->latest(1)['id'],'accepted','Accord renouvelé','+216 12345678');
$db->beginTransaction(); $s->revokeForPhoneChange(1,'+216 12345678','98765432'); $db->prepare('UPDATE members SET phone=? WHERE id=1')->execute(['98765432']); $db->commit();
if ($s->latest(1)['decision']!=='invalidated') throw new RuntimeException('Changement de numéro non révoqué');
$db->beginTransaction(); $s->revokeForPhoneChange(1,'98765432','+216 12345678'); $db->rollBack();
if ($s->latest(1)['decision']!=='invalidated') throw new RuntimeException('Ancien consentement réactivé');
$db->exec('DROP TABLE activity_logs'); $before=(int)$db->query('SELECT COUNT(*) FROM marketing_consent_events')->fetchColumn();
try { $s->record(1,(int)$s->latest(1)['id'],'accepted','Consentement','98765432'); throw new RuntimeException('Audit manquant ignoré'); } catch (PDOException $ex) {}
if ($db->inTransaction() || (int)$db->query('SELECT COUNT(*) FROM marketing_consent_events')->fetchColumn()!==$before) throw new RuntimeException('Rollback incorrect');
echo "Consentement WhatsApp : défaut, autorisation, retrait, numéro, versions et rollback OK\n";
