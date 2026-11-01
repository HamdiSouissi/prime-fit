<?php
declare(strict_types=1);
require_once __DIR__.'/MemberSchedule.php';

/** Inscription personnelle, sous les mêmes verrous et règles que le planning du personnel. */
final class MemberPortalBooking {
    public function __construct(private PDO $db) {}

    /** $session est une identité membre fournie par le serveur, jamais par le formulaire. */
    public function book(array $session,int $courseId,string $ip,?DateTimeImmutable $now=null): string {
        $memberId=$session['member_id'] ?? null; $fingerprint=$session['fingerprint'] ?? null;
        if (!is_int($memberId) || $memberId<1 || !is_string($fingerprint) || $courseId<1) throw new DomainException('Session ou cours invalide.');
        $this->db->beginTransaction();
        try {
            // Ordre commun avec les réservations du personnel : cours puis membre.
            $stmt=$this->db->prepare('SELECT id,status,starts_at,ends_at,capacity FROM courses WHERE id=? FOR UPDATE');
            $stmt->execute([$courseId]); $course=$stmt->fetch();
            // Actualiser l'heure après une éventuelle attente du verrou de cours.
            $now=$now ?? new DateTimeImmutable();
            if (!$course || $course['status']!=='scheduled' || $course['starts_at']<=$now->format('Y-m-d H:i:s')) throw new DomainException('Ce cours est annulé, commencé ou introuvable.');
            $schedule=new MemberSchedule($this->db);
            if (!$schedule->lockActiveMember($memberId)) throw new DomainException('Votre compte membre est indisponible.');
            // Revalider à l'intérieur de la transaction avant toute écriture.
            $stmt=$this->db->prepare('SELECT m.email AS member_email,a.email,a.password_hash FROM members m JOIN member_portal_accounts a ON a.member_id=m.id WHERE m.id=? FOR UPDATE');
            $stmt->execute([$memberId]); $account=$stmt->fetch();
            if (!$account || !is_string($account['password_hash']) || trim((string)$account['member_email'])!==$account['email'] || !hash_equals(hash('sha256',$account['password_hash']),$fingerprint)) throw new DomainException('Votre session membre a expiré. Reconnectez-vous.');
            if (!$schedule->hasSubscription($memberId,$course['starts_at'])) throw new DomainException('Un abonnement actif à la date du cours est nécessaire.');
            $stmt=$this->db->prepare('SELECT id,status FROM course_bookings WHERE course_id=? AND member_id=? FOR UPDATE');
            $stmt->execute([$courseId,$memberId]); $existing=$stmt->fetch();
            if ($existing && $existing['status']!=='cancelled') throw new DomainException('Vous êtes déjà inscrit ou en attente pour ce cours.');
            // Le verrou du cours sérialise les changements de capacité et les réservations.
            $stmt=$this->db->prepare("SELECT COUNT(*) FROM course_bookings WHERE course_id=? AND status='confirmed'"); $stmt->execute([$courseId]);
            $status=(int)$stmt->fetchColumn() < (int)$course['capacity'] ? 'confirmed' : 'waiting';
            if ($status==='confirmed' && ($schedule->hasPrivateConflict($memberId,$course['starts_at'],$course['ends_at']) || $schedule->hasConfirmedCourseConflict($memberId,$course['starts_at'],$course['ends_at'],$courseId,true))) throw new DomainException('Vous avez déjà un cours ou une séance privée sur ce créneau.');
            if ($existing) {
                $stmt=$this->db->prepare('UPDATE course_bookings SET status=?,created_at=? WHERE id=?');
                $stmt->execute([$status,$now->format('Y-m-d H:i:s'),$existing['id']]);
            } else {
                $stmt=$this->db->prepare('INSERT INTO course_bookings (course_id,member_id,status,created_at) VALUES (?,?,?,?)');
                $stmt->execute([$courseId,$memberId,$status,$now->format('Y-m-d H:i:s')]);
            }
            // Le membre n'est pas un utilisateur du personnel ; ne pas usurper un user_id.
            $stmt=$this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (NULL,?,?)');
            $stmt->execute(['Espace membre : réservation '.$status.' cours #'.$courseId.' membre #'.$memberId,substr($ip,0,45)]);
            $this->db->commit(); return $status;
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
}
