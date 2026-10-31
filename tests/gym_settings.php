<?php
declare(strict_types=1);
// Test de validation et de transaction avec PDO SQLite ; les verrous MySQL nécessitent un test séparé.
require __DIR__.'/../app/models/GymSettings.php';
class SettingsTestPDO extends PDO {
    public function prepare(string $query,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); }
}
$db=new SettingsTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE gym_settings (id INTEGER PRIMARY KEY,phone TEXT,email TEXT,address TEXT,tax_id TEXT,opening_hours TEXT,vat_enabled INTEGER,vat_rate REAL,fiscal_stamp REAL,updated_by INTEGER); CREATE TABLE activity_logs (user_id INTEGER,action TEXT,ip_address TEXT); INSERT INTO gym_settings VALUES (1,'','','','','',0,19,1,NULL)");
$model=new GymSettings($db); $data=['phone'=>'+216 22 123 456','email'=>'accueil@example.tn','address'=>'Bir El Hafey','tax_id'=>'123/A','opening_hours'=>"Lundi : 08h – 22h\nDimanche : fermé",'vat_enabled'=>'1','vat_rate'=>'19,00','fiscal_stamp'=>'1,000'];
$model->save($data,1,'127.0.0.1'); $row=$model->get();
if ($row['phone']!==$data['phone'] || $row['address']!==$data['address'] || (int)$row['vat_enabled']!==1 || (float)$row['fiscal_stamp']!==1.0 || (int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn()!==1) throw new RuntimeException('Sauvegarde incorrecte');
foreach ([['vat_rate'=>'101'],['email'=>"a@example.tn\r\nBcc: x@example.tn"],['fiscal_stamp'=>'-1'],['phone'=>['bad']]] as $invalid) {
    try { $model->save(array_replace($data,$invalid),1,'127.0.0.1'); throw new RuntimeException('Validation absente'); } catch (DomainException $ex) {}
}
if ((int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn()!==1 || $model->get()['email']!==$data['email']) throw new RuntimeException('Modification invalide persistée');
$db->exec('DROP TABLE activity_logs');
try { $model->save(array_replace($data,['phone'=>'test']),1,'127.0.0.1'); throw new RuntimeException('Échec du journal ignoré'); } catch (PDOException $ex) {}
if ($model->get()['phone']!==$data['phone']) throw new RuntimeException('Transaction non annulée');
echo "Paramètres : validation, sauvegarde et rollback OK\n";
