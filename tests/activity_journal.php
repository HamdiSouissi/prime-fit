<?php
declare(strict_types=1);
require __DIR__.'/../app/models/ActivityJournal.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY,name TEXT); CREATE TABLE activity_logs (id INTEGER PRIMARY KEY,user_id INTEGER,action TEXT,ip_address TEXT,created_at TEXT);');
$db->exec("INSERT INTO users VALUES (1,'Admin Test'),(2,'Agent');");
$insert=$db->prepare('INSERT INTO activity_logs VALUES (?,?,?,?,?)');
for ($i=1;$i<=610;$i++) $insert->execute([$i,($i%2)+1,$i===3?'=Action dangereuse':'Connexion','127.0.0.1','2026-02-01 10:00:00']);
$insert->execute([611,null,'Ancienne action',null,'2026-02-02 15:00:00']);
$insert->execute([612,1,'Hors période','127.0.0.1','2026-02-03 00:00:00']);
$journal=new ActivityJournal($db); $filters=ActivityJournal::filters(['start'=>'2026-02-01','end'=>'2026-02-02']);
$page=$journal->page($filters,1); if ($page['total']!==611 || count($page['rows'])!==30 || (int)$page['rows'][0]['id']!==611 || (int)$page['rows'][1]['id']!==610) throw new RuntimeException('Recherche ou tri incorrect');
if ($journal->page($filters,999)['page']!==21) throw new RuntimeException('Pagination incorrecte');
$ids=[]; $journal->export($filters,static function($row) use (&$ids) { $ids[]=(int)$row['id']; });
if (count($ids)!==611 || count(array_unique($ids))!==611 || $ids[0]!==611 || end($ids)!==1) throw new RuntimeException('Export incomplet ou doublonné');
$f=ActivityJournal::filters(['start'=>'2026-02-01','end'=>'2026-02-02','user'=>'deleted']); if ($journal->page($f,1)['total']!==1) throw new RuntimeException('Filtre utilisateur supprimé incorrect');
$f=ActivityJournal::filters(['start'=>'2026-02-01','end'=>'2026-02-02','q'=>'dangereuse','user'=>'2']); if ($journal->page($f,1)['total']!==1) throw new RuntimeException('Filtres combinés incorrects');
foreach ([['start'=>'2025-01-01','end'=>'2026-01-02'],['start'=>'2026-02-30','end'=>'2026-03-01'],['start'=>'2026-02-03','end'=>'2026-02-01'],['user'=>'-1'],['q'=>str_repeat('a',121)],['user'=>['1']]] as $bad) { try { ActivityJournal::filters($bad); throw new RuntimeException('Filtre invalide accepté'); } catch (DomainException $ex) {} }
foreach (['Coach','Gérant','Comptable','Réceptionniste'] as $role) { try { ActivityJournal::authorize($role); throw new RuntimeException('Rôle non autorisé accepté'); } catch (DomainException $ex) {} }
ActivityJournal::authorize('Admin'); if (ActivityJournal::csvSafe('  =1+1')!=="'  =1+1" || ActivityJournal::csvSafe('+216')!=="'+216" || ActivityJournal::csvSafe('Connexion')!=='Connexion') throw new RuntimeException('Protection CSV incorrecte');
echo "Journal : filtres, pagination, export par lots, rôles et protection CSV OK\n";
