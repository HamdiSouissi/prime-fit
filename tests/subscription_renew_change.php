<?php
declare(strict_types=1);
require __DIR__.'/../app/services/SubscriptionService.php';
class RenewalTestPDO extends PDO {
    public array $reads=[];
    public function prepare(string $sql,array $options=[]): PDOStatement|false { if (str_contains($sql,'SELECT member_id FROM subscriptions')) $this->reads[]=$this->inTransaction(); return parent::prepare(str_replace(' FOR UPDATE','',$sql),$options); }
}
$db=new RenewalTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE members (id INTEGER PRIMARY KEY,status TEXT); CREATE TABLE plans (id INTEGER PRIMARY KEY,name TEXT,duration_days INTEGER,price TEXT,is_active INTEGER); CREATE TABLE subscriptions (id INTEGER PRIMARY KEY AUTOINCREMENT,member_id INTEGER,plan_id INTEGER,start_date TEXT,end_date TEXT,status TEXT,agreed_price TEXT); CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT); INSERT INTO members VALUES (1,'active'),(2,'active'),(3,'inactive'); INSERT INTO plans VALUES (1,'Mensuel',30,'90.000',0),(2,'Trimestriel',90,'230.125',1),(3,'Inactif',45,'120.000',0); INSERT INTO subscriptions VALUES (1,1,1,'2026-01-01','2026-01-30','active','90.000'),(2,2,1,'2025-01-01','2025-01-30','active','90.000'),(3,3,1,'2026-01-01','2026-01-30','active','90.000');");
$service=new SubscriptionService($db,1,'127.0.0.1',new DateTimeImmutable('2026-01-15'));
$sub=fn($id)=>$db->query('SELECT * FROM subscriptions WHERE id='.(int)$id)->fetch(); $plan=fn($id)=>$db->query('SELECT * FROM plans WHERE id='.(int)$id)->fetch();
$version=SubscriptionService::version($sub(1)); $planVersion=PlanService::version($plan(2));
try { $service->renew(1,$planVersion,2,$version,'2026-01-30'); throw new RuntimeException('Date de début obsolète acceptée'); } catch (DomainException $ex) {}
try { $service->renew(1,PlanService::version($plan(3)),3,$version,'2026-01-31'); throw new RuntimeException('Formule inactive acceptée'); } catch (DomainException $ex) {}
try { $service->renew(3,$planVersion,2,SubscriptionService::version($sub(3)),'2026-01-31'); throw new RuntimeException('Membre inactif accepté'); } catch (DomainException $ex) {}
$db->exec("UPDATE plans SET price='240.125' WHERE id=2");
try { $service->renew(1,$planVersion,2,$version,'2026-01-31'); throw new RuntimeException('Ancien tarif accepté'); } catch (DomainException $ex) {}
$planVersion=PlanService::version($plan(2));
$db->exec("UPDATE subscriptions SET end_date='2026-02-01' WHERE id=1");
try { $service->renew(1,$planVersion,2,$version,'2026-02-02'); throw new RuntimeException('Ancienne version acceptée'); } catch (DomainException $ex) {}
$db->exec("UPDATE subscriptions SET end_date='2026-01-30' WHERE id=1");
$result=$service->renew(1,$planVersion,2,SubscriptionService::version($sub(1)),'2026-01-31');
if ($result['start']!=='2026-01-31' || $result['end']!=='2026-04-30' || $result['price']!=='240.125' || $sub($result['id'])['plan_id']!=2 || $sub(1)['agreed_price']!=='90.000') throw new RuntimeException('Changement de formule ou tarif incorrect');
try { $service->renew(1,$planVersion,2,SubscriptionService::version($sub(1)),'2026-01-31'); throw new RuntimeException('Renouvellement en double accepté'); } catch (DomainException $ex) {}
$expired=$service->renew(2,$planVersion,2,SubscriptionService::version($sub(2)),'2026-01-15'); if ($expired['start']!=='2026-01-15') throw new RuntimeException('Renouvellement expiré incorrect');
if (in_array(true,$db->reads,true)) throw new RuntimeException('Instantané ouvert avant verrou membre');
$db->exec('DROP TABLE activity_logs'); $before=(int)$db->query('SELECT COUNT(*) FROM subscriptions')->fetchColumn();
try { $service->renew($expired['id'],$planVersion,2,SubscriptionService::version($sub($expired['id'])),'2026-04-15'); throw new RuntimeException('Échec audit ignoré'); } catch (PDOException $ex) {}
if ((int)$db->query('SELECT COUNT(*) FROM subscriptions')->fetchColumn()!==$before || $db->inTransaction()) throw new RuntimeException('Rollback incorrect');
echo "Renouvellement : choix de formule, versions, dates, chevauchement et rollback OK\n";
