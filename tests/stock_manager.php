<?php
declare(strict_types=1);
// PDO SQLite teste la logique et le rollback ; les verrous concurrents nécessitent MySQL.
require __DIR__.'/../app/services/StockManager.php';
class StockTestPDO extends PDO {
    public function prepare(string $query,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); }
}
$db=new StockTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE products (id INTEGER PRIMARY KEY,name TEXT,sku TEXT UNIQUE,price TEXT,stock INTEGER,low_stock_threshold INTEGER,is_active INTEGER); CREATE TABLE stock_movements (id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,quantity_change INTEGER,reason TEXT,note TEXT,created_by INTEGER); CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT); INSERT INTO products VALUES (1,'Eau','EAU-01','2.000',10,2,1),(2,'Barre','BAR-01','3.000',5,1,1)");
$service=new StockManager($db,1,'127.0.0.1');
$service->edit(1,['name'=>'Eau minérale','sku'=>'EAU-01','price'=>'2,500','threshold'=>'3']);
$p=$db->query('SELECT * FROM products WHERE id=1')->fetch(); if ($p['name']!=='Eau minérale' || $p['price']!=='2.500' || (int)$p['stock']!==10) throw new RuntimeException('Produit incorrect');
try { $service->edit(1,['name'=>'Eau','sku'=>'BAR-01','price'=>'2','threshold'=>'3']); throw new RuntimeException('Référence dupliquée acceptée'); } catch (PDOException $ex) {}
if ($service->adjust(1,7,10,'Casse')!==-3 || (int)$db->query('SELECT stock FROM products WHERE id=1')->fetchColumn()!==7) throw new RuntimeException('Correction incorrecte');
$movement=$db->query('SELECT * FROM stock_movements')->fetch(); if ($movement['reason']!=='adjustment' || $movement['note']!=='Casse' || (int)$movement['quantity_change']!==-3) throw new RuntimeException('Mouvement incorrect');
try { $service->adjust(1,9,10,'Ancien inventaire'); throw new RuntimeException('Stock obsolète accepté'); } catch (DomainException $ex) {}
try { $service->adjust(1,9,7,''); throw new RuntimeException('Motif vide accepté'); } catch (DomainException $ex) {}
if ((int)$db->query('SELECT stock FROM products WHERE id=1')->fetchColumn()!==7 || (int)$db->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn()!==1) throw new RuntimeException('Correction invalide persistée');
$service->setActive(1,false); if ((int)$db->query('SELECT is_active FROM products WHERE id=1')->fetchColumn()!==0) throw new RuntimeException('Désactivation incorrecte');
$service->setActive(1,true); $db->exec('DROP TABLE activity_logs');
try { $service->adjust(1,4,7,'Test rollback'); throw new RuntimeException('Erreur audit ignorée'); } catch (PDOException $ex) {}
if ((int)$db->query('SELECT stock FROM products WHERE id=1')->fetchColumn()!==7 || (int)$db->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn()!==1) throw new RuntimeException('Rollback incorrect');
echo "Boutique : édition, correction, version obsolète et rollback OK\n";
