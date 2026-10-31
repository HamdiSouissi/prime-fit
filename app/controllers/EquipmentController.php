<?php
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
                } elseif ($action==='intervention') {
                    $id=filter_var($_POST['equipment_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                    $date=trim($_POST['intervention_date'] ?? ''); $type=$_POST['type'] ?? ''; $description=trim($_POST['description'] ?? '');
                    $status=$_POST['status'] ?? ''; $next=trim($_POST['next_maintenance'] ?? ''); $cost=str_replace(',','.',trim($_POST['cost'] ?? ''));
                    if (!$id || !$this->validDate($date) || $date>date('Y-m-d') || !in_array($type,['maintenance','breakdown','repair'],true) || $description==='' || mb_strlen($description)>1000 || !in_array($status,['operational','out_of_service','maintenance'],true) || ($next!=='' && !$this->validDate($next)) || ($cost!=='' && !preg_match('/^\d{1,7}(\.\d{1,3})?$/',$cost))) $this->fail('Vérifiez les champs, la date de l’intervention, le statut et le coût.');
                    $pdo->beginTransaction();
                    $stmt=$pdo->prepare('SELECT id FROM equipment WHERE id=? FOR UPDATE'); $stmt->execute([$id]); if (!$stmt->fetch()) $this->fail('Équipement introuvable.');
                    $pdo->prepare('INSERT INTO equipment_interventions (equipment_id,intervention_date,type,description,cost,created_by) VALUES (?,?,?,?,?,?)')->execute([$id,$date,$type,$description,$cost==='' ? null : $cost,$_SESSION['user']['id']]);
                    $intervention=(int)$pdo->lastInsertId();
                    $pdo->prepare('UPDATE equipment SET status=?,next_maintenance=? WHERE id=?')->execute([$status,$next ?: null,$id]);
                    $this->audit($pdo,'Intervention #'.$intervention.' équipement #'.$id); $pdo->commit(); $_SESSION['flash']='Intervention enregistrée et équipement mis à jour.';
                } else $this->fail('Action invalide.');
                redirect($action === 'intervention' ? 'equipment&item='.$id : 'equipment');
            } catch (DomainException $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); $error=$ex->getMessage(); }
              catch (PDOException $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); if ($ex->getCode()==='23000') $error='Cette référence existe déjà.'; else throw $ex; }
        }
        $q=trim($_GET['q'] ?? ''); if (mb_strlen($q)>120) $q=mb_substr($q,0,120);
        $where=$q!=='' ? ' WHERE name LIKE ? OR reference_code LIKE ? OR location LIKE ?' : ''; $params=$q!=='' ? array_fill(0,3,'%'.$q.'%') : [];
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM equipment'.$where); $stmt->execute($params); $total=(int)$stmt->fetchColumn();
        $pageNumber=max(1,(int)($_GET['p'] ?? 1)); $pageNumber=min($pageNumber,max(1,(int)ceil($total/10))); $offset=($pageNumber-1)*10;
        $stmt=$pdo->prepare('SELECT * FROM equipment'.$where.' ORDER BY name,id LIMIT 10 OFFSET '.$offset); $stmt->execute($params); $items=$stmt->fetchAll();
        $selected=filter_var($_GET['item'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $equipment=null; $interventions=[];
        if ($selected) {
            $stmt=$pdo->prepare('SELECT * FROM equipment WHERE id=?'); $stmt->execute([$selected]); $equipment=$stmt->fetch();
            if (!$equipment) { http_response_code(404); exit('Équipement introuvable.'); }
            $stmt=$pdo->prepare('SELECT i.*,u.name AS author FROM equipment_interventions i LEFT JOIN users u ON u.id=i.created_by WHERE i.equipment_id=? ORDER BY i.intervention_date DESC,i.id DESC LIMIT 100'); $stmt->execute([$selected]); $interventions=$stmt->fetchAll();
        }
        $alertCount=(int)$pdo->query("SELECT COUNT(*) FROM equipment WHERE status <> 'operational' OR next_maintenance <= CURDATE() + INTERVAL 7 DAY")->fetchColumn();
        $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/equipment.php';
    }
}
