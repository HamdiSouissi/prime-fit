<?php
declare(strict_types=1);
// Test autonome avec PDO SQLite ; le modèle utilise des requêtes compatibles MySQL et SQLite.
require __DIR__.'/../app/models/MonthlyReport.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE members (id INTEGER PRIMARY KEY,status TEXT);
CREATE TABLE subscriptions (id INTEGER PRIMARY KEY,member_id INTEGER,status TEXT,start_date TEXT,end_date TEXT);
CREATE TABLE payment_refunds (id INTEGER PRIMARY KEY,payment_id INTEGER,amount DECIMAL(10,3),refunded_at TEXT); CREATE TABLE payments (id INTEGER PRIMARY KEY,paid_at TEXT,amount DECIMAL(10,3));
CREATE TABLE shop_sales (id INTEGER PRIMARY KEY,sold_at TEXT,total DECIMAL(13,3));
CREATE TABLE expenses (id INTEGER PRIMARY KEY,expense_date TEXT,amount DECIMAL(10,3));
CREATE TABLE attendance (id INTEGER PRIMARY KEY,member_id INTEGER,checked_in_at TEXT,result TEXT);");
$db->exec("INSERT INTO members VALUES (1,'active'),(2,'active'),(3,'active'),(4,'blacklisted'),(5,'active');
INSERT INTO subscriptions VALUES (1,1,'active','2026-01-01','2026-02-28'),(2,2,'active','2026-01-01','2026-01-31'),(3,3,'active','2026-02-01','2026-02-28'),(4,4,'active','2026-01-01','2026-02-28'),(5,5,'frozen','2026-01-01','2026-02-28');
INSERT INTO payments VALUES (1,'2026-02-01 10:00:00',12.345),(2,'2026-02-01 11:00:00',30.000),(3,'2026-03-01 00:00:00',99.000);
INSERT INTO payment_refunds VALUES (1,1,2.345,'2026-02-02 10:00:00'),(2,1,1.000,'2026-03-01 00:00:00');
INSERT INTO shop_sales VALUES (1,'2026-02-01 12:00:00',2.500);
INSERT INTO expenses VALUES (1,'2026-02-01',5.100);
INSERT INTO attendance VALUES (1,1,'2026-02-01 09:00:00','accepted'),(2,1,'2026-02-01 11:00:00','accepted'),(3,3,'2026-02-02 09:00:00','accepted'),(4,4,'2026-02-02 09:00:00','accepted'),(5,5,'2026-02-02 09:00:00','accepted'),(6,2,'2026-02-02 09:00:00','refused');");
$r=(new MonthlyReport($db))->build(new DateTimeImmutable('2026-02-01'));
if (count($r['daily'])!==28 || $r['totals']['subscription']!==40000 || $r['totals']['shop']!==2500 || $r['totals']['expense']!==5100 || $r['totals']['balance']!==37400) throw new RuntimeException('Totaux financiers incorrects');
if ($r['daily']['2026-02-02']['subscription']!==-2345) throw new RuntimeException('Remboursement imputé au mauvais jour');
if ($r['totals']['visits']!==5 || $r['uniqueVisitors']!==4 || $r['daily']['2026-02-01']['visitors']!==1 || $r['daily']['2026-02-01']['visits']!==2) throw new RuntimeException('Présences incorrectes');
if ($r['prior']!==2 || $r['retained']!==1 || $r['eligible']!==2 || $r['attending']!==2) throw new RuntimeException('Cohortes incorrectes');
$empty=(new MonthlyReport($db))->build(new DateTimeImmutable('2024-02-01'));
if (count($empty['daily'])!==29 || $empty['prior']!==0 || $empty['eligible']!==0 || $empty['totals']['balance']!==0) throw new RuntimeException('Mois vide ou bissextile incorrect');
echo "Rapport mensuel : montants, dates, fréquentation et rétention estimées OK\n";
