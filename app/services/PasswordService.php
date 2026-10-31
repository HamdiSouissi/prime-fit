<?php
declare(strict_types=1);
/** Changement de mot de passe et journalisation dans une même transaction. */
class PasswordService {
    public function __construct(private PDO $db) {}
    public function change(int $userId,string $current,string $new,string $confirmation,string $ip): string {
        if (strlen($new)<12 || strlen($new)>72 || str_contains($new,"\0")) throw new DomainException('Le nouveau mot de passe doit contenir entre 12 et 72 octets, sans caractère nul.');
        if (!hash_equals($new,$confirmation)) throw new DomainException('La confirmation ne correspond pas au nouveau mot de passe.');
        if (strlen($current)>1024) throw new DomainException('Mot de passe actuel incorrect.');
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare('SELECT password_hash,is_active FROM users WHERE id=? FOR UPDATE');
            $stmt->execute([$userId]); $user=$stmt->fetch();
            if (!$user || !$user['is_active'] || !password_verify($current,$user['password_hash'])) throw new DomainException('Mot de passe actuel incorrect ou compte inactif.');
            if (password_verify($new,$user['password_hash'])) throw new DomainException('Choisissez un mot de passe différent du mot de passe actuel.');
            $hash=password_hash($new,PASSWORD_DEFAULT);
            if ($hash===false) throw new RuntimeException('Impossible de créer le mot de passe.');
            $this->db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash,$userId]);
            $this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$userId,'Mot de passe modifié',substr($ip,0,45)]);
            $this->db->commit(); return hash('sha256',$hash);
        } catch (Throwable $ex) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $ex; }
    }
}
