<?php
require_once __DIR__.'/../services/MarketingConsent.php';
class MarketingController {
    private function validDate(string $value): bool {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        return $date && $date->format('Y-m-d')===$value;
    }
    private function fail(string $message): void { throw new DomainException($message); }
    private function audit(PDO $pdo, string $action): void {
        $pdo->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$_SESSION['user']['id'],$action,substr($_SERVER['REMOTE_ADDR'] ?? '',0,45)]);
    }
    /** Accepte uniquement les numéros tunisiens à huit chiffres, locaux ou préfixés. */
    public static function whatsapp(?string $phone, string $message): ?string {
        $number=MarketingConsent::phone($phone);
        if ($number===null) return null;
        return 'https://wa.me/'.$number.'?text='.rawurlencode($message);
    }
    public function index(): void {
        $role=$_SESSION['user']['role'];
        if (!in_array($role,['Admin','Gérant','Réceptionniste'],true)) { http_response_code(403); exit('Accès interdit.'); }
        $canManage=in_array($role,['Admin','Gérant'],true); $pdo=db(); $error='';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf(); if (!$canManage) { http_response_code(403); exit('Accès interdit.'); }
            $action=$_POST['action'] ?? '';
            try {
                if ($action==='create') {
                    $code=strtoupper(trim($_POST['code'] ?? '')); $label=trim($_POST['label'] ?? '');
                    $discount=str_replace(',','.',trim($_POST['discount_percent'] ?? '')); $start=$_POST['starts_on'] ?? ''; $end=$_POST['ends_on'] ?? '';
                    if (!preg_match('/^[A-Z0-9_-]{3,40}$/D',$code) || $label==='' || mb_strlen($label)>120 || !preg_match('/^\d{1,3}(\.\d{1,2})?$/D',$discount) || (float)$discount<=0 || (float)$discount>100 || !$this->validDate($start) || !$this->validDate($end) || $end<$start) $this->fail('Vérifiez le code (3 à 40 lettres, chiffres, tirets ou underscores), le libellé, le pourcentage et les dates.');
                    $pdo->beginTransaction(); $pdo->prepare('INSERT INTO promotions (code,label,discount_percent,starts_on,ends_on,created_by) VALUES (?,?,?,?,?,?)')->execute([$code,$label,$discount,$start,$end,$_SESSION['user']['id']]);
                    $id=(int)$pdo->lastInsertId(); $this->audit($pdo,'Promotion créée #'.$id); $pdo->commit(); $_SESSION['flash']='Promotion créée. Elle peut être sélectionnée pour un nouvel abonnement ou un renouvellement.';
                } elseif ($action==='toggle') {
                    $id=filter_var($_POST['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $active=$_POST['active'] ?? '';
                    if (!$id || !in_array($active,['0','1'],true)) $this->fail('Action invalide.');
                    $pdo->beginTransaction(); $stmt=$pdo->prepare('UPDATE promotions SET is_active=? WHERE id=?'); $stmt->execute([(int)$active,$id]);
                    if (!$stmt->rowCount()) $this->fail('Promotion introuvable ou déjà dans cet état.');
                    $this->audit($pdo,'Promotion #'.$id.($active==='1' ? ' activée' : ' désactivée')); $pdo->commit(); $_SESSION['flash']='Statut de la promotion mis à jour.';
                } else $this->fail('Action invalide.');
                redirect('marketing');
            } catch (DomainException $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); $error=$ex->getMessage(); }
              catch (PDOException $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); if ($ex->getCode()==='23000') $error='Ce code promotionnel existe déjà.'; else throw $ex; }
        }
        $total=(int)$pdo->query('SELECT COUNT(*) FROM promotions')->fetchColumn(); $pageNumber=max(1,(int)($_GET['p'] ?? 1)); $pageNumber=min($pageNumber,max(1,(int)ceil($total/10))); $offset=($pageNumber-1)*10;
        $promotions=$pdo->query('SELECT * FROM promotions ORDER BY created_at DESC,id DESC LIMIT 10 OFFSET '.$offset)->fetchAll();
        // Les listes sont volontairement limitées ; aucun message n'est envoyé automatiquement.
        $birthdays=$pdo->query("SELECT m.id,m.first_name,m.last_name,m.phone,m.birth_date FROM members m JOIN marketing_consent_events c ON c.id=(SELECT MAX(x.id) FROM marketing_consent_events x WHERE x.member_id=m.id) WHERE m.status='active' AND c.decision='accepted' AND BINARY c.phone_value=BINARY m.phone AND MONTH(m.birth_date)=MONTH(CURDATE()) ORDER BY DAY(m.birth_date),m.last_name,m.first_name LIMIT 100")->fetchAll();
        $expiring=$pdo->query("SELECT s.id AS subscription_id,m.first_name,m.last_name,m.phone,s.end_date,p.name AS plan FROM subscriptions s JOIN members m ON m.id=s.member_id JOIN plans p ON p.id=s.plan_id JOIN marketing_consent_events c ON c.id=(SELECT MAX(x.id) FROM marketing_consent_events x WHERE x.member_id=m.id) WHERE m.status='active' AND s.status='active' AND c.decision='accepted' AND BINARY c.phone_value=BINARY m.phone AND s.end_date BETWEEN CURDATE() AND CURDATE()+INTERVAL 7 DAY ORDER BY s.end_date,m.last_name LIMIT 100")->fetchAll();
        $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/marketing.php';
    }
    /** Vérifie à nouveau l'autorisation et les critères au moment d'ouvrir WhatsApp. */
    public function whatsappRedirect(): void {
        if (!in_array($_SESSION['user']['role'],['Admin','Gérant','Réceptionniste'],true)) { http_response_code(403); exit('Accès interdit.'); }
        $kind=$_GET['kind'] ?? ''; $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!is_string($kind) || !in_array($kind,['birthday','expiry'],true) || !$id) { http_response_code(404); exit('Contact introuvable.'); }
        $link=$this->resolveWhatsapp((int)$id,$kind);
        if ($link===null) { http_response_code(409); exit('Ce contact n’est plus disponible ou autorisé. Actualisez la page marketing.'); }
        header('Cache-Control: no-store'); header('Referrer-Policy: no-referrer'); header('Location: '.$link, true, 302); exit;
    }

    public function resolveWhatsapp(int $id,string $kind): ?string {
        if (!in_array($kind,['birthday','expiry'],true) || $id<1) return null;
        $base=" JOIN marketing_consent_events c ON c.id=(SELECT MAX(x.id) FROM marketing_consent_events x WHERE x.member_id=m.id) WHERE m.status='active' AND c.decision='accepted' AND BINARY c.phone_value=BINARY m.phone";
        if ($kind==='birthday') $sql="SELECT m.first_name,m.phone,m.birth_date,c.phone_value FROM members m".$base." AND m.id=? AND MONTH(m.birth_date)=MONTH(CURDATE())";
        else $sql="SELECT m.first_name,m.phone,s.end_date,p.name AS plan,c.phone_value FROM subscriptions s JOIN members m ON m.id=s.member_id JOIN plans p ON p.id=s.plan_id".$base." AND s.id=? AND s.status='active' AND s.end_date BETWEEN CURDATE() AND CURDATE()+INTERVAL 7 DAY";
        $stmt=db()->prepare($sql); $stmt->execute([$id]); $row=$stmt->fetch();
        if (!$row || (string)$row['phone']!==(string)$row['phone_value']) return null;
        $message=$kind==='birthday' ? 'Bonjour '.$row['first_name'].', toute l’équipe '.gym_name().' vous souhaite un joyeux anniversaire !' : 'Bonjour '.$row['first_name'].', votre abonnement '.$row['plan'].' chez '.gym_name().' expire le '.date('d/m/Y',strtotime($row['end_date'])).'. Contactez-nous pour le renouveler.';
        return self::whatsapp($row['phone'],$message);

    }

}
