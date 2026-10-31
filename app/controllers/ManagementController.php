<?php
class ManagementController {
    private function canWrite(): bool { return in_array($_SESSION['user']['role'], ['Admin', 'Gérant', 'Réceptionniste'], true); }
    private function requireWrite(): void { if (!$this->canWrite()) { http_response_code(403); exit('Accès interdit.'); } }
    private function log(string $action): void {
        db()->prepare('INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)')->execute([$_SESSION['user']['id'], $action, substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45)]);
    }
    private function flash(string $message): void { $_SESSION['flash'] = $message; }
    public function members(): void {
        $error = ''; $editing = null; $canWrite = $this->canWrite();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf(); $this->requireWrite();
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]);
            $first = trim($_POST['first_name'] ?? ''); $last = trim($_POST['last_name'] ?? '');
            $phone = trim($_POST['phone'] ?? ''); $email = trim($_POST['email'] ?? ''); $card = trim($_POST['card_number'] ?? '');
            $governorate = trim($_POST['governorate'] ?? ''); $birth = trim($_POST['birth_date'] ?? ''); $status = $_POST['status'] ?? 'active';
            $editing = compact('id','first','last','phone','email','card','governorate','birth','status');
            if ($id === false || $first === '' || $last === '' || mb_strlen($first)>100 || mb_strlen($last)>100 || mb_strlen($phone)>30 || mb_strlen($card)>50 || mb_strlen($governorate)>80 || mb_strlen($email)>190 || ($email !== '' && !filter_var($email,FILTER_VALIDATE_EMAIL)) || !in_array($status,['active','inactive','blacklisted'],true) || ($birth !== '' && !self::validDate($birth))) {
                $error = 'Vérifiez les champs : nom et prénom sont obligatoires, et les coordonnées doivent être valides.';
            } else {
                try {
                    if ($id) {
                        $stmt = db()->prepare('UPDATE members SET first_name=?, last_name=?, phone=?, email=?, card_number=?, governorate=?, birth_date=?, status=? WHERE id=?');
                        $stmt->execute([$first,$last,$phone ?: null,$email ?: null,$card ?: null,$governorate ?: null,$birth ?: null,$status,$id]);
                    } else {
                        $pdo = db(); $pdo->beginTransaction();
                        try {
                            $pdo->prepare('INSERT INTO members (first_name,last_name,phone,email,card_number,governorate,birth_date,status,qr_token) VALUES (?,?,?,?,?,?,?,?,?)')->execute([$first,$last,$phone ?: null,$email ?: null,$card ?: null,$governorate ?: null,$birth ?: null,$status,bin2hex(random_bytes(32))]);
                            $id = (int)$pdo->lastInsertId();
                            $this->log('Fiche membre et carte QR créées #'.$id);
                            $pdo->commit();
                        } catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $ex; }
                        $this->flash('Membre créé. Sa carte QR est prête à être imprimée.'); redirect('member_card&id='.$id);
                    }
                    $this->log('Fiche membre enregistrée #'.$id); $this->flash('Fiche membre enregistrée.'); redirect('members');
                } catch (PDOException $ex) { if ($ex->getCode() !== '23000') throw $ex; $error = 'Ce numéro de carte est déjà utilisé.'; }
            }
        } elseif (isset($_GET['edit']) && $canWrite) {
            $stmt = db()->prepare('SELECT * FROM members WHERE id=?'); $stmt->execute([(int)$_GET['edit']]); $member = $stmt->fetch();
            if (!$member) { http_response_code(404); exit('Membre introuvable.'); }
            $editing = ['id'=>$member['id'],'first'=>$member['first_name'],'last'=>$member['last_name'],'phone'=>$member['phone'],'email'=>$member['email'],'card'=>$member['card_number'],'governorate'=>$member['governorate'],'birth'=>$member['birth_date'],'status'=>$member['status']];
        }
        $q = trim($_GET['q'] ?? ''); $pageNumber = max(1, (int)($_GET['p'] ?? 1)); $limit=10;
        $where = $q !== '' ? ' WHERE first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR card_number LIKE ?' : '';
        $params = $q !== '' ? array_fill(0,4,'%'.$q.'%') : [];
        $stmt=db()->prepare('SELECT COUNT(*) FROM members'.$where); $stmt->execute($params); $total=(int)$stmt->fetchColumn();
        $pageNumber=min($pageNumber,max(1,(int)ceil($total/$limit))); $offset=($pageNumber-1)*$limit;
        $stmt=db()->prepare('SELECT * FROM members'.$where.' ORDER BY created_at DESC, id DESC LIMIT '.$limit.' OFFSET '.$offset); $stmt->execute($params); $members=$stmt->fetchAll();
        $flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/members.php';
    }
    public function memberCard(): void {
        $this->requireWrite();
        $id = filter_var($_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['id'] ?? '') : ($_GET['id'] ?? ''), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$id) { http_response_code(404); exit('Membre introuvable.'); }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $pdo = db(); $pdo->beginTransaction();
            try {
                $stmt=$pdo->prepare('SELECT id FROM members WHERE id=? FOR UPDATE'); $stmt->execute([$id]);
                if (!$stmt->fetch()) { $pdo->rollBack(); http_response_code(404); exit('Membre introuvable.'); }
                $pdo->prepare('UPDATE members SET qr_token=? WHERE id=?')->execute([bin2hex(random_bytes(32)),$id]);
                $this->log('Carte QR émise ou renouvelée membre #'.$id); $pdo->commit();
                $this->flash('Carte QR créée. Toute carte QR précédente est désormais invalide.'); redirect('member_card&id='.$id);
            } catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $ex; }
        }
        $stmt=db()->prepare('SELECT id,first_name,last_name,card_number,qr_token FROM members WHERE id=?'); $stmt->execute([$id]); $member=$stmt->fetch();
        if (!$member) { http_response_code(404); exit('Membre introuvable.'); }
        header('Cache-Control: no-store, private'); header('Referrer-Policy: no-referrer');
        $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/member_card.php';
    }
    private static function validDate(string $date): bool { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date); return $d && $d->format('Y-m-d') === $date; }
    public function subscriptions(): void {
        $error=''; $canWrite=$this->canWrite();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf(); $this->requireWrite(); $action=$_POST['action'] ?? '';
            if ($action === 'plan') {
                $name=trim($_POST['name'] ?? ''); $days=filter_var($_POST['duration_days'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>3650]]); $price=str_replace(',','.',trim($_POST['price'] ?? ''));
                if ($name==='' || mb_strlen($name)>100 || !$days || !preg_match('/^\d{1,7}(\.\d{1,3})?$/',$price)) $error='Vérifiez le nom, la durée et le prix de la formule.';
                else { db()->prepare('INSERT INTO plans (name,duration_days,price) VALUES (?,?,?)')->execute([$name,$days,$price]); $this->log('Formule créée #'.db()->lastInsertId()); $this->flash('Formule créée.'); redirect('subscriptions'); }
            } elseif ($action === 'subscription') {
                $member=filter_var($_POST['member_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $plan=filter_var($_POST['plan_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $start=$_POST['start_date'] ?? '';
                $stmt=db()->prepare('SELECT duration_days FROM plans WHERE id=? AND is_active=1'); $stmt->execute([$plan ?: 0]); $days=$stmt->fetchColumn();
                $stmt=db()->prepare("SELECT id FROM members WHERE id=? AND status='active'"); $stmt->execute([$member ?: 0]); $memberExists=$stmt->fetchColumn();
                if (!$memberExists || !$days || !self::validDate($start)) $error='Sélectionnez un membre actif, une formule et une date valides.';
                else {
                    $end=(new DateTimeImmutable($start))->modify('+'.((int)$days-1).' days')->format('Y-m-d');
                    db()->prepare("INSERT INTO subscriptions (member_id,plan_id,start_date,end_date,status) VALUES (?,?,?,?,'active')")->execute([$member,$plan,$start,$end]);
                    $this->log('Abonnement créé #'.db()->lastInsertId()); $this->flash('Abonnement enregistré jusqu’au '.date('d/m/Y',strtotime($end)).'. Aucun paiement n’a été enregistré.'); redirect('subscriptions');
                }
            } elseif ($action === 'status') {
                $id=filter_var($_POST['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $status=$_POST['status'] ?? '';
                if (!$id || !in_array($status,['active','frozen','cancelled'],true)) $error='Statut invalide.';
                else { $stmt=db()->prepare('UPDATE subscriptions SET status=? WHERE id=?'); $stmt->execute([$status,$id]); $this->log('Statut abonnement #'.$id.' : '.$status); $this->flash('Statut mis à jour. Le gel ne prolonge pas automatiquement la date de fin.'); redirect('subscriptions'); }
            } else { http_response_code(400); exit('Action invalide.'); }
        }
        $plans=db()->query('SELECT * FROM plans ORDER BY name')->fetchAll();
        $members=db()->query("SELECT id,first_name,last_name,card_number FROM members WHERE status='active' ORDER BY last_name,first_name")->fetchAll();
        $pageNumber=max(1,(int)($_GET['p'] ?? 1)); $total=(int)db()->query('SELECT COUNT(*) FROM subscriptions')->fetchColumn(); $pageNumber=min($pageNumber,max(1,(int)ceil($total/10))); $offset=($pageNumber-1)*10;
        $subscriptions=db()->query('SELECT s.*,m.first_name,m.last_name,p.name AS plan FROM subscriptions s JOIN members m ON m.id=s.member_id JOIN plans p ON p.id=s.plan_id ORDER BY s.created_at DESC,s.id DESC LIMIT 10 OFFSET '.$offset)->fetchAll();
        $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/subscriptions.php';
    }
}
