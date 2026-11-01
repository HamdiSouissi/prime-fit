<?php
declare(strict_types=1);
/** Limite indépendante du login, partagée entre sessions. Utilise la table des limites existante. */
final class ContactRateLimiter {
    public function __construct(private PDO $db) {}
    public function reserve(string $address, ?int $now=null): bool {
        $now=$now ?? time(); $packed=@inet_pton($address); $ip=$packed===false ? 'unknown' : inet_ntop($packed);
        $buckets=[hash('sha256','contact-ip:'.$ip)=>5, hash('sha256','contact-global')=>100];
        ksort($buckets,SORT_STRING);
        $sqlite=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        $this->db->beginTransaction();
        try {
            $insert=$this->db->prepare($sqlite ? 'INSERT OR IGNORE INTO auth_rate_limits (bucket_key,window_started,attempts) VALUES (?,?,0)' : 'INSERT INTO auth_rate_limits (bucket_key,window_started,attempts) VALUES (?,?,0) ON DUPLICATE KEY UPDATE bucket_key=bucket_key');
            $select=$this->db->prepare('SELECT window_started,attempts FROM auth_rate_limits WHERE bucket_key=?'.($sqlite ? '' : ' FOR UPDATE'));
            $state=[]; $allowed=true;
            foreach ($buckets as $key=>$limit) {
                $insert->execute([$key,$now]); $select->execute([$key]); $row=$select->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new RuntimeException('Compteur de contact indisponible.');
                $start=(int)$row['window_started']; $count=(int)$row['attempts'];
                if ($now-$start>=900 || $now<$start) { $start=$now; $count=0; }
                if ($count >= $limit) $allowed=false;
                $state[$key]=[$start,$count];
            }
            if ($allowed) {
                $update=$this->db->prepare('UPDATE auth_rate_limits SET window_started=?,attempts=? WHERE bucket_key=?');
                foreach ($state as $key=>[$start,$count]) $update->execute([$start,$count+1,$key]);
            }
            $this->db->commit(); return $allowed;
        } catch (Throwable $ex) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $ex; }
    }
}
