<?php
/** Pointage manuel par numéro de carte (un lecteur QR USB peut saisir ce numéro). */
class AttendanceController {
    private function canWrite(): bool {
        return in_array($_SESSION['user']['role'], ['Admin', 'Gérant', 'Réceptionniste'], true);
    }
    public function index(): void {
        if (!in_array($_SESSION['user']['role'], ['Admin', 'Gérant', 'Réceptionniste', 'Coach'], true)) {
            http_response_code(403); exit('Accès interdit.');
        }
        $canWrite = $this->canWrite(); $error = ''; $result = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            if (!$canWrite) { http_response_code(403); exit('Accès interdit.'); }
            $card = trim($_POST['card_number'] ?? '');
            if ($card === '' || mb_strlen($card) > 50) {
                $error = 'Saisissez un numéro de carte valide.';
            } else {
                $pdo = db(); $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare('SELECT id, first_name, last_name, status FROM members WHERE card_number = ? FOR UPDATE');
                    $stmt->execute([$card]); $member = $stmt->fetch();
                    if (!$member) {
                        $pdo->rollBack(); $error = 'Carte inconnue. Aucun passage enregistré.';
                    } else {
                        $stmt = $pdo->prepare("SELECT p.name, s.end_date FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE s.member_id=? AND s.status='active' AND s.start_date<=CURDATE() AND s.end_date>=CURDATE() ORDER BY s.end_date DESC LIMIT 1");
                        $stmt->execute([$member['id']]); $subscription = $stmt->fetch();
                        $accepted = $member['status'] === 'active' && (bool)$subscription;
                        $reason = $accepted ? 'Abonnement valide' : ($member['status'] === 'blacklisted' ? 'Membre sur liste noire' : ($member['status'] !== 'active' ? 'Membre inactif' : 'Aucun abonnement actif et valide'));
                        $pdo->prepare('INSERT INTO attendance (member_id, result) VALUES (?, ?)')->execute([$member['id'], $accepted ? 'accepted' : 'refused']);
                        $pdo->prepare('INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)')->execute([$_SESSION['user']['id'], 'Pointage '.($accepted ? 'accepté' : 'refusé').' membre #'.$member['id'], substr($_SERVER['REMOTE_ADDR'] ?? '',0,45)]);
                        $pdo->commit();
                        // PRG évite l'enregistrement répété au rechargement de la page.
                        $_SESSION['attendance_result'] = ['accepted'=>$accepted, 'name'=>$member['first_name'].' '.$member['last_name'], 'reason'=>$reason, 'plan'=>$subscription['name'] ?? '', 'end_date'=>$subscription['end_date'] ?? ''];
                        redirect('attendance');
                    }
                } catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $ex; }
            }
        }
        $result = $_SESSION['attendance_result'] ?? null; unset($_SESSION['attendance_result']);
        $date = $_GET['date'] ?? date('Y-m-d');
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) $date = date('Y-m-d');
        $stmt = db()->prepare('SELECT result, COUNT(*) AS total FROM attendance WHERE checked_in_at >= ? AND checked_in_at < DATE_ADD(?, INTERVAL 1 DAY) GROUP BY result');
        $stmt->execute([$date,$date]); $counts = ['accepted'=>0,'refused'=>0];
        foreach ($stmt->fetchAll() as $row) $counts[$row['result']] = (int)$row['total'];
        $total = $counts['accepted'] + $counts['refused'];
        $pageNumber = max(1, (int)($_GET['p'] ?? 1)); $pageNumber = min($pageNumber, max(1, (int)ceil($total / 10))); $offset = ($pageNumber - 1) * 10;
        $stmt = db()->prepare('SELECT a.checked_in_at, a.result, m.first_name, m.last_name, m.card_number FROM attendance a JOIN members m ON m.id=a.member_id WHERE a.checked_in_at >= ? AND a.checked_in_at < DATE_ADD(?, INTERVAL 1 DAY) ORDER BY a.checked_in_at DESC, a.id DESC LIMIT 10 OFFSET '.$offset);
        $stmt->execute([$date,$date]); $visits = $stmt->fetchAll();
        $flash = ''; require __DIR__.'/../views/attendance.php';
    }
}
