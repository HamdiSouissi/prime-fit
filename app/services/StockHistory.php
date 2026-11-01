<?php
declare(strict_types=1);
/** Consultation paginée et filtrée de l'historique d'un produit. */
class StockHistory {
    private const LIMIT=20;
    public const REASONS=['initial'=>'Stock initial','restock'=>'Réapprovisionnement','sale'=>'Vente','adjustment'=>'Correction'];
    public function __construct(private PDO $db) {}
    public static function filters(array $query): array {
        $result=[];
        foreach (['from','to','reason'] as $key) { $value=$query[$key] ?? ''; if (!is_string($value)) throw new DomainException('Filtres invalides.'); $result[$key]=$value; }
        foreach (['from','to'] as $key) {
            $date=$result[$key]; if ($date==='') continue;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)) throw new DomainException('Indiquez des dates valides.');
            $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            if (!$parsed || $parsed->format('Y-m-d')!==$date || $date<'1900-01-01' || $date>'2099-12-31') throw new DomainException('Indiquez des dates entre 1900 et 2099.');
        }
        if ($result['from']!=='' && $result['to']!=='' && $result['from']>$result['to']) throw new DomainException('La date de début doit précéder ou égaler la date de fin.');
        if ($result['reason']!=='' && !isset(self::REASONS[$result['reason']])) throw new DomainException('Type de mouvement invalide.');
        return $result;
    }
    public function product(int $id): ?array {
        $stmt=$this->db->prepare('SELECT id,name,sku,stock,is_active FROM products WHERE id=?'); $stmt->execute([$id]); return $stmt->fetch() ?: null;
    }
    private function conditions(int $id,array $filters): array {
        $where=['m.product_id=?']; $params=[$id];
        if ($filters['from']!=='') { $where[]='m.created_at>=?'; $params[]=$filters['from'].' 00:00:00'; }
        if ($filters['to']!=='') { $where[]='m.created_at<?'; $params[]=(new DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d').' 00:00:00'; }
        if ($filters['reason']!=='') { $where[]='m.reason=?'; $params[]=$filters['reason']; }
        return [implode(' AND ',$where),$params];
    }
    public function page(int $id,array $filters,int $requestedPage): array {
        [$condition,$params]=$this->conditions($id,$filters);
        $stmt=$this->db->prepare('SELECT COUNT(*) FROM stock_movements m WHERE '.$condition); $stmt->execute($params); $total=(int)$stmt->fetchColumn();
        $pages=max(1,(int)ceil($total/self::LIMIT)); $page=min(max(1,$requestedPage),$pages); $offset=($page-1)*self::LIMIT;
        $stmt=$this->db->prepare('SELECT m.id,m.created_at,m.reason,m.quantity_change,m.note,m.sale_id,u.name AS author FROM stock_movements m LEFT JOIN users u ON u.id=m.created_by WHERE '.$condition.' ORDER BY m.created_at DESC,m.id DESC LIMIT '.self::LIMIT.' OFFSET '.$offset);
        $stmt->execute($params); return ['rows'=>$stmt->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
    /** Parcours borné par lots, sans charger toutes les lignes en mémoire. */
    public function export(int $id,array $filters,callable $write): void {
        [$condition,$params]=$this->conditions($id,$filters);
        $stmt=$this->db->prepare('SELECT COALESCE(MAX(id),0) FROM stock_movements WHERE product_id=?'); $stmt->execute([$id]); $maxId=(int)$stmt->fetchColumn(); $last=null;
        do {
            $extra=' AND m.id<=?'; $values=array_merge($params,[$maxId]);
            if ($last!==null) { $extra.=' AND (m.created_at<? OR (m.created_at=? AND m.id<?))'; array_push($values,$last['created_at'],$last['created_at'],$last['id']); }
            $stmt=$this->db->prepare('SELECT m.id,m.created_at,m.reason,m.quantity_change,m.note,m.sale_id,u.name AS author FROM stock_movements m LEFT JOIN users u ON u.id=m.created_by WHERE '.$condition.$extra.' ORDER BY m.created_at DESC,m.id DESC LIMIT 500');
            $stmt->execute($values); $rows=$stmt->fetchAll();
            foreach ($rows as $row) { $write($row); $last=$row; }
        } while (count($rows)===500);
    }

}
