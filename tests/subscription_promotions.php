<?php
declare(strict_types=1);
require __DIR__.'/../app/services/SubscriptionService.php';
class PromotionTestPDO extends PDO { public function prepare(string $sql,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$sql),$options); } }
$db=new PromotionTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE members (id INTEGER PRIMARY KEY,status TEXT); CREATE TABLE plans (id INTEGER PRIMARY KEY,name TEXT,duration_days INTEGER,price TEXT,is_active INTEGER); CREATE TABLE promotions (id INTEGER PRIMARY KEY,code TEXT,label TEXT,discount_percent TEXT,starts_on TEXT,ends_on TEXT,is_active INTEGER); CREATE TABLE subscriptions (id INTEGER PRIMARY KEY AUTOINCREMENT,member_id INTEGER,plan_id INTEGER,start_date TEXT,end_date TEXT,agreed_price TEXT,status TEXT); CREATE TABLE subscription_discounts (subscription_id INTEGER PRIMARY KEY,promotion_id INTEGER,code TEXT,label TEXT,base_price TEXT,discount_percent TEXT,discount_amount TEXT); CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT); INSERT INTO members VALUES (1,'active'),(2,'active'),(3,'active'); INSERT INTO plans VALUES (1,'Mensuel',30,'10.000',1),(2,'Test',7,'0.001',1); INSERT INTO promotions VALUES (1,'TEST','Offre','33.33','2030-01-01','2030-12-31',1),(2,'FUTUR','À venir','10.00','2031-01-01','2031-12-31',1),(3,'GRATUIT','Offre totale','100.00','2030-01-01','2030-12-31',1),(4,'OFF','Inactif','10.00','2030-01-01','2030-12-31',0);");
$pricing=new SubscriptionPricing($db); $on='2030-05-01'; $plan=fn($id)=>$db->query('SELECT * FROM plans WHERE id='.(int)$id)->fetch(); $promo=fn($id)=>$db->query('SELECT * FROM promotions WHERE id='.(int)$id)->fetch();
if (SubscriptionPricing::discount('10.000','33.33')!==['base'=>'10.000','discount'=>'3.333','price'=>'6.667'] || SubscriptionPricing::discount('0.001','50.00')['price']!=='0.000' || SubscriptionPricing::discount('9999999.999','100.00')['price']!=='0.000') throw new RuntimeException('Calcul ou arrondi incorrect');
foreach (['0','100.01','101','1.999','-1'] as $rate) { try { SubscriptionPricing::discount('10.000',$rate); throw new RuntimeException('Réduction invalide acceptée'); } catch (DomainException $ex) {} }
if (count($pricing->available($on))!==2) throw new RuntimeException('Filtres des promotions incorrects');
$pv=PlanService::version($plan(1)); $dv=SubscriptionPricing::promotionVersion($promo(1)); $service=new SubscriptionService($db,1,'127.0.0.1',new DateTimeImmutable($on));
foreach ([2,4] as $id) { try { $service->create(1,1,'2030-05-01',$pv,$id,SubscriptionPricing::promotionVersion($promo($id)),'9.000'); throw new RuntimeException('Promotion indisponible acceptée'); } catch (DomainException $ex) {} }
try { $service->create(1,1,'2030-05-01',$pv,1,$dv,'6.668'); throw new RuntimeException('Prix confirmé falsifié'); } catch (DomainException $ex) {}
$db->exec("UPDATE promotions SET label='Nouvelle offre' WHERE id=1");
try { $service->create(1,1,'2030-05-01',$pv,1,$dv,'6.667'); throw new RuntimeException('Promotion périmée acceptée'); } catch (DomainException $ex) {}
$dv=SubscriptionPricing::promotionVersion($promo(1)); $created=$service->create(1,1,'2030-05-01',$pv,1,$dv,'6.667');
$snapshot=$db->query('SELECT * FROM subscription_discounts WHERE subscription_id='.$created['id'])->fetch();
if ($created['price']!=='6.667' || $snapshot['base_price']!=='10.000' || $snapshot['discount_amount']!=='3.333' || $snapshot['code']!=='TEST') throw new RuntimeException('Prix ou détail non figé');
$db->exec("UPDATE promotions SET discount_percent='90.00',is_active=0 WHERE id=1; UPDATE plans SET price='15.000' WHERE id=1");
if ($db->query('SELECT agreed_price FROM subscriptions WHERE id='.$created['id'])->fetchColumn()!=='6.667' || $db->query('SELECT discount_percent FROM subscription_discounts WHERE subscription_id='.$created['id'])->fetchColumn()!=='33.33') throw new RuntimeException('Historique altéré');
$plan2=PlanService::version($plan(2)); $promo3=SubscriptionPricing::promotionVersion($promo(3));
$new=$service->renew($created['id'],$plan2,2,SubscriptionService::version($db->query('SELECT * FROM subscriptions WHERE id='.$created['id'])->fetch()),'2030-05-31',3,$promo3,'0.000');
if ($new['price']!=='0.000' || $new['end']!=='2030-06-06') throw new RuntimeException('Renouvellement gratuit incorrect');
$db->exec('DROP TABLE activity_logs'); $before=(int)$db->query('SELECT COUNT(*) FROM subscriptions')->fetchColumn(); $disc=(int)$db->query('SELECT COUNT(*) FROM subscription_discounts')->fetchColumn();
try { $service->create(2,2,'2030-05-01',$plan2,3,$promo3,'0.000'); throw new RuntimeException('Échec audit ignoré'); } catch (PDOException $ex) {}
if ($db->inTransaction() || (int)$db->query('SELECT COUNT(*) FROM subscriptions')->fetchColumn()!==$before || (int)$db->query('SELECT COUNT(*) FROM subscription_discounts')->fetchColumn()!==$disc) throw new RuntimeException('Rollback incorrect');
echo "Promotions : calcul exact, validation, snapshots, renouvellement et rollback OK\n";
