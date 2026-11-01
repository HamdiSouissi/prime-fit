<?php
declare(strict_types=1);
/** Lectures de l'espace personnel. L'identifiant doit provenir d'une session membre vérifiée. */
final class MemberPortalData {
    public function __construct(private PDO $db) {}
    private function rows(string $sql,array $args): array { $stmt=$this->db->prepare($sql); $stmt->execute($args); return $stmt->fetchAll(); }
    public function get(int $memberId,?DateTimeImmutable $now=null): array {
        if ($memberId<1) throw new DomainException('Membre introuvable.');
        $now=$now ?? new DateTimeImmutable(); $today=$now->format('Y-m-d');
        $stmt=$this->db->prepare("SELECT id,first_name,last_name,email,phone,governorate,birth_date,card_number FROM members WHERE id=? AND status='active'"); $stmt->execute([$memberId]); $member=$stmt->fetch();
        if (!$member) throw new DomainException('Compte membre indisponible.');
        $stmt=$this->db->prepare("SELECT COUNT(*) FROM attendance WHERE member_id=? AND result='accepted'"); $stmt->execute([$memberId]); $visits=(int)$stmt->fetchColumn();
        $stmt=$this->db->prepare("SELECT COUNT(*) FROM attendance WHERE member_id=? AND result='accepted' AND checked_in_at>=? AND checked_in_at<?");
        $first=$now->modify('first day of this month')->setTime(0,0); $stmt->execute([$memberId,$first->format('Y-m-d H:i:s'),$first->modify('+1 month')->format('Y-m-d H:i:s')]); $monthVisits=(int)$stmt->fetchColumn();
        $subscriptions=$this->rows('SELECT p.name,s.start_date,s.end_date,s.status FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE s.member_id=? ORDER BY s.start_date DESC,s.id DESC LIMIT 20',[$memberId]);
        $attendance=$this->rows('SELECT checked_in_at,result FROM attendance WHERE member_id=? ORDER BY checked_in_at DESC,id DESC LIMIT 20',[$memberId]);
        $measurements=$this->rows('SELECT measured_on,weight_kg,height_cm,waist_cm FROM fitness_measurements WHERE member_id=? ORDER BY measured_on DESC,id DESC LIMIT 20',[$memberId]);
        $programs=$this->rows('SELECT title,objective,content,status,created_at FROM fitness_programs WHERE member_id=? ORDER BY created_at DESC,id DESC LIMIT 20',[$memberId]);
        $bookings=$this->rows("SELECT c.title,c.starts_at,c.ends_at,c.status AS course_status,b.status AS booking_status,r.name AS room FROM course_bookings b JOIN courses c ON c.id=b.course_id JOIN rooms r ON r.id=c.room_id WHERE b.member_id=? ORDER BY c.starts_at DESC,c.id DESC LIMIT 20",[$memberId]);
        $courses=$this->rows("SELECT c.id,c.title,c.starts_at,c.ends_at,c.capacity,r.name AS room,own.status AS own_status,(SELECT COUNT(*) FROM course_bookings b WHERE b.course_id=c.id AND b.status='confirmed') AS booked FROM courses c JOIN rooms r ON r.id=c.room_id LEFT JOIN course_bookings own ON own.course_id=c.id AND own.member_id=? WHERE c.status='scheduled' AND c.starts_at>? ORDER BY c.starts_at,c.id LIMIT 20",[$memberId,$now->format('Y-m-d H:i:s')]);
        return compact('member','visits','monthVisits','subscriptions','attendance','measurements','programs','bookings','courses','today');
    }
}
