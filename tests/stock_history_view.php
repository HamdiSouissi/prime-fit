<?php
declare(strict_types=1);
require __DIR__.'/../app/services/StockHistory.php';
function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); } function gym_name(): string { return 'Salle'; } function gym_brand_markup(): string { return 'Salle'; } function csrf(): string { return 'csrf'; }
$_SESSION=['user'=>['name'=>'Gérant','role'=>'Gérant']]; $error=''; $flash=''; $product=['id'=>1,'name'=>'<Eau>','sku'=>'E1','stock'=>5,'is_active'=>1]; $filters=['from'=>'2030-01-01','to'=>'2030-12-31','reason'=>'sale'];
$history=['rows'=>[['id'=>1,'created_at'=>'2030-05-01 15:30:00','reason'=>'sale','quantity_change'=>-2,'note'=>'<danger>','sale_id'=>12,'author'=>'<Alice>']],'total'=>21,'page'=>1,'pages'=>2];
ob_start(); require __DIR__.'/../app/views/stock_history.php'; $html=ob_get_clean();
foreach (['&lt;Eau&gt;','&lt;danger&gt;','&lt;Alice&gt;','01/05/2030 15:30','shop_receipt?id=12','stock_history?id=1&amp;from=2030-01-01&amp;to=2030-12-31&amp;reason=sale&amp;p=2','value="sale" selected','action="stock_history_export"','name="csrf" value="csrf"','name="reason" value="sale"'] as $v) if (!str_contains($html,$v)) throw new RuntimeException('Rendu absent : '.$v);
if (str_contains($html,'<danger>') || str_contains($html,'<Alice>')) throw new RuntimeException('Échappement incorrect');
echo "Stock : rendu, filtres conservés, reçu et échappement OK\n";
