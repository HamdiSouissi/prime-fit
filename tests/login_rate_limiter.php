<?php
declare(strict_types=1);
require __DIR__.'/../app/services/LoginRateLimiter.php';
class LoginLimitTestPDO extends PDO {
    public function prepare(string $query,array $options=[]): PDOStatement|false {
        $query=str_replace(' ON DUPLICATE KEY UPDATE bucket_key=bucket_key',' ON CONFLICT(bucket_key) DO NOTHING',$query);
        return parent::prepare(str_replace(' FOR UPDATE','',$query),$options);
    }
}
$db=new LoginLimitTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE auth_rate_limits (bucket_key TEXT PRIMARY KEY,window_started INTEGER NOT NULL,attempts INTEGER NOT NULL)');
$limit=new LoginRateLimiter($db); $account=LoginRateLimiter::accountKey(7,'anything','127.0.0.1');
for ($i=0;$i<5;$i++) if ($limit->reserve('192.0.2.'.($i+1),$account,1000)!==0) throw new RuntimeException('Tentative prématurément bloquée');
if ($limit->reserve('192.0.2.6',$account,1001)!==899) throw new RuntimeException('Compte non limité entre sessions et IP');
if ($limit->reserve('192.0.2.6',$account,1900)!==0) throw new RuntimeException('Expiration de fenêtre incorrecte');
for ($i=0;$i<60;$i++) if ($limit->reserve('198.51.100.1','unknown:'.$i,2000)!==0) throw new RuntimeException('IP bloquée prématurément');
if ($limit->reserve('198.51.100.1','unknown:other',2001)!==899) throw new RuntimeException('Limite IP non appliquée');
if ($limit->reserve('198.51.100.1','unknown:other',2900)!==0) throw new RuntimeException('Limite IP non expirée');
if (LoginRateLimiter::clientAddress('2001:0db8:0:0::1')!==LoginRateLimiter::clientAddress('2001:db8::1')) throw new RuntimeException('IPv6 non normalisée');
if (LoginRateLimiter::clientAddress('not-an-ip')!=='unknown') throw new RuntimeException('Adresse invalide non traitée');
if (LoginRateLimiter::accountKey(7,'variant@example.tn','')!==$account || LoginRateLimiter::accountKey(null,'EXAMPLE@EXAMPLE.TN','')!==LoginRateLimiter::accountKey(null,'example@example.tn','')) throw new RuntimeException('Identité de compteur incorrecte');
foreach ($db->query('SELECT bucket_key FROM auth_rate_limits')->fetchAll(PDO::FETCH_COLUMN) as $key) if (!preg_match('/^[a-f0-9]{64}$/D',$key)) throw new RuntimeException('Clé non hachée');
$db->exec('CREATE TRIGGER fail_counter BEFORE UPDATE ON auth_rate_limits BEGIN SELECT RAISE(ABORT, "failure"); END'); $before=(int)$db->query('SELECT COUNT(*) FROM auth_rate_limits')->fetchColumn();
try { $limit->reserve('203.0.113.55','unknown:rollback',3000); throw new RuntimeException('Erreur non propagée'); } catch (PDOException $ex) {}
if ($db->inTransaction() || (int)$db->query('SELECT COUNT(*) FROM auth_rate_limits')->fetchColumn()!==$before) throw new RuntimeException('Rollback non effectué');
echo "Connexion : limites compte/IP persistantes, fenêtres et rollback OK\n";
