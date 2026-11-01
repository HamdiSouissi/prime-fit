<?php
declare(strict_types=1);
class QuoteTestPDO extends PDO { public function prepare(string $sql,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$sql),$options); } }
$db=new QuoteTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE members (id INTEGER PRIMARY KEY,first_name TEXT,last_name TEXT,status TEXT); CREATE TABLE plans (id INTEGER PRIMARY KEY,name TEXT,duration_days INTEGER,price TEXT,is_active INTEGER); CREATE TABLE promotions (id INTEGER PRIMARY KEY,code TEXT,label TEXT,discount_percent TEXT,starts_on TEXT,ends_on TEXT,is_active INTEGER); CREATE TABLE subscriptions (id INTEGER PRIMARY KEY AUTOINCREMENT,member_id INTEGER,plan_id INTEGER,start_date TEXT,end_date TEXT,agreed_price TEXT,status TEXT); CREATE TABLE subscription_discounts (subscription_id INTEGER PRIMARY KEY,promotion_id INTEGER,code TEXT,label TEXT,base_price TEXT,discount_percent TEXT,discount_amount TEXT); CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT); INSERT INTO members VALUES (1,'<Ali>','Test','active'); INSERT INTO plans VALUES (1,'Mensuel',30,'10.000',1); INSERT INTO promotions VALUES (1,'PROMO','Offre','10.00','2020-01-01','2099-12-31',1);");
function db(): PDO { global $db; return $db; } function verify_csrf(): void {} function csrf(): string { return 'csrf'; } function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); } function gym_name(): string { return 'Salle'; } function gym_brand_markup(): string { return 'Salle'; } function dt($v): string { return number_format((float)$v,3,',',' ').' DT'; }
class QuoteRedirect extends Exception {} function redirect(string $route): void { throw new QuoteRedirect($route); }
require __DIR__.'/../app/controllers/SubscriptionQuoteController.php';
$_SESSION=['user'=>['id'=>1,'name'=>'Admin','role'=>'Admin']]; $_SERVER['REQUEST_METHOD']='POST'; $_SERVER['REMOTE_ADDR']='127.0.0.1';
$plan='1|'.PlanService::version($db->query('SELECT * FROM plans')->fetch()); $promo='1|'.SubscriptionPricing::promotionVersion($db->query('SELECT * FROM promotions')->fetch());
$_POST=['mode'=>'create','phase'=>'quote','plan'=>$plan,'promotion'=>$promo,'member_id'=>'1','start'=>'2030-01-01'];
$_POST['phase']='confirm'; $_POST['expected_price']='9.000'; $_POST['quote_token']=str_repeat('0',64); ob_start(); (new SubscriptionQuoteController())->index(); $html=ob_get_clean(); if (!str_contains($html,'Récapitulatif expiré') || (int)$db->query('SELECT COUNT(*) FROM subscriptions')->fetchColumn()!==0) throw new RuntimeException('Confirmation sans récapitulatif acceptée'); $_POST['phase']='quote';
ob_start(); (new SubscriptionQuoteController())->index(); $html=ob_get_clean();
foreach (['&lt;Ali&gt;','PROMO','10,000 DT','1,000 DT','9,000 DT','value="9.000"'] as $needle) if (!str_contains($html,$needle)) throw new RuntimeException('Récapitulatif incorrect : '.$needle);
if ((int)$db->query('SELECT COUNT(*) FROM subscriptions')->fetchColumn()!==0) throw new RuntimeException('Création avant confirmation');
$_POST['phase']='confirm'; $_POST['expected_price']='9.000'; $_POST['quote_token']=$_SESSION['subscription_quote']['token'];
$db->exec("UPDATE promotions SET is_active=0 WHERE id=1"); ob_start(); (new SubscriptionQuoteController())->index(); $html=ob_get_clean();
if (!str_contains($html,'inactive') || (int)$db->query('SELECT COUNT(*) FROM subscriptions')->fetchColumn()!==0) throw new RuntimeException('Promotion désactivée non refusée');
$db->exec('UPDATE promotions SET is_active=1 WHERE id=1');
$_POST['phase']='quote'; ob_start(); (new SubscriptionQuoteController())->index(); ob_end_clean(); $_POST['phase']='confirm'; $_POST['quote_token']=$_SESSION['subscription_quote']['token'];
try { (new SubscriptionQuoteController())->index(); throw new RuntimeException('Confirmation non enregistrée'); } catch (QuoteRedirect $ex) {}
if ($db->query('SELECT agreed_price FROM subscriptions')->fetchColumn()!=='9.000' || $db->query('SELECT code FROM subscription_discounts')->fetchColumn()!=='PROMO') throw new RuntimeException('Prix ou réduction non enregistrés');
$sub=$db->query('SELECT * FROM subscriptions LIMIT 1')->fetch();
$_POST=['mode'=>'renew','phase'=>'quote','plan'=>$plan,'promotion'=>$promo,'subscription_id'=>(string)$sub['id'],'subscription_version'=>SubscriptionService::version($sub),'start'=>'2030-01-31'];
ob_start(); (new SubscriptionQuoteController())->index(); $html=ob_get_clean();
if (!str_contains($html,'31/01/2030') || !str_contains($html,'9,000 DT')) throw new RuntimeException('Récapitulatif renouvellement incorrect');
$_POST['phase']='confirm'; $_POST['expected_price']='9.000'; $_POST['quote_token']=$_SESSION['subscription_quote']['token']; try { (new SubscriptionQuoteController())->index(); throw new RuntimeException('Renouvellement non enregistré'); } catch (QuoteRedirect $ex) {}
if ((int)$db->query('SELECT COUNT(*) FROM subscription_discounts')->fetchColumn()!==2) throw new RuntimeException('Promotion non appliquée au renouvellement');
echo "Promotion : récapitulatif, confirmation et vérification après changement OK\n";
