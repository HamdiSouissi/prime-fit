<?php
require_once __DIR__.'/../services/SubscriptionPaymentService.php';
require_once __DIR__.'/../services/PaymentRefundService.php';
class PaymentController {
    private function canWrite(): bool {
        return in_array($_SESSION['user']['role'], ['Admin', 'Gérant', 'Réceptionniste', 'Comptable'], true);
    }
    public function index(): void {
        $role = $_SESSION['user']['role'];
        if (!in_array($role, ['Admin', 'Gérant', 'Réceptionniste', 'Comptable'], true)) { http_response_code(403); exit('Accès interdit.'); }
        $error = ''; $canWrite = $this->canWrite();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            if (!$canWrite) { http_response_code(403); exit('Accès interdit.'); }
            $subscription=filter_var($_POST['subscription_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $amount=$_POST['amount'] ?? ''; $method=$_POST['method'] ?? '';
            if (!$subscription || !is_string($amount) || !is_string($method)) $error='Vérifiez les champs du paiement.';
            else {
                try { (new SubscriptionPaymentService(db(),(int)$_SESSION['user']['id'],$_SERVER['REMOTE_ADDR'] ?? ''))->pay($subscription,$amount,$method);
                    $_SESSION['flash']='Paiement enregistré. Le reçu est disponible dans la liste.'; redirect('payments');
                } catch (DomainException $ex) { $error=$ex->getMessage(); }
            }
        }
        $date = $_GET['date'] ?? date('Y-m-d');
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) $date = date('Y-m-d');
        $stmt = db()->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE paid_at >= ? AND paid_at < DATE_ADD(?, INTERVAL 1 DAY)'); $stmt->execute([$date,$date]); $totalDay = $stmt->fetchColumn();
        $stmt=db()->prepare('SELECT COALESCE(SUM(amount),0) FROM payment_refunds WHERE refunded_at >= ? AND refunded_at < DATE_ADD(?, INTERVAL 1 DAY)'); $stmt->execute([$date,$date]); $refundDay=$stmt->fetchColumn();
        $stmt = db()->prepare('SELECT method, SUM(amount) AS total, COUNT(*) AS count FROM payments WHERE paid_at >= ? AND paid_at < DATE_ADD(?, INTERVAL 1 DAY) GROUP BY method ORDER BY method'); $stmt->execute([$date,$date]); $byMethod = $stmt->fetchAll();
        $stmt = db()->prepare('SELECT COUNT(*) FROM payments WHERE paid_at >= ? AND paid_at < DATE_ADD(?, INTERVAL 1 DAY)'); $stmt->execute([$date,$date]); $total=(int)$stmt->fetchColumn();
        $pageNumber=max(1,(int)($_GET['p'] ?? 1)); $pageNumber=min($pageNumber,max(1,(int)ceil($total/10))); $offset=($pageNumber-1)*10;
        $stmt=db()->prepare('SELECT pay.*, m.first_name,m.last_name, pl.name AS plan FROM payments pay JOIN members m ON m.id=pay.member_id LEFT JOIN subscriptions s ON s.id=pay.subscription_id LEFT JOIN plans pl ON pl.id=s.plan_id WHERE pay.paid_at >= ? AND pay.paid_at < DATE_ADD(?, INTERVAL 1 DAY) ORDER BY pay.paid_at DESC,pay.id DESC LIMIT 10 OFFSET '.$offset); $stmt->execute([$date,$date]); $payments=$stmt->fetchAll();
        $subscriptions=db()->query('SELECT s.id,s.start_date,s.end_date,m.first_name,m.last_name,p.name,s.agreed_price AS price,COALESCE((SELECT SUM(pay.amount) FROM payments pay WHERE pay.subscription_id=s.id),0)-COALESCE((SELECT SUM(r.amount) FROM payment_refunds r JOIN payments pay ON pay.id=r.payment_id WHERE pay.subscription_id=s.id),0) AS paid FROM subscriptions s JOIN members m ON m.id=s.member_id JOIN plans p ON p.id=s.plan_id HAVING price IS NULL OR paid < price ORDER BY s.id DESC')->fetchAll();
        $stmt=db()->prepare('SELECT r.*,p.method,p.id AS payment_id,m.first_name,m.last_name FROM payment_refunds r JOIN payments p ON p.id=r.payment_id JOIN members m ON m.id=p.member_id WHERE r.refunded_at>=? AND r.refunded_at<DATE_ADD(?, INTERVAL 1 DAY) ORDER BY r.refunded_at DESC,r.id DESC LIMIT 100'); $stmt->execute([$date,$date]); $refunds=$stmt->fetchAll();
        $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/payments.php';
    }
    public function receipt(): void {
        if (!in_array($_SESSION['user']['role'], ['Admin','Gérant','Réceptionniste','Comptable'], true)) { http_response_code(403); exit('Accès interdit.'); }
        $id=filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$id) { http_response_code(404); exit('Reçu introuvable.'); }
        $stmt=db()->prepare('SELECT pay.*,m.first_name,m.last_name,m.card_number,p.name AS plan FROM payments pay JOIN members m ON m.id=pay.member_id LEFT JOIN subscriptions s ON s.id=pay.subscription_id LEFT JOIN plans p ON p.id=s.plan_id WHERE pay.id=?'); $stmt->execute([$id]); $payment=$stmt->fetch();
        if (!$payment) { http_response_code(404); exit('Reçu introuvable.'); }
        // La migration est facultative pour les anciennes installations.
        try { $gymSettings=db()->query('SELECT address,phone,email,tax_id FROM gym_settings WHERE id=1')->fetch() ?: []; }
        catch (PDOException $ex) {
            $info=$ex->errorInfo ?? [];
            if (($info[0] ?? '')==='42S02' && (int)($info[1] ?? 0)===1146 && stripos((string)($info[2] ?? ''),'gym_settings')!==false) $gymSettings=[];
            else throw $ex;
        }
        $stmt=db()->prepare('SELECT r.id,r.amount,r.reason,r.refunded_at,u.name AS author FROM payment_refunds r LEFT JOIN users u ON u.id=r.created_by WHERE r.payment_id=? ORDER BY r.refunded_at,r.id'); $stmt->execute([$id]); $refunds=$stmt->fetchAll();
        require __DIR__.'/../views/receipt.php';
    }
    public function refund(): void {
        if (!in_array($_SESSION['user']['role'],['Admin','Gérant','Comptable'],true)) { http_response_code(403); exit('Accès interdit.'); }
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if (!$id) { http_response_code(404); exit('Paiement introuvable.'); }
        $error='';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf(); $amount=$_POST['amount'] ?? null; $reason=$_POST['reason'] ?? null; $token=$_POST['request_token'] ?? null;
            try {
                if (!is_string($amount) || !is_string($reason) || !is_string($token)) throw new DomainException('Vérifiez les champs du formulaire.');
                (new PaymentRefundService(db(),(int)$_SESSION['user']['id'],$_SERVER['REMOTE_ADDR'] ?? ''))->record((int)$id,$amount,$reason,$token);
                $_SESSION['flash']='Remboursement enregistré. Aucun transfert d’argent n’a été effectué par l’application.'; redirect('payments');
            } catch (DomainException $ex) { $error=$ex->getMessage(); }
              catch (PDOException $ex) { if ($ex->getCode()==='23000') $error='Demande déjà enregistrée ou données invalides. Consultez le reçu avant de réessayer.'; else throw $ex; }
        }
        $stmt=db()->prepare('SELECT p.*,m.first_name,m.last_name,COALESCE((SELECT SUM(r.amount) FROM payment_refunds r WHERE r.payment_id=p.id),0) AS refunded FROM payments p JOIN members m ON m.id=p.member_id WHERE p.id=?'); $stmt->execute([$id]); $payment=$stmt->fetch();
        if (!$payment) { http_response_code(404); exit('Paiement introuvable.'); }
        $remaining=SubscriptionPriceService::millimes((string)$payment['amount'])-SubscriptionPriceService::millimes((string)$payment['refunded']);
        $token=bin2hex(random_bytes(32)); require __DIR__.'/../views/payment_refund.php';
    }

}
