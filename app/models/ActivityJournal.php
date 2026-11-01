<?php
declare(strict_types=1);
/** Recherche bornée du journal existant, accessible uniquement aux administrateurs. */
class ActivityJournal {
    public function __construct(private PDO $db) {}
    public static function authorize(string $role): void { if ($role!=='Admin') throw new DomainException('Accès interdit.'); }
    public static function filters(array $input,?DateTimeImmutable $today=null): array {
        $today=($today ?? new DateTimeImmutable('today'))->setTime(0,0);
        foreach (['start','end','q','user'] as $key) if (isset($input[$key]) && !is_string($input[$key])) throw new DomainException('Filtres invalides.');
        $start=$input['start'] ?? $today->modify('-29 days')->format('Y-m-d'); $end=$input['end'] ?? $today->format('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$end)) throw new DomainException('Choisissez une période valide de 366 jours maximum.');
        $a=DateTimeImmutable::createFromFormat('!Y-m-d',$start); $b=DateTimeImmutable::createFromFormat('!Y-m-d',$end);
        if (!$a || $a->format('Y-m-d')!==$start || !$b || $b->format('Y-m-d')!==$end || $start<'1900-01-01' || $end>'2099-12-30' || $a>$b || (int)$a->diff($b)->format('%a')>365) throw new DomainException('Choisissez une période valide de 366 jours maximum.');
        $q=trim($input['q'] ?? ''); if (mb_strlen($q)>120) throw new DomainException('La recherche est limitée à 120 caractères.');
        $user=$input['user'] ?? ''; if ($user!=='' && $user!=='deleted' && (!ctype_digit($user) || strlen($user)>10 || (int)$user<1 || (int)$user>4294967295)) throw new DomainException('Utilisateur invalide.');
        return ['start'=>$start,'end'=>$end,'until'=>$b->modify('+1 day')->format('Y-m-d'),'q'=>$q,'user'=>$user];
    }
    private function where(array $f): array {
        $where=['a.created_at>=?','a.created_at<?']; $params=[$f['start'],$f['until']];
        if ($f['q']!=='') { $where[]='(a.action LIKE ? OR u.name LIKE ? OR a.ip_address LIKE ?)'; $params=array_merge($params,array_fill(0,3,'%'.$f['q'].'%')); }
        if ($f['user']==='deleted') $where[]='a.user_id IS NULL'; elseif ($f['user']!=='') { $where[]='a.user_id=?'; $params[]=(int)$f['user']; }
        return [implode(' AND ',$where),$params];
    }
    public function page(array $f,int $page): array {
        [$where,$params]=$this->where($f); $from=' FROM activity_logs a LEFT JOIN users u ON u.id=a.user_id WHERE ';
        $stmt=$this->db->prepare('SELECT COUNT(*)'.$from.$where); $stmt->execute($params); $total=(int)$stmt->fetchColumn();
        $page=min(max(1,$page),max(1,(int)ceil($total/30))); $offset=($page-1)*30;
        $stmt=$this->db->prepare('SELECT a.id,a.created_at,a.action,a.ip_address,a.user_id,u.name'.$from.$where.' ORDER BY a.created_at DESC,a.id DESC LIMIT 30 OFFSET '.$offset); $stmt->execute($params);
        return ['rows'=>$stmt->fetchAll(),'total'=>$total,'page'=>$page];
    }
    public function users(): array { return $this->db->query('SELECT id,name FROM users ORDER BY name,id')->fetchAll(); }
    /** Parcours par curseur avec borne d'identifiant, sans charger tout le résultat en mémoire. */
    public function export(array $f,callable $write): void {
        [$where,$params]=$this->where($f); $maxId=(int)$this->db->query('SELECT COALESCE(MAX(id),0) FROM activity_logs')->fetchColumn(); $last=null;
        do {
            $extra=' AND a.id<=?'; $values=array_merge($params,[$maxId]);
            if ($last!==null) { $extra.=' AND (a.created_at<? OR (a.created_at=? AND a.id<?))'; array_push($values,$last['created_at'],$last['created_at'],$last['id']); }
            $stmt=$this->db->prepare('SELECT a.id,a.created_at,a.action,a.ip_address,a.user_id,u.name FROM activity_logs a LEFT JOIN users u ON u.id=a.user_id WHERE '.$where.$extra.' ORDER BY a.created_at DESC,a.id DESC LIMIT 500'); $stmt->execute($values);
            $rows=$stmt->fetchAll(); foreach ($rows as $row) { $write($row); $last=$row; }
        } while (count($rows)===500);
    }
    public static function csvSafe($value): string { $value=(string)$value; return preg_match('/^[\s\x00-\x1F]*[=+@\-\t\r]/u',$value) ? "'".$value : $value; }
}
