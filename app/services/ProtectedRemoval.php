<?php
declare(strict_types=1);
/** Suppressions par lot, avec conservation des historiques et contrôle de version. */
final class ProtectedRemoval {
    public const TYPES=['members'=>'Membres','rooms'=>'Salles','coaches'=>'Coachs','courses'=>'Cours','subscriptions'=>'Abonnements'];
    public function __construct(private PDO $db,private int $actor,private string $ip) {}
    public static function version(array $row): string { return hash('sha256',json_encode($row,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)); }
    private static function config(string $type): array {
        return match ($type) {
            'members'=>['members','id','id,first_name,last_name,email,status'],
            'rooms'=>['rooms','id','id,name'],
            'coaches'=>['users','id','id,name,email,role,is_active'],
            'courses'=>['courses','id','id,title,room_id,coach_id,starts_at,ends_at,status,capacity'],
            'subscriptions'=>['subscriptions','id','id,member_id,plan_id,start_date,end_date,status,agreed_price'],
            default=>throw new DomainException('Type invalide.'),
        };
    }
    /** Reconstitue exactement les colonnes utilisées lors de la vérification sous verrou. */
    public static function versionFor(string $type,array $row): string {
        [,,$fields]=self::config($type); $projected=[];
        foreach (explode(',',$fields) as $field) { if (!array_key_exists($field,$row)) throw new InvalidArgumentException('Données de version incomplètes.'); $projected[$field]=$row[$field]; }
        return self::version($projected);
    }
    public function one(string $type,int $id): ?array {
        [$table,$key,$fields]=self::config($type);
        $stmt=$this->db->prepare('SELECT '.$fields.' FROM '.$table.' WHERE '.$key.'=?'); $stmt->execute([$id]); $row=$stmt->fetch();
        if (!$row || ($type==='coaches' && $row['role']!=='Coach')) return null;
        $row['version']=self::version($row); return $row;
    }
    public function listing(string $type,string $search,int $page): array {
        [$table,$key,$fields]=self::config($type); $search=trim($search);
        if (mb_strlen($search)>100) throw new DomainException('Recherche trop longue.');
        $name=match($type) { 'members'=>"CONCAT(first_name,' ',last_name)", 'rooms'=>'name', 'coaches'=>'name', 'courses'=>'title', 'subscriptions'=>'CAST(id AS CHAR)' };
        // CONCAT est remplacé par des champs séparés pour rester compatible SQLite et MySQL.
        $where=$type==='coaches' ? " WHERE role='Coach'" : ' WHERE 1=1'; $params=[];
        if ($search!=='') { $where.=$type==='members' ? ' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)' : ' AND '.$name.' LIKE ?'; $params=$type==='members' ? array_fill(0,3,'%'.$search.'%') : ['%'.$search.'%']; }
        $stmt=$this->db->prepare('SELECT COUNT(*) FROM '.$table.$where); $stmt->execute($params); $total=(int)$stmt->fetchColumn(); $pages=max(1,(int)ceil($total/20)); $page=min(max(1,$page),$pages);
        $stmt=$this->db->prepare('SELECT '.$fields.' FROM '.$table.$where.' ORDER BY id DESC LIMIT 20 OFFSET '.(($page-1)*20)); $stmt->execute($params); $rows=$stmt->fetchAll();
        foreach ($rows as &$row) { $row['version']=self::version($row); unset($row['password_hash']); } unset($row);
        return compact('rows','page','pages','total','search');
    }
    private function exists(string $table,string $column,int $id): bool {
        // Identifiants issus exclusivement des listes codées dans ce service.
        $stmt=$this->db->prepare('SELECT 1 FROM '.$table.' WHERE '.$column.'=? LIMIT 1 FOR UPDATE'); $stmt->execute([$id]); return (bool)$stmt->fetchColumn();
    }
    private function any(int $id,array $references): bool {
        foreach ($references as [$table,$column]) if ($this->exists($table,$column,$id)) return true;
        return false;
    }
    /** Tous les éléments sont traités atomiquement. Une erreur annule tout le lot. */
    public function execute(string $type,array $ids,array $versions,string $password,?DateTimeImmutable $now=null): array {
        [$table,$key,$fields]=self::config($type);
        if (!$ids || count($ids)>20 || $password==='' || strlen($password)>1024) throw new DomainException('Sélectionnez entre 1 et 20 éléments et confirmez votre mot de passe.');
        $clean=[]; foreach ($ids as $value) { if (!is_string($value) || !preg_match('/\A[1-9][0-9]{0,9}\z/D',$value)) throw new DomainException('Sélection invalide.'); $id=(int)$value; if (isset($clean[$id])) throw new DomainException('Sélection en double.'); $clean[$id]=true; }
        $ids=array_keys($clean); sort($ids,SORT_NUMERIC); $results=[]; $membersBySubscription=[];
        if ($type==='subscriptions') {
            // Même ordre que SubscriptionService : membre avant abonnement, y compris en lot.
            $stmt=$this->db->prepare('SELECT member_id FROM subscriptions WHERE id=?');
            foreach ($ids as $id) { $stmt->execute([$id]); $memberId=$stmt->fetchColumn(); if (!$memberId) throw new DomainException('Abonnement #'.$id.' introuvable.'); $membersBySubscription[$id]=(int)$memberId; }
            usort($ids,static fn($a,$b)=>[$membersBySubscription[$a],$a]<=>[$membersBySubscription[$b],$b]);
        }
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare("SELECT id,password_hash FROM users WHERE id=? AND role='Admin' AND is_active=1 FOR UPDATE"); $stmt->execute([$this->actor]); $admin=$stmt->fetch();
            if (!$admin || !password_verify($password,$admin['password_hash'])) throw new DomainException('Mot de passe administrateur incorrect ou compte indisponible.');
            foreach ($ids as $id) {
                if ($type==='subscriptions') {
                    $stmt=$this->db->prepare('SELECT id FROM members WHERE id=? FOR UPDATE'); $stmt->execute([$membersBySubscription[$id]]);
                    if (!$stmt->fetch()) throw new DomainException('Membre de l’abonnement introuvable.');
                }
                $stmt=$this->db->prepare('SELECT '.$fields.' FROM '.$table.' WHERE '.$key.'=? FOR UPDATE'); $stmt->execute([$id]); $row=$stmt->fetch();
                if (!$row || ($type==='subscriptions' && (int)$row['member_id']!==$membersBySubscription[$id]) || ($type==='coaches' && $row['role']!=='Coach')) throw new DomainException('Élément #'.$id.' introuvable. Actualisez la liste.');
                $version=$versions[(string)$id] ?? null;
                if (!is_string($version) || !hash_equals(self::version($row),$version)) throw new DomainException('L’élément #'.$id.' a changé. Actualisez la liste et vérifiez votre sélection.');
                $nowValue=($now ?? new DateTimeImmutable())->format('Y-m-d H:i:s');
                if ($type==='members') $outcome=$this->member($row);
                elseif ($type==='rooms') $outcome=$this->room($row);
                elseif ($type==='courses') $outcome=$this->course($row,$nowValue);
                elseif ($type==='subscriptions') $outcome=$this->subscription($row,substr($nowValue,0,10));
                else $outcome=$this->coach($row,$nowValue);
                $this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$this->actor,'Gestion protégée : '.$type.' #'.$id.' '.$outcome,substr($this->ip,0,45)]);
                $results[$id]=$outcome;
            }
            $this->db->commit(); return $results;
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
    private function member(array $row): string {
        $id=(int)$row['id'];
        $refs=[['subscriptions','member_id'],['payments','member_id'],['attendance','member_id'],['course_bookings','member_id'],['private_sessions','member_id'],['fitness_measurements','member_id'],['fitness_programs','member_id'],['marketing_consent_events','member_id'],['member_portal_accounts','member_id'],['member_measurement_revisions','member_id']];
        if ($row['status']==='blacklisted') return 'liste noire conservée';
        if (!$this->any($id,$refs)) { $this->db->prepare('DELETE FROM members WHERE id=?')->execute([$id]); return 'supprimé'; }
        if ($row['status']==='inactive') return 'déjà inactif';
        $this->db->prepare("UPDATE members SET status='inactive' WHERE id=?")->execute([$id]);
        // Empêche les anciennes sessions de redevenir valides après une réactivation.
        $stmt=$this->db->prepare('SELECT password_hash FROM member_portal_accounts WHERE member_id=? FOR UPDATE'); $stmt->execute([$id]); $account=$stmt->fetch();
        if ($account) {
            $hash=$account['password_hash']!==null ? password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT) : null;
            if ($account['password_hash']!==null && $hash===false) throw new RuntimeException('Impossible de révoquer le compte membre.');
            $this->db->prepare('UPDATE member_portal_accounts SET password_hash=?,invitation_hash=NULL,invitation_expires_at=NULL WHERE member_id=?')->execute([$hash,$id]);
        }
        return 'désactivé, historique conservé';
    }
    private function room(array $row): string {
        $id=(int)$row['id']; if ($this->exists('courses','room_id',$id)) throw new DomainException('Salle #'.$id.' liée à des cours : suppression impossible pour préserver le planning.');
        $this->db->prepare('DELETE FROM rooms WHERE id=?')->execute([$id]); return 'supprimée';
    }
    private function course(array $row,string $now): string {
        $id=(int)$row['id'];
        if ($row['starts_at']<=$now) throw new DomainException('Cours #'.$id.' commencé ou passé : historique conservé, suppression impossible.');
        if (!$this->exists('course_bookings','course_id',$id)) { $this->db->prepare('DELETE FROM courses WHERE id=?')->execute([$id]); return 'supprimé'; }
        if ($row['status']==='cancelled') return 'déjà annulé, historique conservé';
        $this->db->prepare("UPDATE courses SET status='cancelled' WHERE id=?")->execute([$id]);
        $this->db->prepare("UPDATE course_bookings SET status='cancelled' WHERE course_id=? AND status<>'cancelled'")->execute([$id]);
        return 'annulé, inscriptions conservées';
    }
    private function subscription(array $row,string $today): string {
        $id=(int)$row['id'];
        // Les pièces financières, gels et remises ne doivent jamais être effacés en cascade.
        $hasHistory=$this->any($id,[['payments','subscription_id'],['subscription_freezes','subscription_id'],['subscription_discounts','subscription_id']]);
        if ($row['start_date']>$today && !$hasHistory) {
            $this->db->prepare('DELETE FROM subscriptions WHERE id=?')->execute([$id]); return 'supprimé';
        }
        if ($row['status']!=='frozen' && $row['end_date']<$today) return 'historique expiré conservé';
        if ($row['status']==='cancelled') return 'déjà annulé, historique conservé';
        $this->db->prepare("UPDATE subscriptions SET status='cancelled' WHERE id=?")->execute([$id]);
        $this->db->prepare("UPDATE subscription_freezes SET state='cancelled',resumed_on=?,credited_days=0,closed_by=? WHERE subscription_id=? AND state='open'")->execute([$today,$this->actor,$id]);
        return 'annulé, historique conservé ; aucun remboursement';
    }
    private function coach(array $row,string $now): string {
        $id=(int)$row['id']; if ($id===$this->actor) throw new DomainException('Impossible de modifier son propre compte.');
        $refs=[['courses','coach_id'],['private_sessions','coach_id'],['activity_logs','user_id'],['fitness_measurements','created_by'],['fitness_programs','created_by'],['marketing_consent_events','created_by'],['shop_sales','sold_by'],['stock_movements','created_by'],['equipment_interventions','created_by'],['expenses','created_by'],['promotions','created_by'],['subscription_freezes','created_by'],['subscription_freezes','closed_by'],['payment_refunds','created_by'],['gym_settings','updated_by']];
        if (!$this->any($id,$refs)) { $this->db->prepare('DELETE FROM users WHERE id=?')->execute([$id]); return 'supprimé'; }
        if (!$row['is_active']) return 'déjà inactif';
        $stmt=$this->db->prepare("SELECT id FROM courses WHERE coach_id=? AND status='scheduled' AND ends_at>? LIMIT 1 FOR UPDATE"); $stmt->execute([$id,$now]);
        if ($stmt->fetch()) throw new DomainException('Coach #'.$id.' affecté à un cours en cours ou futur : réattribuez ou annulez d’abord ses cours.');
        $stmt=$this->db->prepare("SELECT id FROM private_sessions WHERE coach_id=? AND status='scheduled' LIMIT 1 FOR UPDATE"); $stmt->execute([$id]);
        if ($stmt->fetch()) throw new DomainException('Coach #'.$id.' affecté à une séance planifiée : régularisez d’abord ses séances.');
        $hash=password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT); if ($hash===false) throw new RuntimeException('Impossible de révoquer le compte.');
        $this->db->prepare('UPDATE users SET is_active=0,password_hash=? WHERE id=?')->execute([$hash,$id]); return 'désactivé, historique conservé';
    }
}
