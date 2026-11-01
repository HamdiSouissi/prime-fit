<?php
declare(strict_types=1);
require __DIR__.'/../app/services/MemberHistory.php';
class HistoryTestPDO extends PDO {
    public array $queries=[];
    public function prepare(string $sql,array $options=[]): PDOStatement|false { $this->queries[]=$sql; return parent::prepare($sql,$options); }
}
$db=new HistoryTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE members (id INTEGER PRIMARY KEY,first_name TEXT,last_name TEXT,card_number TEXT); CREATE TABLE plans (id INTEGER PRIMARY KEY,name TEXT); CREATE TABLE subscriptions (id INTEGER PRIMARY KEY,member_id INTEGER,plan_id INTEGER,start_date TEXT,end_date TEXT,status TEXT); CREATE TABLE payments (id INTEGER PRIMARY KEY,member_id INTEGER,subscription_id INTEGER,amount TEXT,method TEXT,paid_at TEXT); CREATE TABLE payment_refunds (id INTEGER PRIMARY KEY,payment_id INTEGER,amount DECIMAL(10,3)); CREATE TABLE attendance (id INTEGER PRIMARY KEY,member_id INTEGER,checked_in_at TEXT,result TEXT); INSERT INTO members VALUES (1,'<Ali>','Test','PF1'),(2,'Autre','Personne','PF2'); INSERT INTO plans VALUES (1,'<Mensuel>');");
for ($i=1;$i<=45;$i++) {
    $db->prepare('INSERT INTO subscriptions VALUES (?,?,?,?,?,?)')->execute([$i,1,1,'2026-01-01','2026-01-31','active']);
    $db->prepare('INSERT INTO payments VALUES (?,?,?,?,?,?)')->execute([$i,1,$i,'10.000','D17','2026-01-01 12:00:00']);
    $db->prepare('INSERT INTO attendance VALUES (?,?,?,?)')->execute([$i,1,'2026-01-01 12:00:00',$i%2 ? 'accepted' : 'refused']);
}
$db->exec("INSERT INTO subscriptions VALUES (99,2,1,'2026-01-01','2026-01-31','active'); INSERT INTO payments VALUES (99,2,99,'99','espèces','2026-01-01 12:00:00'); INSERT INTO attendance VALUES (99,2,'2026-01-01 12:00:00','accepted'); INSERT INTO payment_refunds VALUES (1,45,2.125),(2,45,1.000);");
$service=new MemberHistory($db);
foreach (['subscriptions','payments','attendance'] as $type) {
    $data=$service->get(1,'Admin',$type,1);
    if ($data['total']!==45 || $data['pages']!==3 || $data['page']!==1 || count($data['rows'])!==20 || (int)$data['rows'][0]['id']!==45 || (int)$data['rows'][19]['id']!==26) throw new RuntimeException('Première page incorrecte : '.$type);
    $last=$service->get(1,'Admin',$type,999); if ($last['page']!==3 || count($last['rows'])!==5 || (int)$last['rows'][4]['id']!==1) throw new RuntimeException('Dernière page incorrecte : '.$type);
}
if ((float)$service->get(1,'Admin','payments',1)['rows'][0]['refunded']!==3.125) throw new RuntimeException('Remboursement incorrect');
foreach ([['Coach','payments'],['Comptable','attendance'],['Inconnu','subscriptions'],['Admin','other']] as [$role,$type]) {
    $db->queries=[]; try { $service->get(1,$role,$type,1); throw new RuntimeException('Accès non autorisé'); } catch (DomainException $ex) {}
    if ($db->queries) throw new RuntimeException('Requête avant vérification des droits');
}
$db->queries=[]; $service->get(1,'Coach','attendance',-5); foreach ($db->queries as $q) if (stripos($q,'payments')!==false || stripos($q,'payment_refunds')!==false) throw new RuntimeException('Paiements interrogés pour un coach');
$db->queries=[]; $service->get(1,'Comptable','payments',1); foreach ($db->queries as $q) if (stripos($q,'attendance')!==false) throw new RuntimeException('Passages interrogés pour un comptable');
if ($service->get(999,'Admin','subscriptions',1)!==null || $service->get(2,'Coach','subscriptions',1)['total']!==1) throw new RuntimeException('Membre manquant ou isolation incorrecte');
echo "Historique membre : pagination, isolation, remboursements et droits OK\n";
