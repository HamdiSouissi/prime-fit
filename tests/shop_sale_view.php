<?php
declare(strict_types=1);
require __DIR__.'/../app/services/ShopSaleService.php';
function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function dt($v): string { return number_format((float)$v,3,',',' ').' DT'; }
function gym_name(): string { return 'Salle'; } function gym_brand_markup(): string { return 'Salle'; } function csrf(): string { return 'csrf'; }
$_SESSION=['user'=>['name'=>'Admin','role'=>'Admin']]; $_POST=['product_id'=>'1|ancien'];
$canSell=true; $canManage=false; $error=''; $flash=''; $requestToken=str_repeat('a',64);
$products=[['id'=>1,'name'=>'<Eau>','sku'=>'A1','price'=>'1.500','stock'=>2,'low_stock_threshold'=>0,'is_active'=>1]];
$selectedProduct=null; $sales=[]; $total=0; $salesTotal='0.000'; $date='2030-01-01'; $pageNumber=1;
ob_start(); require __DIR__.'/../app/views/shop.php'; $html=ob_get_clean();
foreach (['name="request_token"','value="'.$requestToken.'"','1|'.ShopSaleService::productVersion($products[0]),'&lt;Eau&gt;','1,500 DT','selected'] as $needle) if (!str_contains($html,$needle)) throw new RuntimeException('Formulaire incomplet : '.$needle);
if (str_contains($html,'<Eau>')) throw new RuntimeException('Nom non échappé');
$canSell=false; $_SESSION['user']['role']='Comptable'; ob_start(); require __DIR__.'/../app/views/shop.php'; $html=ob_get_clean(); if (str_contains($html,'name="request_token"') || str_contains($html,'name="action" value="sale"')) throw new RuntimeException('Formulaire de vente visible pour Comptable');
echo "Vente boutique : formulaire versionné, jeton, droits et échappement OK\n";
