<?php
declare(strict_types=1);
/** Recherche paginée des cours, sans modifier le planning ni les inscriptions. */
class CourseSearch {
    private const PAGE_SIZE=20;
    public function __construct(private PDO $db) {}
    private static function date(string $value): bool {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value) || (int)substr($value,0,4)<1000) return false;
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        return $d && $d->format('Y-m-d')===$value;
    }
    public static function filters(array $input,string $today): array {
        $provided=isset($input['filter']) || array_intersect(['q','from','to','status'],array_keys($input));
        $values=['q'=>'','from'=>$provided ? '' : $today,'to'=>'','status'=>''];
        foreach ($values as $key=>$default) {
            if (array_key_exists($key,$input)) {
                if (!is_string($input[$key])) throw new DomainException('Filtres de recherche invalides.');
                $values[$key]=trim($input[$key]);
            }
        }
        if (!mb_check_encoding($values['q'],'UTF-8') || mb_strlen($values['q'])>120 || preg_match('/[\x00-\x1f\x7f]/u',$values['q'])) throw new DomainException('La recherche doit contenir au maximum 120 caractères valides.');
        if (($values['from']!=='' && !self::date($values['from'])) || ($values['to']!=='' && !self::date($values['to']))) throw new DomainException('Indiquez des dates valides.');
        if ($values['from']!=='' && $values['to']!=='' && $values['from']>$values['to']) throw new DomainException('La date de début doit précéder ou égaler la date de fin.');
        if (!in_array($values['status'],['','scheduled','cancelled'],true)) throw new DomainException('Statut de cours invalide.');
        return $values;
    }
    public function search(array $filters,int $page): array {
        $where=[]; $params=[];
        if ($filters['q']!=='') {
            // Recherche littérale : %, _ et ! ne deviennent pas des jokers SQL.
            $term='%'.str_replace(['!','%','_'],['!!','!%','!_'],$filters['q']).'%';
            $where[]="(c.title LIKE ? ESCAPE '!' OR r.name LIKE ? ESCAPE '!' OR u.name LIKE ? ESCAPE '!')";
            array_push($params,$term,$term,$term);
        }
        if ($filters['from']!=='') { $where[]='c.starts_at>=?'; $params[]=$filters['from'].' 00:00:00'; }
        if ($filters['to']!=='') { $where[]='c.starts_at<=?'; $params[]=$filters['to'].' 23:59:59'; }
        if ($filters['status']!=='') { $where[]='c.status=?'; $params[]=$filters['status']; }
        $join=' FROM courses c JOIN rooms r ON r.id=c.room_id LEFT JOIN users u ON u.id=c.coach_id';
        $condition=$where ? ' WHERE '.implode(' AND ',$where) : '';
        $stmt=$this->db->prepare('SELECT COUNT(*)'.$join.$condition); $stmt->execute($params); $total=(int)$stmt->fetchColumn();
        $pages=max(1,(int)ceil($total/self::PAGE_SIZE)); $page=min(max(1,$page),$pages); $offset=($page-1)*self::PAGE_SIZE;
        $sql="SELECT c.*,r.name AS room,u.name AS coach, (SELECT COUNT(*) FROM course_bookings b WHERE b.course_id=c.id AND b.status='confirmed') AS confirmed, (SELECT COUNT(*) FROM course_bookings b WHERE b.course_id=c.id AND b.status='waiting') AS waiting".$join.$condition.' ORDER BY c.starts_at ASC,c.id ASC LIMIT '.self::PAGE_SIZE.' OFFSET '.$offset;
        $stmt=$this->db->prepare($sql); $stmt->execute($params);
        return ['courses'=>$stmt->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
}
