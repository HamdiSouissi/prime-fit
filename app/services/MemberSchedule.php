<?php
declare(strict_types=1);
/** Les appelants verrouillent le membre avant de créer un créneau confirmé. */
class MemberSchedule {
    public function __construct(private PDO $db) {}
    private function requireTransaction(): void { if (!$this->db->inTransaction()) throw new LogicException('Une transaction est nécessaire.'); }
    public function lockActiveMember(int $id): bool {
        $this->requireTransaction();
        $stmt=$this->db->prepare('SELECT id,status FROM members WHERE id=? FOR UPDATE'); $stmt->execute([$id]); $row=$stmt->fetch();
        return $row && $row['status']==='active';
    }
    public function hasSubscription(int $member,string $start): bool {
        $this->requireTransaction();
        // Lecture courante, même si une promotion a déjà ouvert un instantané REPEATABLE READ.
        $stmt=$this->db->prepare("SELECT id FROM subscriptions WHERE member_id=? AND status='active' AND start_date<=DATE(?) AND end_date>=DATE(?) LIMIT 1 FOR UPDATE");
        $stmt->execute([$member,$start,$start]); return (bool)$stmt->fetch();
    }
    public function hasPrivateConflict(int $member,string $start,string $end): bool {
        $this->requireTransaction();
        $stmt=$this->db->prepare("SELECT id FROM private_sessions WHERE member_id=? AND status='scheduled' AND starts_at<? AND ends_at>? LIMIT 1 FOR UPDATE");
        $stmt->execute([$member,$end,$start]); return (bool)$stmt->fetch();
    }
    /** Sans lecture courante, appeler uniquement si l'instantané débute après le verrou membre. */
    public function hasConfirmedCourseConflict(int $member,string $start,string $end,?int $excludeCourse=null,bool $currentRead=false): bool {
        $this->requireTransaction();
        // En promotion un instantané peut déjà exister avant le verrou membre : FOR UPDATE
        // impose alors une lecture courante, quitte à provoquer un interblocage à réessayer.
        $sql="SELECT b.id FROM course_bookings b JOIN courses c ON c.id=b.course_id WHERE b.member_id=? AND b.status='confirmed' AND c.status='scheduled' AND c.starts_at<? AND c.ends_at>? AND c.id<>? LIMIT 1";
        $stmt=$this->db->prepare($sql.($currentRead ? ' FOR UPDATE' : ''));
        $stmt->execute([$member,$end,$start,$excludeCourse ?? 0]); return (bool)$stmt->fetch();
    }
    /** Le cours doit être verrouillé ; renvoie les identifiants promus dans l'ordre d'inscription. */
    public function promote(int $course,string $start,string $end,int $places): array {
        $this->requireTransaction(); $promoted=[]; $lastDate=null; $lastId=0;
        while (count($promoted)<$places) {
            // Le verrou du cours stabilise sa liste ; parcours par lots pour éviter un fetch illimité.
            $sql="SELECT id,member_id,created_at FROM course_bookings WHERE course_id=? AND status='waiting'";
            $params=[$course];
            if ($lastDate!==null) { $sql.=' AND (created_at>? OR (created_at=? AND id>?))'; array_push($params,$lastDate,$lastDate,$lastId); }
            $stmt=$this->db->prepare($sql.' ORDER BY created_at,id LIMIT 100'); $stmt->execute($params); $batch=$stmt->fetchAll();
            if (!$batch) break;
            foreach ($batch as $candidate) {
                $lastDate=$candidate['created_at']; $lastId=(int)$candidate['id']; $member=(int)$candidate['member_id'];
                if (!$this->lockActiveMember($member) || !$this->hasSubscription($member,$start) || $this->hasPrivateConflict($member,$start,$end) || $this->hasConfirmedCourseConflict($member,$start,$end,$course,true)) continue;
                $this->db->prepare("UPDATE course_bookings SET status='confirmed' WHERE id=? AND status='waiting'")->execute([$candidate['id']]); $promoted[]=(int)$candidate['id'];
                if (count($promoted)>=$places) break;
            }
            if (count($batch)<100) break;
        }
        return $promoted;
    }
}
