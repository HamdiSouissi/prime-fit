<?php
declare(strict_types=1);
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE products (id INTEGER PRIMARY KEY,name TEXT,sku TEXT,stock INTEGER,is_active INTEGER); CREATE TABLE users (id INTEGER PRIMARY KEY,name TEXT); CREATE TABLE stock_movements (id INTEGER PRIMARY KEY,product_id INTEGER,quantity_change INTEGER,reason TEXT,note TEXT,sale_id INTEGER,created_by INTEGER,created_at TEXT); CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT); INSERT INTO products VALUES (1,'Eau','E1',4,1); INSERT INTO stock_movements VALUES (1,1,-1,'sale',NULL,10,NULL,'2030-01-02 12:00:00'),(2,1,1,'restock',NULL,NULL,NULL,'2030-01-03 12:00:00')");
function db(): PDO { global $db; return $db; } function verify_csrf(): void { if (($_POST['csrf'] ?? '')!=='valid') throw new RuntimeException('CSRF'); } function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
require __DIR__.'/../app/controllers/StockHistoryController.php'; $_SESSION=['user'=>['id'=>9,'role'=>'Réceptionniste']]; $_SERVER['REQUEST_METHOD']='POST'; $_SERVER['REMOTE_ADDR']='127.0.0.1'; $_POST=['id'=>'1','csrf'=>'valid','from'=>'2030-01-02','to'=>'2030-01-02','reason'=>'sale'];
ob_start(); (new StockHistoryController())->export(); $csv=ob_get_clean();
if (!str_starts_with($csv,"\xEF\xBB\xBF") || !str_contains($csv,'02/01/2030') || str_contains($csv,'03/01/2030') || (int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn()!==1) throw new RuntimeException('Export ou audit incorrect');
$_POST['csrf']='bad'; try { (new StockHistoryController())->export(); throw new RuntimeException('CSRF non vérifié'); } catch (RuntimeException $ex) { if ($ex->getMessage()!=='CSRF') throw $ex; }
if ((int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn()!==1) throw new RuntimeException('Audit indésirable');
$db->exec('DROP TABLE activity_logs'); $_POST['csrf']='valid'; ob_start(); try { (new StockHistoryController())->export(); throw new RuntimeException('Audit ignoré'); } catch (PDOException $ex) {} $partial=ob_get_clean(); if ($partial!=='') throw new RuntimeException('CSV émis malgré un échec d’audit');
echo "Stock : contrôleur CSV, filtres, CSRF et audit OK\n";
