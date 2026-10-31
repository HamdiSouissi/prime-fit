<?php
declare(strict_types=1);
require __DIR__.'/../app/services/SubscriptionService.php';
class PlanTestPDO extends PDO { public function prepare(string $query,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); } }
$db=new PlanTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE plans (id INTEGER PRIMARY KEY,name TEXT,duration_days INTEGER,price TEXT,is_active INTEGER); CREATE TABLE members (id INTEGER PRIMARY KEY,status TEXT); CREATE TABLE subscriptions (id INTEGER PRIMARY KEY AUTOINCREMENT,member_id INTEGER,plan_id INTEGER,start_date TEXT,end_date TEXT,status TEXT,agreed_price TEXT); CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT); INSERT INTO plans VALUES (1,'Mensuel',30,'90.000',1); INSERT INTO members VALUES (1,'active'),(2,'active');");
$get=static fn()=> $db->query('SELECT * FROM plans WHERE id=1')->fetch();
$service=new PlanService($db,1,'127.0.0.1'); $subscription=new SubscriptionService($db,1,'127.0.0.1',new DateTimeImmutable('2026-01-01'));
$version=PlanService::version($get()); $created=$subscription->create(1,1,'2026-01-01',$version);
$service->edit(1,$version,['name'=>'Mensuel Plus','duration_days'=>'31','price'=>'120,500']);
$old=$db->query('SELECT * FROM subscriptions WHERE id=1')->fetch();
if ($old['agreed_price']!=='90.000' || $old['end_date']!=='2026-01-30') throw new RuntimeException('Abonnement existant modifié');
try { $service->edit(1,$version,['name'=>'Ancien','duration_days'=>'30','price'=>'1']); throw new RuntimeException('Version périmée acceptée'); } catch (DomainException $expected) {}
try { $subscription->create(2,1,'2026-01-01',$version); throw new RuntimeException('Création au tarif périmé acceptée'); } catch (DomainException $expected) {}
try { $subscription->renew(1,$version); throw new RuntimeException('Renouvellement au tarif périmé accepté'); } catch (DomainException $expected) {}
$current=PlanService::version($get()); $new=$subscription->renew(1,$current);
if ($new['price']!=='120.500' || $new['start']!=='2026-01-31' || $new['end']!=='2026-03-02') throw new RuntimeException('Prix ou durée renouvellement incorrect');
$service->setActive(1,$current,false);
try { $subscription->create(2,1,'2026-01-01',PlanService::version($get())); throw new RuntimeException('Formule inactive acceptée'); } catch (DomainException $expected) {}
if ($db->query('SELECT status FROM subscriptions WHERE id=1')->fetchColumn()!=='active') throw new RuntimeException('Ancien abonnement désactivé');
$service->setActive(1,PlanService::version($get()),true);
try { $service->edit(1,PlanService::version($get()),['name'=>'','duration_days'=>'0','price'=>'-2']); throw new RuntimeException('Entrée invalide acceptée'); } catch (DomainException $expected) {}
$db->exec('DROP TABLE activity_logs');
try { $service->edit(1,PlanService::version($get()),['name'=>'Essai','duration_days'=>'30','price'=>'1']); throw new RuntimeException('Audit ignoré'); } catch (PDOException $expected) {}
if ($get()['name']!=='Mensuel Plus') throw new RuntimeException('Rollback incorrect');
echo "Formules : modification, protection concurrente, renouvellement et rollback OK\n";
