<?php
/** Pointage par numéro de carte ou jeton QR personnel. */
class AttendanceController {
    private function canWrite(): bool {
        return in_array($_SESSION['user']['role'], ['Admin', 'Gérant', 'Réceptionniste'], true);
    }
    public function index(): void {
        if (!in_array($_SESSION['user']['role'], ['Admin', 'Gérant', 'Réceptionniste', 'Coach'], true)) {
            http_response_code(403); exit('Accès interdit.');
        }
        $canWrite = $this->canWrite(); $error = ''; $result = null; $unknownCard = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            if (!$canWrite) { http_response_code(403); exit('Accès interdit.'); }
            $card = trim($_POST['card_number'] ?? '');
            if ($card === '' || (mb_strlen($card) > 50 && !preg_match('/^PF1:[0-9a-f]{64}$/D', $card))) {
                $error = 'Saisissez un numéro de carte valide.';
            } else {
                $pdo = db(); $pdo->beginTransaction();
                try {
                    if (preg_match('/^PF1:([0-9a-f]{64})$/D', $card, $match)) {
                        $stmt = $pdo->prepare('SELECT id, first_name, last_name, card_number, phone, status FROM members WHERE qr_token = ? FOR UPDATE');
                        $stmt->execute([$match[1]]);
                    } else {
                        $stmt = $pdo->prepare('SELECT id, first_name, last_name, card_number, phone, status FROM members WHERE card_number = ? FOR UPDATE');
                        $stmt->execute([$card]);
                    } $member = $stmt->fetch();
                    if (!$member) {
                        $pdo->rollBack(); $unknownCard = true; $error = 'Ce code ne correspond à aucun membre. Vérifiez la carte ou demandez au membre de présenter son dernier QR. Aucun passage n’a été enregistré.';
                    } else {
                        $stmt = $pdo->prepare("SELECT p.name, s.start_date, s.end_date, 'Valide' AS display_status FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE s.member_id=? AND s.status='active' AND s.start_date<=CURDATE() AND s.end_date>=CURDATE() ORDER BY s.end_date DESC LIMIT 1");
                        $stmt->execute([$member['id']]); $subscription = $stmt->fetch();
                        $accepted = $member['status'] === 'active' && (bool)$subscription;
                        if (!$subscription) {
                            // Afficher la dernière formule même si aucune n'est valide, sans autoriser l'accès.
                            $stmt = $pdo->prepare("SELECT p.name, s.start_date, s.end_date, CASE WHEN s.status='frozen' THEN 'Gelé' WHEN s.status='cancelled' THEN 'Annulé' WHEN s.start_date>CURDATE() THEN 'À venir' WHEN s.end_date<CURDATE() THEN 'Expiré' ELSE 'Inactif' END AS display_status FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE s.member_id=? ORDER BY s.end_date DESC,s.id DESC LIMIT 1");
                            $stmt->execute([$member['id']]); $subscription = $stmt->fetch();
                        }
                        $reason = $accepted ? 'Abonnement valide' : ($member['status'] === 'blacklisted' ? 'Membre sur liste noire' : ($member['status'] !== 'active' ? 'Membre inactif' : 'Aucun abonnement actif et valide'));
                        $pdo->prepare('INSERT INTO attendance (member_id, result) VALUES (?, ?)')->execute([$member['id'], $accepted ? 'accepted' : 'refused']);
                        $attendanceId = (int)$pdo->lastInsertId();
                        $stmt = $pdo->prepare('SELECT checked_in_at FROM attendance WHERE id=?'); $stmt->execute([$attendanceId]); $checkedInAt = $stmt->fetchColumn();
                        $pdo->prepare('INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)')->execute([$_SESSION['user']['id'], 'Pointage '.($accepted ? 'accepté' : 'refusé').' membre #'.$member['id'], substr($_SERVER['REMOTE_ADDR'] ?? '',0,45)]);
                        $pdo->commit();
                        // PRG évite l'enregistrement répété au rechargement de la page.
                        $_SESSION['attendance_result'] = ['accepted'=>$accepted, 'name'=>$member['first_name'].' '.$member['last_name'], 'card'=>$member['card_number'] ?: 'Non renseigné', 'phone'=>$member['phone'] ?: 'Non renseigné', 'member_status'=>$member['status'], 'reason'=>$reason, 'plan'=>$subscription['name'] ?? 'Aucun abonnement', 'subscription_status'=>$subscription['display_status'] ?? 'Aucun', 'start_date'=>$subscription['start_date'] ?? null, 'end_date'=>$subscription['end_date'] ?? null, 'checked_in_at'=>$checkedInAt];
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
