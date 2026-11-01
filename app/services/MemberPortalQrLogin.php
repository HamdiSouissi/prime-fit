<?php
declare(strict_types=1);
/** Le QR de pointage seul ne connecte jamais : un lien e-mail à usage unique est nécessaire. */
final class MemberPortalQrLogin {
    public function __construct(private PDO $db) {}
    /** La réponse null ne doit pas révéler si le QR ou le compte existe. */
    public function issue(string $qr,?DateTimeImmutable $now=null): ?array {
        if (!preg_match('/\APF1:([a-f0-9]{64})\z/D',$qr,$matches)) return null;
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare('SELECT id FROM members WHERE qr_token=?'); $stmt->execute([$matches[1]]); $id=$stmt->fetchColumn();
            if (!$id) { $this->db->commit(); return null; }
            $stmt=$this->db->prepare('SELECT first_name,last_name,email,status,qr_token FROM members WHERE id=? FOR UPDATE'); $stmt->execute([(int)$id]); $member=$stmt->fetch();
            $stmt=$this->db->prepare('SELECT email,password_hash FROM member_portal_accounts WHERE member_id=? FOR UPDATE'); $stmt->execute([(int)$id]); $account=$stmt->fetch();
            if (!$member || $member['status']!=='active' || !is_string($member['qr_token']) || !hash_equals($member['qr_token'],$matches[1]) || !$account || !is_string($account['password_hash']) || trim((string)$member['email'])!==$account['email']) { $this->db->commit(); return null; }
            $now=$now ?? new DateTimeImmutable(); $token=bin2hex(random_bytes(32));
            $this->db->prepare('DELETE FROM member_qr_logins WHERE member_id=?')->execute([(int)$id]);
            $this->db->prepare('INSERT INTO member_qr_logins (member_id,token_hash,account_fingerprint,qr_fingerprint,email,expires_at) VALUES (?,?,?,?,?,?)')->execute([(int)$id,hash('sha256',$token),hash('sha256',$account['password_hash']),hash('sha256',$member['qr_token']),$account['email'],$now->modify('+15 minutes')->format('Y-m-d H:i:s')]);
            $this->db->commit();
            return ['email'=>$account['email'],'first_name'=>$member['first_name'],'last_name'=>$member['last_name'],'token'=>$token];
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
    /** POST confirmé par l'utilisateur, après réception du lien ; ne pas appeler lors d'un GET. */
    public function confirm(string $token,string $ip,?DateTimeImmutable $now=null): array {
        if (!preg_match('/\A[a-f0-9]{64}\z/D',$token)) throw new DomainException('Lien invalide ou expiré.');
        $this->db->beginTransaction();
        try {
            $tokenHash=hash('sha256',$token); $stmt=$this->db->prepare('SELECT member_id FROM member_qr_logins WHERE token_hash=?'); $stmt->execute([$tokenHash]); $id=$stmt->fetchColumn();
            if (!$id) throw new DomainException('Lien invalide ou expiré.');
            $stmt=$this->db->prepare('SELECT email,status,qr_token FROM members WHERE id=? FOR UPDATE'); $stmt->execute([(int)$id]); $member=$stmt->fetch();
            $stmt=$this->db->prepare('SELECT email,password_hash FROM member_portal_accounts WHERE member_id=? FOR UPDATE'); $stmt->execute([(int)$id]); $account=$stmt->fetch();
            $stmt=$this->db->prepare('SELECT token_hash,account_fingerprint,qr_fingerprint,email,expires_at FROM member_qr_logins WHERE member_id=? FOR UPDATE'); $stmt->execute([(int)$id]); $request=$stmt->fetch();
            $now=$now ?? new DateTimeImmutable();
            if (!$member || $member['status']!=='active' || !is_string($member['qr_token']) || !$account || !is_string($account['password_hash']) || !$request || !hash_equals($request['token_hash'],$tokenHash) || $request['expires_at']<=$now->format('Y-m-d H:i:s') || $request['email']!==$account['email'] || trim((string)$member['email'])!==$account['email'] || !hash_equals($request['account_fingerprint'],hash('sha256',$account['password_hash'])) || !hash_equals($request['qr_fingerprint'],hash('sha256',$member['qr_token']))) throw new DomainException('Lien invalide ou expiré.');
            $this->db->prepare('DELETE FROM member_qr_logins WHERE member_id=?')->execute([(int)$id]);
            $this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (NULL,?,?)')->execute(['Espace membre : connexion QR confirmée membre #'.(int)$id,substr($ip,0,45)]);
            $this->db->commit(); return ['member_id'=>(int)$id,'fingerprint'=>hash('sha256',$account['password_hash'])];
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
}
