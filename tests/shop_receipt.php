<?php
declare(strict_types=1);
class ShopReceiptTestPDO extends PDO { public function prepare(string $sql,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$sql),$options); } }
$db=new ShopReceiptTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE products (id INTEGER PRIMARY KEY,name TEXT,sku TEXT,price TEXT,stock INTEGER,is_active INTEGER); CREATE TABLE shop_sales (id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,product_name_snapshot TEXT,product_sku_snapshot TEXT,quantity INTEGER,unit_price TEXT,total TEXT,method TEXT,sold_by INTEGER,sold_at TEXT DEFAULT '2030-05-04 14:25:00'); CREATE TABLE stock_movements (id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,quantity_change INTEGER,reason TEXT,sale_id INTEGER,created_by INTEGER); CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT); CREATE TABLE gym_settings (id INTEGER PRIMARY KEY,address TEXT,phone TEXT,email TEXT,tax_id TEXT); INSERT INTO products VALUES (1,'Eau <sport>','EAU-1','2.500',10,1); INSERT INTO gym_settings VALUES (1,'Adresse','', '', '');");
function db(): PDO { global $db; return $db; } function verify_csrf(): void {} function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); } function gym_name(): string { return 'Salle <test>'; } function dt($v): string { return number_format((float)$v,3,',',' ').' DT'; }
class ShopRedirect extends Exception {} function redirect(string $route): void { throw new ShopRedirect($route); }
require __DIR__.'/../app/controllers/ShopController.php';
$_SESSION=['user'=>['id'=>1,'role'=>'Réceptionniste']]; $_SERVER['REQUEST_METHOD']='POST'; $_SERVER['REMOTE_ADDR']='127.0.0.1'; $_POST=['action'=>'sale','product_id'=>'1','quantity'=>'2','method'=>'espèces'];
try { (new ShopController())->index(); throw new RuntimeException('Vente non redirigée'); } catch (ShopRedirect $ex) { if ($ex->getMessage()!=='shop_receipt&id=1') throw $ex; }
$s=$db->query('SELECT * FROM shop_sales WHERE id=1')->fetch();
if ($s['product_name_snapshot']!=='Eau <sport>' || $s['product_sku_snapshot']!=='EAU-1' || (float)$s['total']!==5.0 || (int)$db->query('SELECT stock FROM products WHERE id=1')->fetchColumn()!==8 || (int)$db->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn()!==1) throw new RuntimeException('Vente ou snapshot incorrect');
$db->exec("UPDATE products SET name='Nouveau',sku='EAU-2',price='7.000' WHERE id=1; INSERT INTO shop_sales (product_id,quantity,unit_price,total,method) VALUES (1,1,'1.000','1.000','D17')");
$_SERVER['REQUEST_METHOD']='GET'; $_GET=['id'=>'1']; ob_start(); (new ShopController())->receipt(); $html=ob_get_clean();
foreach (['Salle &lt;test&gt;','Eau &lt;sport&gt;','EAU-1','2,500 DT','5,000 DT','pas une facture fiscale','04/05/2030'] as $str) if (!str_contains($html,$str)) throw new RuntimeException('Reçu manquant : '.$str);
if (str_contains($html,'Nouveau') || str_contains($html,'antérieure à la conservation')) throw new RuntimeException('Snapshot incorrect');
$_GET=['id'=>'2']; $_SESSION['user']['role']='Comptable'; ob_start(); (new ShopController())->receipt(); $html=ob_get_clean();
foreach (['Nouveau','EAU-2','antérieure à la conservation','1,000 DT'] as $str) if (!str_contains($html,$str)) throw new RuntimeException('Vente historique : '.$str);
$_SERVER['REQUEST_METHOD']='POST'; $_SESSION['user']['role']='Réceptionniste'; $_POST=['action'=>'sale','product_id'=>'1','quantity'=>'1','method'=>'D17']; $db->exec('DROP TABLE activity_logs');
try { (new ShopController())->index(); throw new RuntimeException('Audit ignoré'); } catch (PDOException $ex) {}
if ($db->inTransaction() || (int)$db->query('SELECT stock FROM products WHERE id=1')->fetchColumn()!==8 || (int)$db->query('SELECT COUNT(*) FROM shop_sales')->fetchColumn()!==2 || (int)$db->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn()!==1) throw new RuntimeException('Rollback incorrect');
echo "Boutique : reçu, snapshot, anciennes ventes et rollback OK\n";
