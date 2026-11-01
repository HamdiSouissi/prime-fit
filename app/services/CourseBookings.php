<?php
declare(strict_types=1);
/** Registre des états actuels ; les anciens changements ne sont pas historisés. */
class CourseBookings {
    private const PAGE_SIZE=20;
    public const STATUS=['confirmed'=>'Confirmée','waiting'=>'En attente','cancelled'=>'Annulée'];
    public function __construct(private PDO $db) {}
    public function course(int $id): ?array {
        $stmt=$this->db->prepare('SELECT c.id,c.title,c.starts_at,c.ends_at,c.status,r.name AS room FROM courses c JOIN rooms r ON r.id=c.room_id WHERE c.id=?');
        $stmt->execute([$id]); return $stmt->fetch() ?: null;
    }
    public static function status(array $input): string {
        $status=$input['status'] ?? ''; if (!is_string($status) || ($status!=='' && !isset(self::STATUS[$status]))) throw new DomainException('Statut d’inscription invalide.'); return $status;
    }
    public function page(int $course,string $status,int $page): array {
        if ($status!=='' && !isset(self::STATUS[$status])) throw new DomainException('Statut d’inscription invalide.');
        $where='b.course_id=?'; $params=[$course]; if ($status!=='') { $where.=' AND b.status=?'; $params[]=$status; }
        $stmt=$this->db->prepare('SELECT COUNT(*) FROM course_bookings b WHERE '.$where); $stmt->execute($params); $total=(int)$stmt->fetchColumn();
        $pages=max(1,(int)ceil($total/self::PAGE_SIZE)); $page=min(max(1,$page),$pages); $offset=($page-1)*self::PAGE_SIZE;
        $stmt=$this->db->prepare('SELECT b.id,b.status,b.created_at,b.updated_at,m.first_name,m.last_name FROM course_bookings b JOIN members m ON m.id=b.member_id WHERE '.$where.' ORDER BY b.updated_at DESC,b.id DESC LIMIT '.self::PAGE_SIZE.' OFFSET '.$offset); $stmt->execute($params);
        return ['rows'=>$stmt->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
}
