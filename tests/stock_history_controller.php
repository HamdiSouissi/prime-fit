<?php
declare(strict_types=1);
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE products (id INTEGER PRIMARY KEY,name TEXT,sku TEXT,stock INTEGER,is_active INTEGER); CREATE TABLE users (id INTEGER PRIMARY KEY,name TEXT); CREATE TABLE stock_movements (id INTEGER PRIMARY KEY,product_id INTEGER,quantity_change INTEGER,reason TEXT,note TEXT,sale_id INTEGER,created_by INTEGER,created_at TEXT); INSERT INTO products VALUES (1,'Eau','E1',10,1); INSERT INTO stock_movements VALUES (1,1,3,'restock',NULL,NULL,NULL,'2030-03-01 10:00:00')");
function db(): PDO { global $db; return $db; } function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); } function gym_name(): string { return 'Salle'; } function gym_brand_markup(): string { return 'Salle'; } function csrf(): string { return 'csrf'; }
require __DIR__.'/../app/controllers/StockHistoryController.php'; $_SESSION=['user'=>['id'=>1,'role'=>'Comptable','name'=>'Comptable']]; $_SERVER['REQUEST_METHOD']='GET'; $_GET=['id'=>'1','from'=>'2030-03-01','to'=>'2030-03-01','reason'=>'restock'];
ob_start(); (new StockHistoryController())->index(); $html=ob_get_clean(); if (!str_contains($html,'Réapprovisionnement') || !str_contains($html,'01/03/2030 10:00') || !str_contains($html,'1 mouvement(s)')) throw new RuntimeException('Historique non rendu');
$_GET['from']='2030-02-30'; ob_start(); (new StockHistoryController())->index(); $html=ob_get_clean(); if (http_response_code()!==422 || !str_contains($html,'dates entre 1900 et 2099') || str_contains($html,'01/03/2030 10:00')) throw new RuntimeException('Filtre invalide non signalé');
echo "Stock : contrôleur, filtres et erreurs OK\n";
