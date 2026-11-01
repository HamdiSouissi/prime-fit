<?php
declare(strict_types=1);
/** Retourne uniquement la carte du membre dont la session est fournie par le serveur. */
final class MemberPortalCard {
    public function __construct(private PDO $db) {}
    public function get(array $session): array {
        $id=$session['member_id'] ?? null; $fingerprint=$session['fingerprint'] ?? null;
        if (!is_int($id) || $id<1 || !is_string($fingerprint)) throw new DomainException('Session membre indisponible.');
        $stmt=$this->db->prepare('SELECT m.first_name,m.last_name,m.email,m.card_number,m.qr_token,m.created_at,m.status,a.email AS account_email,a.password_hash FROM members m JOIN member_portal_accounts a ON a.member_id=m.id WHERE m.id=?');
        $stmt->execute([$id]); $member=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$member || $member['status']!=='active' || !is_string($member['password_hash']) || !hash_equals(hash('sha256',$member['password_hash']),$fingerprint) || trim((string)$member['email'])!==$member['account_email']) throw new DomainException('Session membre indisponible.');
        unset($member['password_hash'],$member['account_email'],$member['status']); return $member;
    }
}
