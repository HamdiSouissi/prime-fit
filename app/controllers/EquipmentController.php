<?php
require_once __DIR__.'/../services/EquipmentService.php';
/** Inventaire et historique des interventions sur le matériel. */
class EquipmentController {
    private function validDate(string $date): bool {
        $value = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $value && $value->format('Y-m-d') === $date;
    }
    private function fail(string $message): void { throw new DomainException($message); }
    private function audit(PDO $pdo, string $action): void {
        $pdo->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$_SESSION['user']['id'], $action, substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45)]);
    }
    public function index(): void {
        $role = $_SESSION['user']['role'];
        if (!in_array($role, ['Admin','Gérant','Réceptionniste','Coach','Comptable'], true)) { http_response_code(403); exit('Accès interdit.'); }
        $canWrite = in_array($role, ['Admin','Gérant'], true);
        $canSeeCost = in_array($role, ['Admin','Gérant','Comptable'], true);
        $pdo=db(); $error='';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf(); if (!$canWrite) { http_response_code(403); exit('Accès interdit.'); }
            $action=$_POST['action'] ?? '';
            try {
                if ($action==='equipment') {
                    $name=trim($_POST['name'] ?? ''); $reference=trim($_POST['reference_code'] ?? ''); $location=trim($_POST['location'] ?? '');
                    $purchase=trim($_POST['purchased_on'] ?? ''); $next=trim($_POST['next_maintenance'] ?? '');
                    if ($name==='' || mb_strlen($name)>120 || $reference==='' || mb_strlen($reference)>50 || mb_strlen($location)>120 || ($purchase!=='' && (!$this->validDate($purchase) || $purchase>date('Y-m-d'))) || ($next!=='' && !$this->validDate($next))) $this->fail('Vérifiez le nom, la référence, le lieu et les dates.');
                    $pdo->beginTransaction();
                    $pdo->prepare('INSERT INTO equipment (name,reference_code,location,purchased_on,next_maintenance) VALUES (?,?,?,?,?)')->execute([$name,$reference,$location,$purchase ?: null,$next ?: null]);
                    $id=(int)$pdo->lastInsertId(); $this->audit($pdo,'Équipement créé #'.$id); $pdo->commit(); $_SESSION['flash']='Équipement ajouté à l’inventaire.';
                } elseif (in_array($action,['intervention','edit'],true)) {
                    $id=filter_var($_POST['equipment_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                    if (!$id) $this->fail('Équipement invalide.');
                    $service=new EquipmentService($pdo,(int)$_SESSION['user']['id'],$_SERVER['REMOTE_ADDR'] ?? '');
                    if ($action==='edit') { $service->edit($id,$_POST); $_SESSION['flash']='Fiche équipement mise à jour.'; }
                    else { $service->intervention($id,$_POST); $_SESSION['flash']='Intervention enregistrée et équipement mis à jour.'; }
                } else $this->fail('Action invalide.');
                redirect(in_array($action,['intervention','edit'],true) ? 'equipment&item='.$id : 'equipment');
            } catch (DomainException $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); $error=$ex->getMessage(); }
              catch (PDOException $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); if ($ex->getCode()==='23000') $error='Cette référence existe déjà.'; else throw $ex; }
        }
        [$q,$filter,$where,$params]=$this->filters();
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM equipment'.$where); $stmt->execute($params); $total=(int)$stmt->fetchColumn();
        $pageNumber=max(1,(int)($_GET['p'] ?? 1)); $pageNumber=min($pageNumber,max(1,(int)ceil($total/10))); $offset=($pageNumber-1)*10;
        $stmt=$pdo->prepare('SELECT * FROM equipment'.$where.' ORDER BY name,id LIMIT 10 OFFSET '.$offset); $stmt->execute($params); $items=$stmt->fetchAll();
        $selected=filter_var($_GET['item'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $equipment=null; $interventions=[]; $lastInterventionId=0;
        if ($selected) {
            $stmt=$pdo->prepare('SELECT * FROM equipment WHERE id=?'); $stmt->execute([$selected]); $equipment=$stmt->fetch();
            if (!$equipment) { http_response_code(404); exit('Équipement introuvable.'); }
            $lastStmt=$pdo->prepare('SELECT COALESCE(MAX(id),0) FROM equipment_interventions WHERE equipment_id=?'); $lastStmt->execute([$selected]); $lastInterventionId=(int)$lastStmt->fetchColumn();
            $stmt=$pdo->prepare('SELECT i.*,u.name AS author FROM equipment_interventions i LEFT JOIN users u ON u.id=i.created_by WHERE i.equipment_id=? ORDER BY i.intervention_date DESC,i.id DESC LIMIT 100'); $stmt->execute([$selected]); $interventions=$stmt->fetchAll();
        }
        $stmt=$pdo->prepare("SELECT COUNT(*) FROM equipment WHERE status <> 'operational' OR next_maintenance <= ?"); $stmt->execute([date('Y-m-d',strtotime('+7 days'))]); $alertCount=(int)$stmt->fetchColumn();
        $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/equipment.php';
    }
    /** Filtres communs à l'interface et à l'export complet. */
    private function filters(): array {
        $q=is_string($_GET['q'] ?? '') ? trim($_GET['q'] ?? '') : ''; if (mb_strlen($q)>120) $q=mb_substr($q,0,120);
        $filter=$_GET['filter'] ?? ''; if (!is_string($filter) || !in_array($filter,['','operational','out_of_service','maintenance','due'],true)) $filter='';
        $clauses=[]; $params=[];
        if ($q!=='') { $clauses[]='(name LIKE ? OR reference_code LIKE ? OR location LIKE ?)'; $params=array_fill(0,3,'%'.$q.'%'); }
        if ($filter==='due') { $clauses[]='next_maintenance IS NOT NULL AND next_maintenance<=?'; $params[]=date('Y-m-d',strtotime('+7 days')); }
        elseif ($filter!=='') { $clauses[]='status=?'; $params[]=$filter; }
        return [$q,$filter,$clauses ? ' WHERE '.implode(' AND ',$clauses) : '',$params];
    }
    public function export(): void {
        if (!in_array($_SESSION['user']['role'],['Admin','Gérant','Réceptionniste','Coach','Comptable'],true)) { http_response_code(403); exit('Accès interdit.'); }
        [,,$where,$params]=$this->filters();
        $stmt=db()->prepare('SELECT id,name,reference_code,location,purchased_on,status,next_maintenance FROM equipment'.$where.' ORDER BY name,id'); $stmt->execute($params);
        header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="equipements.csv"'); header('Cache-Control: no-store');
        $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,['ID','Nom','Référence','Emplacement','Date d’achat','Statut','Prochain entretien'],';','"','');
        $safe=static function($v): string { $v=(string)$v; return preg_match('/^[\s\x00-\x1F]*[=+@\-\t\r]/u',$v) ? "'".$v : $v; };
        $labels=['operational'=>'Opérationnel','out_of_service'=>'Hors service','maintenance'=>'En maintenance'];
        while ($r=$stmt->fetch()) fputcsv($out,[(string)$r['id'],$safe($r['name']),$safe($r['reference_code']),$safe($r['location']),$r['purchased_on'] ? date('d/m/Y',strtotime($r['purchased_on'])) : '',$labels[$r['status']] ?? '',$r['next_maintenance'] ? date('d/m/Y',strtotime($r['next_maintenance'])) : ''],';','"','');
        fclose($out);
    }

}
