<?php
declare(strict_types=1);
require __DIR__.'/../app/models/Dashboard.php';
class DashboardTestPDO extends PDO {
    public array $queries=[];
    public function prepare(string $query,array $options=[]): PDOStatement|false { $this->queries[]=$query; return parent::prepare($query,$options); }
}
$db=new DashboardTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->sqliteCreateFunction('DATE_FORMAT',static fn($value,$format)=>substr($value,0,7),2);
$db->exec("CREATE TABLE members (id INTEGER PRIMARY KEY,first_name TEXT,last_name TEXT,phone TEXT,avatar_color TEXT,status TEXT); CREATE TABLE plans (id INTEGER PRIMARY KEY,name TEXT); CREATE TABLE subscriptions (id INTEGER PRIMARY KEY,member_id INTEGER,plan_id INTEGER,start_date TEXT,end_date TEXT,status TEXT,created_at TEXT); CREATE TABLE payment_refunds (id INTEGER PRIMARY KEY,payment_id INTEGER,amount DECIMAL(10,3),refunded_at TEXT); CREATE TABLE payments (id INTEGER PRIMARY KEY,amount DECIMAL(10,3),paid_at TEXT); CREATE TABLE attendance (id INTEGER PRIMARY KEY,result TEXT,checked_in_at TEXT); INSERT INTO members VALUES (1,'Amina','Test','216','gold','active'),(2,'Karim','Test','216','gold','active'),(3,'Inactif','Test',NULL,'gold','inactive'); INSERT INTO plans VALUES (1,'Mensuel'); INSERT INTO subscriptions VALUES (1,1,1,'2026-01-01','2026-02-02','active','2026-01-01'),(2,2,1,'2026-02-03','2026-02-08','active','2026-01-02'),(3,3,1,'2026-01-01','2026-02-03','active','2026-01-03'),(4,2,1,'2026-01-01','2026-01-30','frozen','2026-01-04'); INSERT INTO payments VALUES (1,12.345,'2026-02-01 10:00:00'),(2,3,'2026-02-02 00:00:00'),(3,99,'2026-01-31 23:59:59'),(4,7,'2026-03-01 00:00:00'); INSERT INTO payment_refunds VALUES (1,1,2,'2026-02-01 10:00:00'),(2,1,1,'2026-02-02 09:00:00'),(3,1,3,'2026-01-15 09:00:00'); INSERT INTO attendance VALUES (1,'accepted','2026-02-01 09:00:00'),(2,'refused','2026-02-01 10:00:00'),(3,'accepted','2026-02-02 00:00:00');");
$db->exec("CREATE TABLE rooms (id INTEGER PRIMARY KEY,name TEXT); CREATE TABLE courses (id INTEGER PRIMARY KEY,title TEXT,starts_at TEXT,room_id INTEGER,status TEXT); CREATE TABLE products (id INTEGER PRIMARY KEY,name TEXT,sku TEXT,stock INTEGER,low_stock_threshold INTEGER,is_active INTEGER); CREATE TABLE shop_sales (id INTEGER PRIMARY KEY,total DECIMAL(13,3),sold_at TEXT);");
$db->exec("INSERT INTO rooms VALUES (1,'Studio'); INSERT INTO courses VALUES (1,'Yoga <test>','2026-02-01 10:00:00',1,'scheduled'),(2,'Passé','2026-01-31 10:00:00',1,'scheduled'),(3,'Annulé','2026-02-02 10:00:00',1,'cancelled'),(4,'Loin','2026-02-09 10:00:00',1,'scheduled'); INSERT INTO products VALUES (1,'Boisson <test>','EAU',2,3,1),(2,'Inactif','OFF',0,5,0),(3,'Disponible','OK',10,2,1); INSERT INTO shop_sales VALUES (1,4.125,'2026-02-01 09:00:00'),(2,100,'2026-02-02 09:00:00');");
$model=new Dashboard($db); $date=new DateTimeImmutable('2026-02-01');
$db->queries=[]; $coach=$model->data('Coach',$date);
if (!$coach['canSeeCourses'] || $coach['canSeeShop'] || count($coach['upcomingCourses'])!==1 || isset($coach['shopToday']) || isset($coach['lowStock'])) throw new RuntimeException('Widgets coach incorrects');
if ($coach['active']!==1 || $coach['expiring']!==1 || $coach['visits']!==1 || isset($coach['today']) || isset($coach['values'])) throw new RuntimeException('Données coach incorrectes');
foreach ($db->queries as $sql) if (stripos($sql,'FROM products')!==false || stripos($sql,'FROM shop_sales')!==false) throw new RuntimeException('Boutique exposée au coach');
foreach ($db->queries as $sql) if ((stripos($sql,'FROM payments')!==false || stripos($sql,'FROM payment_refunds')!==false)) throw new RuntimeException('Requête financière exécutée pour le coach');
$db->queries=[]; $accountant=$model->data('Comptable',$date);
if ($accountant['canSeeCourses'] || !$accountant['canSeeShop'] || $accountant['lowStockCount']!==1 || $accountant['shopToday']!==4.125 || isset($accountant['upcomingCourses'])) throw new RuntimeException('Widgets comptable incorrects');
if ($accountant['today']!==10.345 || $accountant['month']!==12.345 || isset($accountant['visits']) || count($accountant['values'])!==6) throw new RuntimeException('Données comptable incorrectes');
foreach ($db->queries as $sql) if (stripos($sql,'FROM courses')!==false) throw new RuntimeException('Cours exposés au comptable');
foreach ($db->queries as $sql) if (stripos($sql,'FROM attendance')!==false) throw new RuntimeException('Requête de passages exécutée pour le comptable');
$admin=$model->data('Admin',$date); if ($admin['visits']!==1 || $admin['today']!==10.345 || $admin['values'][5]!==12.345 || $admin['values'][4]!==96.0) throw new RuntimeException('Agrégats ou périodes incorrects');
$late=$model->data('Admin',new DateTimeImmutable('2026-02-01 11:00:00')); if ($late['upcomingCourses']!==[] || $late['shopToday']!==4.125) throw new RuntimeException('Filtre horaire des cours ou ventes incorrect');
try { $model->data('Inconnu',$date); throw new RuntimeException('Rôle inconnu accepté'); } catch (DomainException $ex) {}
function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function gym_name(): string { return 'Salle de test'; }
function gym_brand_markup(): string { return e(gym_name()); }
function csrf(): string { return 'test'; }
function dt($v): string { return number_format((float)$v,3,',',' ').' DT'; }
$_SESSION=['user'=>['name'=>'Coach Test','role'=>'Coach']]; $data=$coach; ob_start(); require __DIR__.'/../app/views/dashboard.php'; $html=ob_get_clean();
if (!str_contains($html,'class="top-profile account-link" href="account"') || !str_contains($html,'class="header-logout" method="post" action="logout"') || !str_contains($html,'>CT</span>')) throw new RuntimeException('Compte ou déconnexion du header absents');
if (!str_contains($html,'class="icon-button site-home-link" href="./"') || !str_contains($html,'assets/favicon.svg')) throw new RuntimeException('Icône du site ou favicon absente');
if (!str_contains($html,'href="./"><i data-lucide="globe"') || !str_contains($html,'Voir le site principal')) throw new RuntimeException('Lien du site principal absent pour le coach');
if (!str_contains($html,'Yoga &lt;test&gt;') || str_contains($html,'Yoga <test>') || str_contains($html,'Stock &amp; boutique')) throw new RuntimeException('Widget coach incorrect ou non échappé');
if (str_contains($html,'revenueChart') || str_contains($html,'12,345') || str_contains($html,'"values"') || !str_contains($html,'id="planChart"') || str_contains($html,'href="payments"')) throw new RuntimeException('Données financières exposées au coach dans le rendu');
$_SESSION=['user'=>['name'=>'Comptable Test','role'=>'Comptable']]; $data=$accountant; ob_start(); require __DIR__.'/../app/views/dashboard.php'; $html=ob_get_clean();
if (!str_contains($html,'Voir le site principal')) throw new RuntimeException('Lien du site principal absent pour le comptable');
if (!str_contains($html,'Boisson &lt;test&gt;') || !str_contains($html,'4,125 DT') || str_contains($html,'Prochains cours')) throw new RuntimeException('Widget comptable incorrect');
if (str_contains($html,'Présences du jour') || str_contains($html,'href="marketing"') || !str_contains($html,'id="revenueChart"')) throw new RuntimeException('Rendu comptable incorrect');
$db->exec('DROP TABLE courses'); $db->exec('DROP TABLE shop_sales');
$partial=$model->data('Admin',$date);
if ($partial['upcomingCourses']!==null || $partial['lowStock']!==null || $partial['shopToday']!==null || $partial['today']!==10.345) throw new RuntimeException('Repli des modules indisponibles incorrect');
$_SESSION=['user'=>['name'=>'Admin Test','role'=>'Admin']]; $data=$partial; ob_start(); require __DIR__.'/../app/views/dashboard.php'; $html=ob_get_clean();
if (!str_contains($html,'Planning indisponible') || !str_contains($html,'Boutique indisponible')) throw new RuntimeException('Indisponibilité masquée');
echo "Tableau de bord : rôles, requêtes, périodes et rendus OK\n";
