<?php
declare(strict_types=1);
/** Réinitialisation à usage unique pour les comptes membres déjà activés. */
final class MemberPortalPasswordReset {
    public function __construct(private PDO $db) {}
    /** Null pour un compte inconnu ou non éligible. Ne jamais révéler ce résultat à l'utilisateur. */
    public function issue(string $email,?DateTimeImmutable $now=null): ?array {
        if (strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL)) return null;
        $this->db->beginTransaction();
        try {
            // Recherche sans verrou : le verrou membre précède celui du compte et de la demande.
            $stmt=$this->db->prepare('SELECT member_id FROM member_portal_accounts WHERE email=?'); $stmt->execute([$email]); $id=$stmt->fetchColumn();
            if (!$id) { $this->db->commit(); return null; }
            $stmt=$this->db->prepare('SELECT id,first_name,last_name,email,status FROM members WHERE id=? FOR UPDATE'); $stmt->execute([(int)$id]); $member=$stmt->fetch();
            $stmt=$this->db->prepare('SELECT email,password_hash FROM member_portal_accounts WHERE member_id=? FOR UPDATE'); $stmt->execute([(int)$id]); $account=$stmt->fetch();
            if (!$member || !$account || $member['status']!=='active' || !is_string($account['password_hash']) || trim((string)$member['email'])!==$account['email']) { $this->db->commit(); return null; }
            // L'adresse peut avoir changé entre la recherche et le verrou : comparer selon la collation de la base.
            $stmt=$this->db->prepare('SELECT member_id FROM member_portal_accounts WHERE member_id=? AND email=? FOR UPDATE'); $stmt->execute([(int)$id,$email]);
            if (!$stmt->fetch()) { $this->db->commit(); return null; }
            $now=$now ?? new DateTimeImmutable(); $token=bin2hex(random_bytes(32));
            $this->db->prepare('DELETE FROM member_password_resets WHERE member_id=?')->execute([(int)$id]);
            $this->db->prepare('INSERT INTO member_password_resets (member_id,token_hash,account_fingerprint,email,expires_at) VALUES (?,?,?,?,?)')->execute([(int)$id,hash('sha256',$token),hash('sha256',$account['password_hash']),$account['email'],$now->modify('+1 hour')->format('Y-m-d H:i:s')]);
            $this->db->commit();
            return ['email'=>$account['email'],'first_name'=>$member['first_name'],'last_name'=>$member['last_name'],'token'=>$token];
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
    public function reset(string $token,string $password,string $confirmation,string $ip,?DateTimeImmutable $now=null): void {
        if (!preg_match('/\A[a-f0-9]{64}\z/D',$token)) throw new DomainException('Lien invalide ou expiré.');
        if (strlen($password)<12 || strlen($password)>72 || str_contains($password,"\0")) throw new DomainException('Le mot de passe doit contenir entre 12 et 72 octets, sans caractère nul.');
        if (!hash_equals($password,$confirmation)) throw new DomainException('La confirmation ne correspond pas au mot de passe.');
        $this->db->beginTransaction();
        try {
            $hash=hash('sha256',$token);
            $stmt=$this->db->prepare('SELECT member_id FROM member_password_resets WHERE token_hash=?'); $stmt->execute([$hash]); $id=$stmt->fetchColumn();
            if (!$id) throw new DomainException('Lien invalide ou expiré.');
            $stmt=$this->db->prepare('SELECT email,status FROM members WHERE id=? FOR UPDATE'); $stmt->execute([(int)$id]); $member=$stmt->fetch();
            $stmt=$this->db->prepare('SELECT email,password_hash FROM member_portal_accounts WHERE member_id=? FOR UPDATE'); $stmt->execute([(int)$id]); $account=$stmt->fetch();
            $stmt=$this->db->prepare('SELECT token_hash,email,account_fingerprint,expires_at FROM member_password_resets WHERE member_id=? FOR UPDATE'); $stmt->execute([(int)$id]); $reset=$stmt->fetch();
            $now=$now ?? new DateTimeImmutable();
            if (!$member || $member['status']!=='active' || !$account || !is_string($account['password_hash']) || !$reset || !hash_equals($reset['token_hash'],$hash) || $reset['expires_at']<=$now->format('Y-m-d H:i:s') || $reset['email']!==$account['email'] || trim((string)$member['email'])!==$account['email'] || !hash_equals($reset['account_fingerprint'],hash('sha256',$account['password_hash']))) throw new DomainException('Lien invalide ou expiré.');
            $newHash=password_hash($password,PASSWORD_DEFAULT); if ($newHash===false) throw new RuntimeException('Impossible de créer le mot de passe.');
            $this->db->prepare('UPDATE member_portal_accounts SET password_hash=? WHERE member_id=?')->execute([$newHash,(int)$id]);
            $this->db->prepare('DELETE FROM member_password_resets WHERE member_id=?')->execute([(int)$id]);
            $this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (NULL,?,?)')->execute(['Espace membre : mot de passe réinitialisé membre #'.(int)$id,substr($ip,0,45)]);
            $this->db->commit();
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
}
