<?php
class CoachController {
    private function audit(PDO $db, string $action): void {
        $db->prepare('INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)')->execute([$_SESSION['user']['id'], $action, substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45)]);
    }
    private function fail(string $message): void { throw new DomainException($message); }
    private function datetime(string $value): ?string {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
        return $date && $date->format('Y-m-d\TH:i') === $value ? $date->format('Y-m-d H:i:s') : null;
    }
    public function index(): void {
        $user = $_SESSION['user']; $role = $user['role'];
        if (!in_array($role, ['Admin','Gérant','Réceptionniste','Comptable','Coach'], true)) { http_response_code(403); exit('Accès interdit.'); }
        $manageCoaches = in_array($role, ['Admin','Gérant'], true);
        $manageSessions = in_array($role, ['Admin','Gérant','Réceptionniste'], true);
        $seeMoney = in_array($role, ['Admin','Gérant','Comptable'], true);
        $error = ''; $db = db();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf(); $action = $_POST['action'] ?? '';
            if (($action === 'coach' && !$manageCoaches) || (in_array($action, ['session','status'], true) && !$manageSessions)) { http_response_code(403); exit('Accès interdit.'); }
            try {
                if ($action === 'coach') {
                    $name=trim($_POST['name'] ?? ''); $email=trim($_POST['email'] ?? ''); $password=$_POST['password'] ?? ''; $phone=trim($_POST['phone'] ?? ''); $specialties=trim($_POST['specialties'] ?? ''); $rate=str_replace(',', '.', trim($_POST['commission_rate'] ?? ''));
                    if ($name==='' || mb_strlen($name)>120 || mb_strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12 || strlen($password)>72 || mb_strlen($phone)>30 || mb_strlen($specialties)>255 || !preg_match('/^\d{1,3}(\.\d{1,2})?$/',$rate) || (float)$rate>100) $this->fail('Vérifiez les champs : mot de passe de 12 à 72 octets et commission entre 0 et 100 %.');
                    $db->beginTransaction();
                    $db->prepare("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,'Coach')")->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
                    $id=(int)$db->lastInsertId(); $db->prepare('INSERT INTO coach_profiles (user_id,phone,specialties,commission_rate) VALUES (?,?,?,?)')->execute([$id,$phone ?: null,$specialties,$rate]);
                    $this->audit($db,'Coach créé #'.$id); $db->commit(); $_SESSION['flash']='Coach et compte de connexion créés.';
                } elseif ($action === 'session') {
                    $coach=filter_var($_POST['coach_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $member=filter_var($_POST['member_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $start=$this->datetime($_POST['starts_at'] ?? ''); $end=$this->datetime($_POST['ends_at'] ?? ''); $price=str_replace(',','.',trim($_POST['price'] ?? ''));
                    if (!$coach || !$member || !$start || !$end || $end <= $start || $start <= date('Y-m-d H:i:s') || !preg_match('/^\d{1,7}(\.\d{1,3})?$/',$price)) $this->fail('Vérifiez le coach, le membre, les horaires futurs et le prix.');
                    $db->beginTransaction();
                    // Verrouiller toujours coach puis membre pour sérialiser les créations concurrentes.
                    $stmt=$db->prepare("SELECT u.id,cp.commission_rate FROM users u JOIN coach_profiles cp ON cp.user_id=u.id WHERE u.id=? AND u.role='Coach' AND u.is_active=1 FOR UPDATE"); $stmt->execute([$coach]); $profile=$stmt->fetch(); if (!$profile) $this->fail('Coach introuvable ou inactif.');
                    $stmt=$db->prepare("SELECT id FROM members WHERE id=? AND status='active' FOR UPDATE"); $stmt->execute([$member]); if (!$stmt->fetch()) $this->fail('Membre introuvable ou inactif.');
                    $stmt=$db->prepare("SELECT id FROM private_sessions WHERE (coach_id=? OR member_id=?) AND status='scheduled' AND starts_at < ? AND ends_at > ? LIMIT 1"); $stmt->execute([$coach,$member,$end,$start]); if ($stmt->fetch()) $this->fail('Le coach ou le membre a déjà une séance privée sur ce créneau.');
                    $db->prepare('INSERT INTO private_sessions (coach_id,member_id,starts_at,ends_at,price,commission_rate) VALUES (?,?,?,?,?,?)')->execute([$coach,$member,$start,$end,$price,$profile['commission_rate']]);
                    $this->audit($db,'Séance privée créée #'.$db->lastInsertId()); $db->commit(); $_SESSION['flash']='Séance privée planifiée. Aucun paiement n’a été enregistré.';
                } elseif ($action === 'status') {
                    $id=filter_var($_POST['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $status=$_POST['status'] ?? '';
                    if (!$id || !in_array($status,['completed','cancelled'],true)) $this->fail('Action invalide.');
                    $db->beginTransaction(); $stmt=$db->prepare('SELECT status, ends_at FROM private_sessions WHERE id=? FOR UPDATE'); $stmt->execute([$id]); $session=$stmt->fetch();
                    if (!$session || $session['status']!=='scheduled') $this->fail('Cette séance n’est plus planifiée.');
                    if ($status==='completed' && $session['ends_at'] > date('Y-m-d H:i:s')) $this->fail('Une séance ne peut être marquée terminée avant sa fin.');
                    $db->prepare('UPDATE private_sessions SET status=? WHERE id=?')->execute([$status,$id]); $this->audit($db,'Séance privée #'.$id.' : '.$status); $db->commit(); $_SESSION['flash']='Statut de la séance mis à jour.';
                } else $this->fail('Action invalide.');
                redirect('coaches');
            } catch (DomainException $ex) { if ($db->inTransaction()) $db->rollBack(); $error=$ex->getMessage(); }
              catch (PDOException $ex) { if ($db->inTransaction()) $db->rollBack(); if ($ex->getCode()==='23000') $error='Cette adresse e-mail est déjà utilisée.'; else throw $ex; }
        }
        $onlyOwn=$role==='Coach';
        $sql='SELECT u.id,u.name,u.email,u.is_active,cp.phone,cp.specialties,cp.commission_rate FROM users u JOIN coach_profiles cp ON cp.user_id=u.id WHERE u.role=\'Coach\''.($onlyOwn ? ' AND u.id=?' : '').' ORDER BY u.name';
        $stmt=$db->prepare($sql); $stmt->execute($onlyOwn ? [$user['id']] : []); $coaches=$stmt->fetchAll();
        $members=$manageSessions ? $db->query("SELECT id,first_name,last_name FROM members WHERE status='active' ORDER BY last_name,first_name")->fetchAll() : [];
        $stmt=$db->prepare('SELECT s.*,u.name AS coach,m.first_name,m.last_name FROM private_sessions s JOIN users u ON u.id=s.coach_id JOIN members m ON m.id=s.member_id'.($onlyOwn ? ' WHERE s.coach_id=?' : '').' ORDER BY s.starts_at DESC LIMIT 100'); $stmt->execute($onlyOwn ? [$user['id']] : []); $sessions=$stmt->fetchAll();
        $commissions=[];
        if ($seeMoney) $commissions=$db->query("SELECT u.name, COUNT(s.id) AS sessions, COALESCE(SUM(s.price*s.commission_rate/100),0) AS amount FROM users u JOIN coach_profiles cp ON cp.user_id=u.id LEFT JOIN private_sessions s ON s.coach_id=u.id AND s.status='completed' GROUP BY u.id,u.name ORDER BY u.name")->fetchAll();
        $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/coaches.php';
    }
}
