<?php
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
            $subscription = filter_var($_POST['subscription_id'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            $amount = str_replace(',', '.', trim($_POST['amount'] ?? ''));
            $method = $_POST['method'] ?? '';
            $methods = ['espèces', 'chèque', 'virement', 'D17', 'Flouci', 'carte bancaire'];
            if (!$subscription || !preg_match('/^\d{1,7}(\.\d{1,3})?$/', $amount) || (float)$amount <= 0 || !in_array($method, $methods, true)) {
                $error = 'Sélectionnez un abonnement et indiquez un montant positif et un moyen de paiement valides.';
            } else {
                // Le verrou sur l'abonnement sérialise les paiements concurrents et protège le solde.
                $pdo = db(); $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare('SELECT s.id,s.member_id,p.price FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE s.id=? FOR UPDATE');
                    $stmt->execute([$subscription]); $sub = $stmt->fetch();
                    if (!$sub) { $pdo->rollBack(); $error = 'Abonnement introuvable.'; }
                    else {
                        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE subscription_id=?'); $stmt->execute([$subscription]);
                        // Comparaison en millièmes pour éviter les erreurs de virgule flottante.
                        $millimes = static function ($value): int { $parts = explode('.', (string)$value, 2); return (int)$parts[0]*1000+(int)str_pad($parts[1] ?? '', 3, '0'); };
                        $remaining = $millimes($sub['price']) - $millimes($stmt->fetchColumn());
                        if ($millimes($amount) > $remaining) { $pdo->rollBack(); $error = 'Le montant dépasse le reste à payer de cet abonnement.'; }
                        else {
                            $pdo->prepare('INSERT INTO payments (member_id,subscription_id,amount,method) VALUES (?,?,?,?)')->execute([$sub['member_id'],$subscription,$amount,$method]);
                            $id = (int)$pdo->lastInsertId();
                            $pdo->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$_SESSION['user']['id'],'Paiement enregistré #'.$id,substr($_SERVER['REMOTE_ADDR'] ?? '',0,45)]);
                            $pdo->commit(); $_SESSION['flash'] = 'Paiement enregistré. Le reçu est disponible dans la liste.'; redirect('payments');
                        }
                    }
                } catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $ex; }
            }
        }
        $date = $_GET['date'] ?? date('Y-m-d');
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) $date = date('Y-m-d');
        $stmt = db()->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE paid_at >= ? AND paid_at < DATE_ADD(?, INTERVAL 1 DAY)'); $stmt->execute([$date,$date]); $totalDay = $stmt->fetchColumn();
        $stmt = db()->prepare('SELECT method, SUM(amount) AS total, COUNT(*) AS count FROM payments WHERE paid_at >= ? AND paid_at < DATE_ADD(?, INTERVAL 1 DAY) GROUP BY method ORDER BY method'); $stmt->execute([$date,$date]); $byMethod = $stmt->fetchAll();
        $stmt = db()->prepare('SELECT COUNT(*) FROM payments WHERE paid_at >= ? AND paid_at < DATE_ADD(?, INTERVAL 1 DAY)'); $stmt->execute([$date,$date]); $total=(int)$stmt->fetchColumn();
        $pageNumber=max(1,(int)($_GET['p'] ?? 1)); $pageNumber=min($pageNumber,max(1,(int)ceil($total/10))); $offset=($pageNumber-1)*10;
        $stmt=db()->prepare('SELECT pay.*, m.first_name,m.last_name, pl.name AS plan FROM payments pay JOIN members m ON m.id=pay.member_id LEFT JOIN subscriptions s ON s.id=pay.subscription_id LEFT JOIN plans pl ON pl.id=s.plan_id WHERE pay.paid_at >= ? AND pay.paid_at < DATE_ADD(?, INTERVAL 1 DAY) ORDER BY pay.paid_at DESC,pay.id DESC LIMIT 10 OFFSET '.$offset); $stmt->execute([$date,$date]); $payments=$stmt->fetchAll();
        $subscriptions=db()->query('SELECT s.id,s.start_date,s.end_date,m.first_name,m.last_name,p.name,p.price,COALESCE(SUM(pay.amount),0) AS paid FROM subscriptions s JOIN members m ON m.id=s.member_id JOIN plans p ON p.id=s.plan_id LEFT JOIN payments pay ON pay.subscription_id=s.id GROUP BY s.id,s.start_date,s.end_date,m.first_name,m.last_name,p.name,p.price HAVING paid < p.price ORDER BY s.id DESC')->fetchAll();
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
        require __DIR__.'/../views/receipt.php';
    }
}
