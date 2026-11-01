<?php
declare(strict_types=1);
/** Utilisé uniquement à l'intérieur d'une transaction de création de créneau. */
class CoachSchedule {
    public function __construct(private PDO $db) {}
    public function lockCoach(int $coach): void {
        if (!$this->db->inTransaction()) throw new LogicException('Le verrouillage du coach nécessite une transaction.');
        $stmt=$this->db->prepare("SELECT id FROM users WHERE id=? AND role='Coach' AND is_active=1 FOR UPDATE"); $stmt->execute([$coach]);
        if (!$stmt->fetch()) throw new DomainException('Coach introuvable ou inactif.');
    }
    /** Le même verrou coach doit être détenu par tous les créateurs de créneaux. */
    public function assertAvailable(int $coach,string $start,string $end): void {
        if (!$this->db->inTransaction()) throw new LogicException('La vérification nécessite une transaction.');
        $stmt=$this->db->prepare("SELECT id FROM courses WHERE coach_id=? AND status='scheduled' AND starts_at<? AND ends_at>? LIMIT 1"); $stmt->execute([$coach,$end,$start]);
        if ($stmt->fetch()) throw new DomainException('Ce coach a déjà un cours collectif sur ce créneau.');
        $stmt=$this->db->prepare("SELECT id FROM private_sessions WHERE coach_id=? AND status='scheduled' AND starts_at<? AND ends_at>? LIMIT 1"); $stmt->execute([$coach,$end,$start]);
        if ($stmt->fetch()) throw new DomainException('Ce coach a déjà une séance privée sur ce créneau.');
    }
}
