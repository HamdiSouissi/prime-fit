<?php
require_once __DIR__ . '/../services/MemberCardMailer.php';
require_once __DIR__ . '/../services/SubscriptionService.php';
require_once __DIR__ . '/../services/MemberProfile.php';
require_once __DIR__ . '/../services/SubscriptionLookup.php';
class ManagementController {
    public static function missingQrColumn(PDOException $ex): bool {
        $info = $ex->errorInfo ?? [];
        return ($info[0] ?? '') === '42S22' && (int)($info[1] ?? 0) === 1054
            && stripos((string)($info[2] ?? ''), 'qr_token') !== false;
    }
    private function sendCardEmail(array $member): string {
        global $config;
        $status = (new MemberCardMailer($config['mail'] ?? []))->send($member);
        $messages = [
            'accepted' => 'Carte QR acceptée par le serveur de messagerie pour envoi (livraison non garantie).',
            'not_configured' => 'E-mail non envoyé : configurez la messagerie dans app/config/local.php.',
            'missing_email' => 'E-mail non envoyé : aucune adresse valide pour ce membre.',
            'missing_qr' => 'E-mail non envoyé : générez d’abord la carte QR.',
            'failed' => 'E-mail non envoyé : vérifiez la configuration du serveur de messagerie puis réessayez.',
        ];
        return $messages[$status];
    }
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
            $phone = trim($_POST['phone'] ?? ''); $email = trim($_POST['email'] ?? '');
            $governorate = trim($_POST['governorate'] ?? ''); $birth = trim($_POST['birth_date'] ?? ''); $status = $_POST['status'] ?? 'active';
            $editing = compact('id','first','last','phone','email','governorate','birth','status');
            if ($id === false || $first === '' || $last === '' || mb_strlen($first)>100 || mb_strlen($last)>100 || mb_strlen($phone)>30 || mb_strlen($governorate)>80 || mb_strlen($email)>190 || (($email === '' && !$id) || ($email !== '' && !filter_var($email,FILTER_VALIDATE_EMAIL))) || !in_array($status,['active','inactive','blacklisted'],true) || ($birth !== '' && !self::validDate($birth))) {
                $error = 'Vérifiez les champs : nom, prénom et adresse e-mail valide sont obligatoires pour un nouveau membre.';
            } else {
                try {
                    if ($id) {
                        $stmt = db()->prepare('UPDATE members SET first_name=?, last_name=?, phone=?, email=?, governorate=?, birth_date=?, status=? WHERE id=?');
                        $stmt->execute([$first,$last,$phone ?: null,$email ?: null,$governorate ?: null,$birth ?: null,$status,$id]);
                    } else {
                        $pdo = db(); $pdo->beginTransaction();
                        try {
                            // Le numéro est produit côté serveur et garanti unique par l'index SQL.
                            // En cas de collision, réessayer avec un nouveau numéro et un nouveau QR.
                            $stmt = $pdo->prepare('INSERT INTO members (first_name,last_name,phone,email,card_number,governorate,birth_date,status,qr_token) VALUES (?,?,?,?,?,?,?,?,?)');
                            for ($attempt = 0; $attempt < 5; $attempt++) {
                                $card = 'PF-' . random_int(10000000, 99999999) . random_int(10000000, 99999999);
                                try {
                                    $qrToken = bin2hex(random_bytes(32));
                                    $stmt->execute([$first,$last,$phone ?: null,$email ?: null,$card,$governorate ?: null,$birth ?: null,$status,$qrToken]);
                                    break;
                                } catch (PDOException $ex) {
                                    if ($ex->getCode() !== '23000' || (int)($ex->errorInfo[1] ?? 0) !== 1062 || $attempt === 4) throw $ex;
                                }
                            }
                            $id = (int)$pdo->lastInsertId();
                            $stmt = $pdo->prepare('SELECT created_at FROM members WHERE id=?'); $stmt->execute([$id]);
                            $createdAt = $stmt->fetchColumn();
                            $this->log('Fiche membre et carte QR créées #'.$id);
                            $pdo->commit();
                        } catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $ex; }
                        // L'envoi intervient après le commit : son échec ne supprime pas le membre.
                        $mailMessage = $this->sendCardEmail(['first_name'=>$first, 'last_name'=>$last, 'email'=>$email, 'card_number'=>$card, 'qr_token'=>$qrToken, 'created_at'=>$createdAt]);
                        $this->flash('Membre créé. ' . $mailMessage); redirect('member_card&id='.$id);
                    }
                    $this->log('Fiche membre enregistrée #'.$id); $this->flash('Fiche membre enregistrée.'); redirect('members');
                } catch (PDOException $ex) {
                    if (self::missingQrColumn($ex)) {
                        error_log('Migration QR manquante : ' . $ex->getMessage());
                        $error = 'La base de données doit être mise à jour avant de créer un membre. Après sauvegarde, importez database/member_qr.sql dans la base utilisée par l’application, puis réessayez.';
                    } elseif ($ex->getCode() === '23000') {
                        $error = 'Impossible de créer le membre. Veuillez réessayer.';
                    } else { throw $ex; }
                }
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
    public function memberProfile(): void {
        $role=$_SESSION['user']['role'];
        if (!in_array($role,['Admin','Gérant','Réceptionniste','Coach','Comptable'],true)) { http_response_code(403); exit('Accès interdit.'); }
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id) { http_response_code(404); exit('Membre introuvable.'); }
        $profile=(new MemberProfile(db()))->get($id,$role);
        if (!$profile) { http_response_code(404); exit('Membre introuvable.'); }
        $canWrite=$this->canWrite(); require __DIR__.'/../views/member_profile.php';
    }
    public function memberCard(): void {
        $this->requireWrite();
        $id = filter_var($_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['id'] ?? '') : ($_GET['id'] ?? ''), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$id) { http_response_code(404); exit('Membre introuvable.'); }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $action = $_POST['action'] ?? 'renew';
            if ($action === 'send_email') {
                $stmt = db()->prepare('SELECT first_name,last_name,email,card_number,qr_token,created_at FROM members WHERE id=?'); $stmt->execute([$id]); $member=$stmt->fetch();
                if (!$member) { http_response_code(404); exit('Membre introuvable.'); }
                $this->flash($this->sendCardEmail($member)); redirect('member_card&id='.$id);
            }
            if ($action !== 'renew') { http_response_code(400); exit('Action invalide.'); }
            $pdo = db(); $pdo->beginTransaction();
            try {
                $stmt=$pdo->prepare('SELECT id FROM members WHERE id=? FOR UPDATE'); $stmt->execute([$id]);
                if (!$stmt->fetch()) { $pdo->rollBack(); http_response_code(404); exit('Membre introuvable.'); }
                $pdo->prepare('UPDATE members SET qr_token=? WHERE id=?')->execute([bin2hex(random_bytes(32)),$id]);
                $this->log('Carte QR émise ou renouvelée membre #'.$id); $pdo->commit();
                $this->flash('Carte QR créée. Toute carte QR précédente est désormais invalide.'); redirect('member_card&id='.$id);
            } catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $ex; }
        }
        $stmt=db()->prepare('SELECT id,first_name,last_name,email,card_number,qr_token FROM members WHERE id=?'); $stmt->execute([$id]); $member=$stmt->fetch();
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
            } elseif (in_array($action, ['subscription','renew','freeze','resume','cancel'], true)) {
                $service=new SubscriptionService(db(), (int)$_SESSION['user']['id'], $_SERVER['REMOTE_ADDR'] ?? '');
                try {
                    if ($action==='subscription') {
                        $member=filter_var($_POST['member_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                        $selection=$_POST['plan_id'] ?? ''; $start=$_POST['start_date'] ?? '';
                        if (!$member || !is_string($selection) || !preg_match('/^(\d+)\|([a-f0-9]{64})$/D',$selection,$matches) || !is_string($start) || (int)$matches[1]<1) throw new DomainException('Sélectionnez un membre, une formule et une date valides.');
                        $created=$service->create($member,(int)$matches[1],$start,$matches[2]);
                        $this->flash('Abonnement enregistré jusqu’au '.date('d/m/Y',strtotime($created['end'])).', prix fixé : '.dt($created['price']).'. Aucun paiement n’a été enregistré.');
                    } else {
                        $id=filter_var($_POST['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                        if (!$id) throw new DomainException('Abonnement invalide.');
                        if ($action==='renew') {
                            $expected=$_POST['plan_version'] ?? '';
                            if (!is_string($expected) || !preg_match('/^[a-f0-9]{64}$/D',$expected)) throw new DomainException('Rechargez la page et vérifiez la formule avant de renouveler.');
                            $created=$service->renew($id,$expected);
                            $this->flash('Renouvellement enregistré du '.date('d/m/Y',strtotime($created['start'])).' au '.date('d/m/Y',strtotime($created['end'])).', prix fixé : '.dt($created['price']).'. Aucun paiement n’a été enregistré.');
                        } elseif ($action==='freeze') { $service->freeze($id); $this->flash('Abonnement gelé à partir d’aujourd’hui.'); }
                        elseif ($action==='resume') { $result=$service->resume($id); $this->flash('Abonnement réactivé : '.$result['days'].' jour(s) crédité(s), fin le '.date('d/m/Y',strtotime($result['end'])).'.'); }
                        else { $service->cancel($id); $this->flash('Abonnement annulé. Aucun remboursement n’a été enregistré.'); }
                    }
                    redirect('subscriptions');
                } catch (DomainException $ex) { $error=$ex->getMessage(); }
                  catch (PDOException $ex) {
                      $info=$ex->errorInfo ?? [];
                      if (($info[0] ?? '')==='42S02' && (int)($info[1] ?? 0)===1146 && stripos((string)($info[2] ?? ''),'subscription_freezes')!==false) {
                          error_log('Migration des gels manquante : '.$ex->getMessage());
                          $error='La base doit être mise à jour : après sauvegarde, importez database/subscription_freezes.sql dans la base utilisée, puis réessayez.';
                      } else throw $ex;
                  }
            } else { http_response_code(400); exit('Action invalide.'); }
        }
        $plans=db()->query('SELECT * FROM plans ORDER BY name')->fetchAll();
        $members=db()->query("SELECT id,first_name,last_name,card_number FROM members WHERE status='active' ORDER BY last_name,first_name")->fetchAll();
        $q=is_string($_GET['q'] ?? null) ? $_GET['q'] : ''; $filter=is_string($_GET['filter'] ?? null) ? $_GET['filter'] : '';
        $result=(new SubscriptionLookup(db()))->search($q,$filter,(int)($_GET['p'] ?? 1),date('Y-m-d'));
        $q=$result['query']; $filter=$result['filter']; $total=$result['total']; $pageNumber=$result['page']; $subscriptions=$result['subscriptions'];
        $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/subscriptions.php';
    }
    public function subscriptionDetail(): void {
        if (!in_array($_SESSION['user']['role'],['Admin','Gérant','Réceptionniste','Coach','Comptable'],true)) { http_response_code(403); exit('Accès interdit.'); }
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id) { http_response_code(404); exit('Abonnement introuvable.'); }
        $detail=(new SubscriptionLookup(db()))->detail($id,(int)($_GET['p'] ?? 1));
        if (!$detail) { http_response_code(404); exit('Abonnement introuvable.'); }
        require __DIR__.'/../views/subscription_detail.php';
    }

}
