<?php
declare(strict_types=1);
require __DIR__.'/../app/services/StockHistory.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE products (id INTEGER PRIMARY KEY,name TEXT,sku TEXT,stock INTEGER,is_active INTEGER); CREATE TABLE users (id INTEGER PRIMARY KEY,name TEXT); CREATE TABLE stock_movements (id INTEGER PRIMARY KEY,product_id INTEGER,quantity_change INTEGER,reason TEXT,note TEXT,sale_id INTEGER,created_by INTEGER,created_at TEXT); INSERT INTO products VALUES (1,'Eau','EAU',4,0),(2,'Barre','BAR',2,1); INSERT INTO users VALUES (1,'<Admin>')");
$insert=$db->prepare('INSERT INTO stock_movements VALUES (?,?,?,?,?,?,?,?)');
for ($i=1;$i<=125;$i++) $insert->execute([$i,1,($i%2 ? -1 : 2),$i%2 ? 'sale' : 'restock',null,$i%2 ? $i : null,1,'2030-05-01 12:00:00']);
$insert->execute([126,1,1,'adjustment','<casse>',null,1,'2030-05-02 23:59:59']); $insert->execute([127,2,1,'initial',null,null,null,'2030-05-02 12:00:00']); $insert->execute([128,1,1,'initial',null,null,null,'2030-05-03 00:00:00']);
$service=new StockHistory($db); if ($service->product(999)!==null || (int)$service->product(1)['is_active']!==0) throw new RuntimeException('Produit introuvable ou inactif incorrect');
$all=StockHistory::filters([]); $first=$service->page(1,$all,1); $last=$service->page(1,$all,999);
if ($first['total']!==127 || $first['pages']!==7 || count($first['rows'])!==20 || (int)$first['rows'][0]['id']!==128 || $last['page']!==7 || (int)end($last['rows'])['id']!==1) throw new RuntimeException('Pagination ou tri incorrect');
$filtered=StockHistory::filters(['from'=>'2030-05-02','to'=>'2030-05-02','reason'=>'adjustment']); $page=$service->page(1,$filtered,1);
if ($page['total']!==1 || $page['rows'][0]['note']!=='<casse>' || $page['rows'][0]['author']!=='<Admin>') throw new RuntimeException('Filtres ou jointure incorrects');
$sales=$service->page(1,StockHistory::filters(['reason'=>'sale']),1); if ($sales['total']!==63 || !$sales['rows'][0]['sale_id']) throw new RuntimeException('Type vente incorrect');
foreach ([['from'=>'2030-02-30'],['to'=>'2100-01-01'],['from'=>'2030-05-03','to'=>'2030-05-02'],['reason'=>'sale OR 1=1'],['from'=>['x']]] as $bad) { try { StockHistory::filters($bad); throw new RuntimeException('Filtre invalide accepté'); } catch (DomainException $ex) {} }
echo "Stock : filtres, bornes inclusives, tri et historique complet paginé OK\n";
