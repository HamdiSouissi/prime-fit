<?php
declare(strict_types=1);
require __DIR__.'/../app/services/StockHistoryCsv.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE products (id INTEGER PRIMARY KEY,name TEXT,sku TEXT,stock INTEGER,is_active INTEGER); CREATE TABLE users (id INTEGER PRIMARY KEY,name TEXT); CREATE TABLE stock_movements (id INTEGER PRIMARY KEY,product_id INTEGER,quantity_change INTEGER,reason TEXT,note TEXT,sale_id INTEGER,created_by INTEGER,created_at TEXT); INSERT INTO products VALUES (1,'=Eau','+CODE',5,1),(2,'Autre','REF',1,1); INSERT INTO users VALUES (1,'@Alice')");
$insert=$db->prepare('INSERT INTO stock_movements VALUES (?,?,?,?,?,?,?,?)');
for ($i=1;$i<=610;$i++) $insert->execute([$i,1,$i%2 ? -2 : 1,$i%2 ? 'sale' : 'adjustment',$i%2 ? null : "  =CMD;\nDanger",$i%2 ? $i : null,1,'2030-05-01 12:00:00']);
$insert->execute([611,2,1,'restock',null,null,null,'2030-05-01 12:00:00']);
$service=new StockHistory($db); $filters=StockHistory::filters(['from'=>'2030-05-01','to'=>'2030-05-01']); $ids=[];
$service->export(1,$filters,function($r) use (&$ids,$insert) { $ids[]=(int)$r['id']; if (count($ids)===1) $insert->execute([999,1,1,'restock',null,null,null,'2030-05-01 12:00:00']); });
if (count($ids)!==610 || count(array_unique($ids))!==610 || $ids[0]!==610 || end($ids)!==1 || in_array(999,$ids,true)) throw new RuntimeException('Lots, ordre ou borne incorrects');
$filter=StockHistory::filters(['from'=>'2030-05-01','to'=>'2030-05-01','reason'=>'adjustment']); $out=fopen('php://temp','w+'); StockHistoryCsv::write($out,$service,$service->product(1),$filter); rewind($out);
if (fread($out,3)!=="\xEF\xBB\xBF") throw new RuntimeException('BOM manquant');
$head=fgetcsv($out,0,';','"',''); $count=0; while (($row=fgetcsv($out,0,';','"',''))!==false) { $count++; if ($row[2]!=="'=Eau" || $row[3]!=="'+CODE" || $row[5]!=='Correction' || $row[6]!=='1' || $row[7]!=="'  =CMD;\nDanger" || $row[8]!=="'@Alice" || $row[9]!=='') throw new RuntimeException('CSV ou protection incorrecte'); }
if (count($head)!==10 || $count!==305) throw new RuntimeException('Export incomplet ou filtre incorrect'); fclose($out);
$empty=fopen('php://temp','w+'); StockHistoryCsv::write($empty,$service,$service->product(2),StockHistory::filters(['reason'=>'sale'])); rewind($empty); fread($empty,3); $head=fgetcsv($empty,0,';','"',''); if (count($head)!==10 || fgetcsv($empty,0,';','"','')!==false) throw new RuntimeException('Export vide incorrect'); fclose($empty);
foreach (["=1", "\ttexte", "\rtext", "  +1", "\xEF\xBB\xBF=1", " -1"] as $value) if (StockHistoryCsv::safe($value)!=="'".$value) throw new RuntimeException('Cellule non neutralisée');
if (StockHistoryCsv::safe('Eau')!=='Eau') throw new RuntimeException('Texte normal modifié');
echo "Stock : export filtré par lots, limite et protection des cellules CSV OK\n";
