<?php
declare(strict_types=1);
/** Réservation atomique d'une tentative avant vérification du mot de passe. */
class LoginRateLimiter {
    private const WINDOW=900;
    private const ACCOUNT_LIMIT=5;
    private const IP_LIMIT=60;
    public function __construct(private PDO $db) {}
    /** REMOTE_ADDR uniquement : ne pas faire confiance aux en-têtes envoyés par un client. */
    public static function clientAddress(string $address): string {
        $packed=@inet_pton($address);
        return $packed===false ? 'unknown' : inet_ntop($packed);
    }
    /** Identité basée sur l'identifiant réel du compte lorsqu'il existe. */
    public static function accountKey(?int $userId,string $email,string $address): string {
        if ($userId!==null) return 'user:'.$userId;
        return $email!=='' ? 'unknown:'.mb_strtolower($email,'UTF-8') : 'invalid:'.self::clientAddress($address);
    }
    /** Renvoie 0 si autorisé, sinon les secondes restantes. Toutes les tentatives sont comptées, même réussies. */
    public function reserve(string $address,string $account,?int $now=null): int {
        $now=$now ?? time();
        $buckets=[hash('sha256','ip:'.self::clientAddress($address))=>self::IP_LIMIT, hash('sha256','account:'.$account)=>self::ACCOUNT_LIMIT];
        ksort($buckets,SORT_STRING); // Ordre constant pour éviter les inversions de verrous.
        $this->db->beginTransaction();
        try {
            $insert=$this->db->prepare('INSERT INTO auth_rate_limits (bucket_key,window_started,attempts) VALUES (?,?,0) ON DUPLICATE KEY UPDATE bucket_key=bucket_key');
            $select=$this->db->prepare('SELECT window_started,attempts FROM auth_rate_limits WHERE bucket_key=? FOR UPDATE');
            $state=[]; $retry=0;
            foreach ($buckets as $key=>$limit) {
                $insert->execute([$key,$now]); $select->execute([$key]); $row=$select->fetch();
                if (!$row) throw new RuntimeException('Compteur de sécurité introuvable.');
                $start=(int)$row['window_started']; $attempts=(int)$row['attempts'];
                if ($now-$start>=self::WINDOW) { $start=$now; $attempts=0; }
                if ($attempts >= $limit) $retry=max($retry,$start+self::WINDOW-$now);
                $state[$key]=[$start,$attempts];
            }
            if ($retry===0) {
                $update=$this->db->prepare('UPDATE auth_rate_limits SET window_started=?,attempts=? WHERE bucket_key=?');
                foreach ($state as $key=>[$start,$attempts]) $update->execute([$start,$attempts+1,$key]);
            }
            $this->db->commit(); return $retry;
        } catch (Throwable $ex) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $ex; }
    }
}
