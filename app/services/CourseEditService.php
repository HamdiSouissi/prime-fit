<?php
declare(strict_types=1);
require_once __DIR__.'/CoachSchedule.php';
/** Modification des cours futurs, sous les mêmes verrous que les réservations. */
class CourseEditService {
    public function __construct(private PDO $db,private int $userId,private string $ip,private ?DateTimeImmutable $now=null) {}
    public static function version(array $c): string {
        return hash('sha256',json_encode([(int)$c['id'],$c['title'],(int)$c['room_id'],$c['coach_id']===null ? null : (int)$c['coach_id'],$c['starts_at'],$c['ends_at'],(int)$c['capacity'],$c['status']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }
    private function datetime(string $value): ?string {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D',$value)) return null;
        $d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value);
        return $d && $d->format('Y-m-d\TH:i')===$value ? $d->format('Y-m-d H:i:s') : null;
    }
    private function log(string $message): void { $this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$this->userId,$message,substr($this->ip,0,45)]); }
    public function edit(int $id,string $version,array $input): int {
        foreach (['title','room_id','coach_id','starts_at','ends_at','capacity'] as $key) if (!isset($input[$key]) || !is_string($input[$key])) throw new DomainException('Vérifiez les champs du cours.');
        $title=trim($input['title']); $room=filter_var($input['room_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $coach=$input['coach_id']==='' ? null : filter_var($input['coach_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $capacity=filter_var($input['capacity'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000]]);
        $start=$this->datetime($input['starts_at']); $end=$this->datetime($input['ends_at']); $now=($this->now ?? new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        if ($title==='' || mb_strlen($title)>120 || !$room || $coach===false || !$capacity || !$start || !$end || $end<=$start || $start<=$now) throw new DomainException('Vérifiez le titre, la salle, le coach, la capacité et les horaires futurs.');
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare('SELECT * FROM courses WHERE id=? FOR UPDATE'); $stmt->execute([$id]); $course=$stmt->fetch();
            if (!$course || $course['status']!=='scheduled' || $course['starts_at']<=$now) throw new DomainException('Ce cours est annulé, commencé ou introuvable.');
            if (!hash_equals(self::version($course),$version)) throw new DomainException('Le cours a changé depuis l’ouverture de la page. Vérifiez les informations actuelles avant de réessayer.');
            // Aucun SELECT non verrouillant avant ces verrous : le contrôle voit les créations déjà validées.
            $stmt=$this->db->prepare('SELECT id FROM rooms WHERE id=? FOR UPDATE'); $stmt->execute([$room]); if (!$stmt->fetch()) throw new DomainException('Salle introuvable.');
            if ($coach!==null) { $schedule=new CoachSchedule($this->db); $schedule->lockCoach((int)$coach); }
            $stmt=$this->db->prepare("SELECT id FROM courses WHERE room_id=? AND status='scheduled' AND starts_at<? AND ends_at>? AND id<>? LIMIT 1"); $stmt->execute([$room,$end,$start,$id]);
            if ($stmt->fetch()) throw new DomainException('Cette salle est déjà occupée sur ce créneau.');
            if ($coach!==null) $schedule->assertAvailable((int)$coach,$start,$end,$id);
            $stmt=$this->db->prepare("SELECT COUNT(*) FROM course_bookings WHERE course_id=? AND status='confirmed'"); $stmt->execute([$id]); $confirmed=(int)$stmt->fetchColumn();
            $stmt=$this->db->prepare("SELECT COUNT(*) FROM course_bookings WHERE course_id=? AND status IN ('confirmed','waiting')"); $stmt->execute([$id]); $registered=(int)$stmt->fetchColumn();
            if ($capacity<$confirmed) throw new DomainException('La capacité ne peut pas être inférieure au nombre de réservations confirmées.');
            if ($registered>0 && ($start!==$course['starts_at'] || $end!==$course['ends_at'])) throw new DomainException('Les horaires ne peuvent pas être modifiés tant que des inscriptions ou personnes en attente existent.');
            $this->db->prepare('UPDATE courses SET title=?,room_id=?,coach_id=?,starts_at=?,ends_at=?,capacity=? WHERE id=?')->execute([$title,$room,$coach,$start,$end,$capacity,$id]);
            $promoted=0; $available=$capacity-$confirmed;
            if ($capacity>(int)$course['capacity'] && $available>0) {
                // La priorité reste l'ordre d'inscription ; les membres non éligibles sont ignorés.
                $stmt=$this->db->prepare("SELECT b.id FROM course_bookings b JOIN members m ON m.id=b.member_id WHERE b.course_id=? AND b.status='waiting' AND m.status='active' AND EXISTS (SELECT 1 FROM subscriptions s WHERE s.member_id=m.id AND s.status='active' AND s.start_date<=DATE(?) AND s.end_date>=DATE(?)) ORDER BY b.created_at,b.id LIMIT ".(int)$available);
                $stmt->execute([$id,$start,$start]); $waiting=$stmt->fetchAll();
                foreach ($waiting as $booking) { $this->db->prepare("UPDATE course_bookings SET status='confirmed' WHERE id=? AND status='waiting'")->execute([$booking['id']]); $this->log('Liste d’attente promue réservation #'.$booking['id']); $promoted++; }
            }
            $this->log('Cours modifié #'.$id); $this->db->commit(); return $promoted;
        } catch (Throwable $ex) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $ex; }
    }
}
