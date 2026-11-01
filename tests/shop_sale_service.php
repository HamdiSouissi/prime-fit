<?php
declare(strict_types=1);
require __DIR__.'/../app/services/ShopSaleService.php';
class SalePDO extends PDO { public function prepare(string $sql,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$sql),$options); } }
$db=new SalePDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE products (id INTEGER PRIMARY KEY,name TEXT,sku TEXT,price TEXT,stock INTEGER,is_active INTEGER); CREATE TABLE shop_sales (id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,product_name_snapshot TEXT,product_sku_snapshot TEXT,quantity INTEGER,unit_price TEXT,total TEXT,method TEXT,sold_by INTEGER); CREATE TABLE shop_sale_requests (request_token TEXT PRIMARY KEY,request_hash TEXT NOT NULL,sale_id INTEGER UNIQUE); CREATE TABLE stock_movements (product_id INTEGER,quantity_change INTEGER,reason TEXT,sale_id INTEGER,created_by INTEGER); CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT); INSERT INTO products VALUES (1,'Eau','E1','2.500',3,1),(2,'Barre','B1','1.000',5,1)");
$service=new ShopSaleService($db,1,'127.0.0.1'); $get=fn($id)=>$db->query('SELECT * FROM products WHERE id='.(int)$id)->fetch(); $v=ShopSaleService::productVersion($get(1)); $token=str_repeat('a',64);
$result=$service->sell(1,$v,3,'D17',$token);
if ($result!==['id'=>1,'replayed'=>false] || (int)$db->query('SELECT stock FROM products WHERE id=1')->fetchColumn()!==0) throw new RuntimeException('Vente incorrecte');
// Même lorsque le stock et le produit ont changé, la répétition retourne le reçu initial.
$db->exec("UPDATE products SET name='Nouveau',price='9.000',is_active=0 WHERE id=1");
if ($service->sell(1,$v,3,'D17',$token)!==['id'=>1,'replayed'=>true]) throw new RuntimeException('Répétition incorrecte');
foreach ([[1,$v,2,'D17'],[2,ShopSaleService::productVersion($get(2)),1,'D17']] as [$id,$version,$qty,$method]) {
    try { $service->sell($id,$version,$qty,$method,$token); throw new RuntimeException('Jeton réutilisé accepté'); } catch (DomainException $ex) {}
}
if ((int)$db->query('SELECT COUNT(*) FROM shop_sales')->fetchColumn()!==1 || (int)$db->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn()!==1 || (int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn()!==1) throw new RuntimeException('Écriture dupliquée');
$v2=ShopSaleService::productVersion($get(2)); $db->exec("UPDATE products SET price='2.000' WHERE id=2");
try { $service->sell(2,$v2,1,'espèces',str_repeat('b',64)); throw new RuntimeException('Prix périmé accepté'); } catch (DomainException $ex) {}
if ((int)$db->query('SELECT COUNT(*) FROM shop_sale_requests')->fetchColumn()!==1 || (int)$db->query('SELECT stock FROM products WHERE id=2')->fetchColumn()!==5) throw new RuntimeException('Réservation non annulée');
$v2=ShopSaleService::productVersion($get(2));
try { $service->sell(2,$v2,6,'D17',str_repeat('c',64)); throw new RuntimeException('Stock insuffisant accepté'); } catch (DomainException $ex) {}
try { $service->sell(2,$v2,1,'D17','bad'); throw new RuntimeException('Jeton invalide accepté'); } catch (DomainException $ex) {}
$db->exec('DROP TABLE activity_logs');
try { $service->sell(2,$v2,1,'D17',str_repeat('d',64)); throw new RuntimeException('Audit non obligatoire'); } catch (PDOException $ex) {}
if ($db->inTransaction() || (int)$db->query('SELECT COUNT(*) FROM shop_sale_requests')->fetchColumn()!==1 || (int)$db->query('SELECT COUNT(*) FROM shop_sales')->fetchColumn()!==1 || (int)$db->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn()!==1 || (int)$db->query('SELECT stock FROM products WHERE id=2')->fetchColumn()!==5) throw new RuntimeException('Rollback incomplet');
echo "Vente boutique : prix obsolète, répétition, contenu du jeton et rollback OK\n";
