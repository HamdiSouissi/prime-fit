<?php
declare(strict_types=1);
/** Identité indépendante du personnel. Le contrôleur doit limiter toutes les tentatives. */
final class MemberPortalAuth {
    private const DUMMY_HASH='$2y$12$n0iQ5q6SVqUn8R.RruoQ0eYnbRc2Yly.bxm8Na3OpO1cg96.2DIoe';
    public function __construct(private PDO $db) {}
    public function authenticate(string $email,string $password): ?array {
        $validEmail=strlen($email)<=190 && filter_var($email,FILTER_VALIDATE_EMAIL)!==false;
        $validPassword=strlen($password)<=1024;
        $stmt=$this->db->prepare('SELECT a.member_id,a.email,a.password_hash,m.email AS member_email,m.status FROM member_portal_accounts a JOIN members m ON m.id=a.member_id WHERE a.email=? LIMIT 1');
        $stmt->execute([$validEmail ? $email : '']); $row=$stmt->fetch();
        $hash=($row && is_string($row['password_hash'])) ? $row['password_hash'] : self::DUMMY_HASH;
        $valid=password_verify($validPassword ? $password : '',$hash);
        if (!$validEmail || !$validPassword || !$row || $row['status']!=='active' || !is_string($row['password_hash']) || trim((string)$row['member_email'])!==$row['email'] || !$valid) return null;
        return ['member_id'=>(int)$row['member_id'],'fingerprint'=>hash('sha256',$row['password_hash'])];
    }
    /** Revalidation à chaque requête, y compris après désactivation ou changement d'e-mail. */
    public function validSession($session): bool {
        if (!is_array($session) || !isset($session['member_id'],$session['fingerprint']) || !is_int($session['member_id']) || $session['member_id']<1 || !is_string($session['fingerprint'])) return false;
        $stmt=$this->db->prepare('SELECT a.email,a.password_hash,m.email AS member_email,m.status FROM member_portal_accounts a JOIN members m ON m.id=a.member_id WHERE a.member_id=?');
        $stmt->execute([$session['member_id']]); $row=$stmt->fetch();
        return $row && $row['status']==='active' && is_string($row['password_hash']) && trim((string)$row['member_email'])===$row['email'] && hash_equals(hash('sha256',$row['password_hash']),$session['fingerprint']);
    }
}
