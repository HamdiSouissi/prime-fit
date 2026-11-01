<?php
declare(strict_types=1);
class MarketingTestPDO extends PDO {
    public function prepare(string $query,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(['BINARY ','CURDATE()+INTERVAL 7 DAY'],['',"DATE(CURDATE(), '+7 day')"],$query),$options); }
    public function query(string $query, ?int $fetchMode=null, mixed ...$fetchModeArgs): PDOStatement|false {
        $query=str_replace(['BINARY ','CURDATE()+INTERVAL 7 DAY'],['',"DATE(CURDATE(), '+7 day')"],$query);
        return $fetchMode===null ? parent::query($query) : parent::query($query,$fetchMode,...$fetchModeArgs);
    }
}
$db=new MarketingTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->sqliteCreateFunction('CURDATE',fn()=> '2030-05-01',0); $db->sqliteCreateFunction('MONTH',fn($x)=> (int)substr((string)$x,5,2),1); $db->sqliteCreateFunction('DAY',fn($x)=> (int)substr((string)$x,8,2),1);
$db->exec("CREATE TABLE promotions (id INTEGER PRIMARY KEY,created_at TEXT); CREATE TABLE members (id INTEGER PRIMARY KEY,first_name TEXT,last_name TEXT,phone TEXT,birth_date TEXT,status TEXT); CREATE TABLE subscriptions (id INTEGER PRIMARY KEY,member_id INTEGER,plan_id INTEGER,status TEXT,end_date TEXT); CREATE TABLE plans (id INTEGER PRIMARY KEY,name TEXT); CREATE TABLE marketing_consent_events (id INTEGER PRIMARY KEY,member_id INTEGER,decision TEXT,phone_value TEXT); INSERT INTO members VALUES (1,'Autorise','Test','12345678','2000-05-01','active'),(2,'Retire','Test','12345678','2000-05-01','active'),(3,'Absent','Test','12345678','2000-05-01','active'),(4,'Change','Test','87654321','2000-05-01','active'),(5,'Inactif','Test','12345678','2000-05-01','inactive'); INSERT INTO marketing_consent_events VALUES (1,1,'accepted','12345678'),(2,2,'accepted','12345678'),(3,2,'revoked','12345678'),(4,4,'accepted','12345678'),(5,5,'accepted','12345678'); INSERT INTO plans VALUES (1,'Mensuel'); INSERT INTO subscriptions VALUES (1,1,1,'active','2030-05-04'),(2,2,1,'active','2030-05-04'),(3,3,1,'active','2030-05-04'),(4,4,1,'active','2030-05-04'),(5,5,1,'active','2030-05-04');");
function db(): PDO { global $db; return $db; } function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); } function gym_name(): string { return 'Salle'; } function gym_brand_markup(): string { return 'Salle'; } function csrf(): string { return 'test'; }
require __DIR__.'/../app/controllers/MarketingController.php';
$_SESSION=['user'=>['id'=>1,'name'=>'Admin','role'=>'Admin']]; $_GET=[]; $_POST=[]; $_SERVER['REQUEST_METHOD']='GET';
ob_start(); (new MarketingController())->index(); $html=ob_get_clean();
if (substr_count($html,'Autorise Test')!==2 || str_contains($html,'Retire Test') || str_contains($html,'Absent Test') || str_contains($html,'Change Test') || str_contains($html,'Inactif Test')) throw new RuntimeException('Filtre de consentement insuffisant');
if (!str_contains($html,'page=marketing_whatsapp&amp;kind=birthday&amp;id=1') || !str_contains($html,'page=marketing_whatsapp&amp;kind=expiry&amp;id=1') || str_contains($html,'wa.me/')) throw new RuntimeException('Les liens doivent être revérifiés sur le serveur');
if (!str_starts_with((string)MarketingController::whatsapp('+216 12 345 678','Bonjour'),'https://wa.me/21612345678?text=') || MarketingController::whatsapp('123','X')!==null) throw new RuntimeException('Numéro non validé');
$controller=new MarketingController();
if (!$controller->resolveWhatsapp(1,'birthday') || !$controller->resolveWhatsapp(1,'expiry') || $controller->resolveWhatsapp(2,'birthday')!==null || $controller->resolveWhatsapp(3,'expiry')!==null || $controller->resolveWhatsapp(4,'birthday')!==null) throw new RuntimeException('Vérification au clic incorrecte');
$db->exec("INSERT INTO marketing_consent_events VALUES (6,1,'revoked','12345678')");
if ($controller->resolveWhatsapp(1,'birthday')!==null || $controller->resolveWhatsapp(1,'expiry')!==null) throw new RuntimeException('Retrait non pris en compte au clic');
echo "Marketing : listes par consentement, statut, numéro et liens revérifiés OK\n";
