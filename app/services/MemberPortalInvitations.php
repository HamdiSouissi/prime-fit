<?php
declare(strict_types=1);

/** Fondations des invitations de l'espace membre. Ne réalise pas l'envoi d'e-mail. */
final class MemberPortalInvitations {
    public function __construct(private PDO $db) {}

    /**
     * Émet un nouveau secret et invalide l'éventuelle invitation précédente.
     * L'appelant doit envoyer le secret uniquement au titulaire et ne pas le journaliser.
     * Le contrôle d'autorisation du personnel appartient au contrôleur appelant.
     */
    public function issue(int $memberId, ?DateTimeImmutable $now=null): array {
        if ($memberId<1) throw new DomainException('Membre introuvable.');
        $now=$now ?? new DateTimeImmutable();
        $token=bin2hex(random_bytes(32));
        $expires=$now->modify('+24 hours')->format('Y-m-d H:i:s');
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare('SELECT id,first_name,last_name,email,status FROM members WHERE id=? FOR UPDATE');
            $stmt->execute([$memberId]); $member=$stmt->fetch();
            if (!$member || $member['status']!=='active') throw new DomainException('Le membre doit être actif.');
            $email=trim((string)$member['email']);
            if (strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new DomainException('Une adresse e-mail valide est nécessaire.');
            $stmt=$this->db->prepare('SELECT member_id,password_hash FROM member_portal_accounts WHERE member_id=? FOR UPDATE');
            $stmt->execute([$memberId]); $account=$stmt->fetch();
            // Une nouvelle invitation ne réinitialise jamais silencieusement un compte actif.
            if ($account && $account['password_hash']!==null) throw new DomainException('Ce compte est déjà activé.');
            if ($account) {
                $stmt=$this->db->prepare('UPDATE member_portal_accounts SET email=?,invitation_hash=?,invitation_expires_at=? WHERE member_id=?');
                $stmt->execute([$email,hash('sha256',$token),$expires,$memberId]);
            } else {
                $stmt=$this->db->prepare('INSERT INTO member_portal_accounts (member_id,email,invitation_hash,invitation_expires_at) VALUES (?,?,?,?)');
                $stmt->execute([$memberId,$email,hash('sha256',$token),$expires]);
            }
            $this->db->commit();
            return ['email'=>$email,'first_name'=>$member['first_name'],'last_name'=>$member['last_name'],'token'=>$token,'expires_at'=>$expires];
        } catch (Throwable $ex) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $ex;
        }
    }

    /** Consomme atomiquement un lien valide et initialise le mot de passe choisi. */
    public function activate(string $token,string $password,string $confirmation,?DateTimeImmutable $now=null): void {
        if (!preg_match('/\A[a-f0-9]{64}\z/D',$token)) throw new DomainException('Lien invalide ou expiré.');
        if (strlen($password)<12 || strlen($password)>72 || str_contains($password,"\0")) throw new DomainException('Le mot de passe doit contenir entre 12 et 72 octets, sans caractère nul.');
        if (!hash_equals($password,$confirmation)) throw new DomainException('La confirmation ne correspond pas au mot de passe.');
        $now=$now ?? new DateTimeImmutable();
        $this->db->beginTransaction();
        try {
            // Verrou du membre en premier, comme lors de l'émission : évite les inversions de verrous.
            $stmt=$this->db->prepare('SELECT member_id FROM member_portal_accounts WHERE invitation_hash=?');
            $stmt->execute([hash('sha256',$token)]); $id=$stmt->fetchColumn();
            if (!$id) throw new DomainException('Lien invalide ou expiré.');
            $stmt=$this->db->prepare('SELECT email,status FROM members WHERE id=? FOR UPDATE'); $stmt->execute([(int)$id]); $member=$stmt->fetch();
            $stmt=$this->db->prepare('SELECT email,password_hash,invitation_hash,invitation_expires_at FROM member_portal_accounts WHERE member_id=? FOR UPDATE'); $stmt->execute([(int)$id]); $account=$stmt->fetch();
            if (!$member || $member['status']!=='active' || !$account || $account['password_hash']!==null || !is_string($account['invitation_hash']) || !hash_equals($account['invitation_hash'],hash('sha256',$token)) || !$account['invitation_expires_at'] || $account['invitation_expires_at']<=$now->format('Y-m-d H:i:s') || trim((string)$member['email'])!==$account['email']) throw new DomainException('Lien invalide ou expiré.');
            $hash=password_hash($password,PASSWORD_DEFAULT);
            if ($hash===false) throw new RuntimeException('Impossible de créer le mot de passe.');
            $stmt=$this->db->prepare('UPDATE member_portal_accounts SET password_hash=?,invitation_hash=NULL,invitation_expires_at=NULL WHERE member_id=?');
            $stmt->execute([$hash,(int)$id]);
            $this->db->commit();
        } catch (Throwable $ex) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $ex;
        }
    }
}
