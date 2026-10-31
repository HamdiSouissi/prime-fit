<?php
/** Suivi sportif : données sensibles, accès limité aux équipes autorisées. */
class FitnessController {
    private function fail(string $message): void { throw new DomainException($message); }
    private function decimal($value, float $min, float $max): ?string {
        $value = str_replace(',', '.', trim((string)$value));
        if (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $value) || (float)$value < $min || (float)$value > $max) return null;
        return $value;
    }
    public function index(): void {
        if (!in_array($_SESSION['user']['role'], ['Admin', 'Gérant', 'Coach'], true)) { http_response_code(403); exit('Accès interdit.'); }
        $pdo = db(); $error = '';
        $memberId = filter_var($_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['member_id'] ?? '') : ($_GET['member'] ?? ''), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $member = null;
        if ($memberId) {
            $stmt = $pdo->prepare('SELECT id,first_name,last_name,card_number FROM members WHERE id=?'); $stmt->execute([$memberId]); $member = $stmt->fetch();
            if (!$member) { http_response_code(404); exit('Membre introuvable.'); }
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            try {
                if (!$member) $this->fail('Sélectionnez un membre valide.');
                $action = $_POST['action'] ?? '';
                $pdo->beginTransaction();
                if ($action === 'measurement') {
                    $date = trim($_POST['measured_on'] ?? ''); $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                    $weight = $this->decimal($_POST['weight_kg'] ?? '', 10, 500); $height = $this->decimal($_POST['height_cm'] ?? '', 50, 250);
                    $waistRaw = trim($_POST['waist_cm'] ?? ''); $waist = $waistRaw === '' ? null : $this->decimal($waistRaw, 20, 300);
                    $notes = trim($_POST['notes'] ?? '');
                    if (!$parsed || $parsed->format('Y-m-d') !== $date || $date > date('Y-m-d') || $weight === null || $height === null || ($waistRaw !== '' && $waist === null) || mb_strlen($notes) > 500) $this->fail('Vérifiez la date et les mensurations : poids 10–500 kg, taille 50–250 cm et tour de taille 20–300 cm.');
                    $pdo->prepare('INSERT INTO fitness_measurements (member_id,measured_on,weight_kg,height_cm,waist_cm,notes,created_by) VALUES (?,?,?,?,?,?,?)')->execute([$memberId,$date,$weight,$height,$waist,$notes,$_SESSION['user']['id']]);
                    $id = (int)$pdo->lastInsertId(); $message = 'Mensurations enregistrées.'; $audit = 'Mensurations créées #'.$id;
                } elseif ($action === 'program') {
                    $title=trim($_POST['title'] ?? ''); $objective=trim($_POST['objective'] ?? ''); $content=trim($_POST['content'] ?? '');
                    if ($title==='' || mb_strlen($title)>120 || $objective==='' || mb_strlen($objective)>500 || $content==='' || mb_strlen($content)>10000) $this->fail('Renseignez un titre, un objectif et un programme valides.');
                    $pdo->prepare('INSERT INTO fitness_programs (member_id,title,objective,content,created_by) VALUES (?,?,?,?,?)')->execute([$memberId,$title,$objective,$content,$_SESSION['user']['id']]);
                    $id = (int)$pdo->lastInsertId(); $message = 'Programme enregistré.'; $audit = 'Programme créé #'.$id;
                } elseif ($action === 'archive') {
                    $id=filter_var($_POST['program_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                    $stmt=$pdo->prepare("UPDATE fitness_programs SET status='archived' WHERE id=? AND member_id=? AND status='active'"); $stmt->execute([$id ?: 0,$memberId]);
                    if (!$stmt->rowCount()) $this->fail('Programme actif introuvable.');
                    $message='Programme archivé.'; $audit='Programme archivé #'.$id;
                } else $this->fail('Action invalide.');
                $pdo->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$_SESSION['user']['id'],$audit,substr($_SERVER['REMOTE_ADDR'] ?? '',0,45)]);
                $pdo->commit(); $_SESSION['flash']=$message; redirect('fitness&member='.$memberId);
            } catch (DomainException $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); $error=$ex->getMessage(); }
              catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $ex; }
        }
        $members=$pdo->query('SELECT id,first_name,last_name,card_number FROM members ORDER BY last_name,first_name')->fetchAll();
        $measurements=[]; $programs=[];
        if ($member) {
            $stmt=$pdo->prepare('SELECT * FROM fitness_measurements WHERE member_id=? ORDER BY measured_on DESC,id DESC LIMIT 100'); $stmt->execute([$memberId]); $measurements=$stmt->fetchAll();
            $stmt=$pdo->prepare('SELECT * FROM fitness_programs WHERE member_id=? ORDER BY created_at DESC,id DESC LIMIT 100'); $stmt->execute([$memberId]); $programs=$stmt->fetchAll();
        }
        $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/fitness.php';
    }
}
