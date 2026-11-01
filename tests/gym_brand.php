<?php
declare(strict_types=1);
require __DIR__.'/../app/services/GymBrand.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
if (GymBrand::resolve($db,'Salle par défaut')!=='Salle par défaut') throw new RuntimeException('Repli sans migration incorrect');
$db->exec("CREATE TABLE gym_settings (id INTEGER PRIMARY KEY,gym_name TEXT); INSERT INTO gym_settings VALUES (1,NULL)");
if (GymBrand::resolve($db,'Salle par défaut')!=='Salle par défaut') throw new RuntimeException('Repli avec nom non défini incorrect');
$db->exec("UPDATE gym_settings SET gym_name='Énergie & Sport' WHERE id=1");
if (GymBrand::resolve($db,'Ancien nom')!=='Énergie & Sport') throw new RuntimeException('Nom enregistré non utilisé');
foreach (['',"A\r\nBcc: bad",str_repeat('a',121),['bad']] as $invalid) if (GymBrand::clean($invalid)!==null) throw new RuntimeException('Nom invalide accepté');
echo "Identité : nom enregistré, validation et repli sans migration OK\n";
