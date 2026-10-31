<?php
class CourseController {
    private function canWrite(): bool { return in_array($_SESSION['user']['role'], ['Admin','Gérant','Réceptionniste'], true); }
    private function log(PDO $pdo, string $action): void {
        $pdo->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$_SESSION['user']['id'],$action,substr($_SERVER['REMOTE_ADDR'] ?? '',0,45)]);
    }
    private function fail(string $message): void { throw new DomainException($message); }
    private function localDateTime(string $value): ?string {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
        return $d && $d->format('Y-m-d\TH:i') === $value ? $d->format('Y-m-d H:i:s') : null;
    }
    public function index(): void {
        if (!in_array($_SESSION['user']['role'], ['Admin','Gérant','Réceptionniste','Coach'], true)) { http_response_code(403); exit('Accès interdit.'); }
        $canWrite=$this->canWrite(); $error='';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf(); if (!$canWrite) { http_response_code(403); exit('Accès interdit.'); }
            $pdo=db();
            try {
                $action=$_POST['action'] ?? '';
                if ($action==='room') {
                    $name=trim($_POST['name'] ?? ''); if ($name==='' || mb_strlen($name)>100) $this->fail('Indiquez un nom de salle valide.');
                    $pdo->beginTransaction(); $pdo->prepare('INSERT INTO rooms (name) VALUES (?)')->execute([$name]); $this->log($pdo,'Salle créée #'.$pdo->lastInsertId()); $pdo->commit();
                    $_SESSION['flash']='Salle créée.';
                } elseif ($action==='course') {
                    $title=trim($_POST['title'] ?? ''); $room=filter_var($_POST['room_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $coach=filter_var($_POST['coach_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $capacity=filter_var($_POST['capacity'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000]]);
                    $start=$this->localDateTime($_POST['starts_at'] ?? ''); $end=$this->localDateTime($_POST['ends_at'] ?? '');
                    if ($title==='' || mb_strlen($title)>120 || !$room || !$capacity || !$start || !$end || $end <= $start || $start <= date('Y-m-d H:i:s')) $this->fail('Vérifiez le titre, la salle, la capacité et les horaires futurs du cours.');
                    $pdo->beginTransaction();
                    // Le verrou de salle sérialise les créations et empêche les chevauchements.
                    $stmt=$pdo->prepare('SELECT id FROM rooms WHERE id=? FOR UPDATE'); $stmt->execute([$room]); if (!$stmt->fetch()) $this->fail('Salle introuvable.');
                    if ($coach) { $stmt=$pdo->prepare("SELECT id FROM users WHERE id=? AND role='Coach' AND is_active=1"); $stmt->execute([$coach]); if (!$stmt->fetch()) $this->fail('Coach introuvable ou inactif.'); }
                    elseif (($_POST['coach_id'] ?? '') !== '') $this->fail('Coach invalide.');
                    $stmt=$pdo->prepare("SELECT id FROM courses WHERE room_id=? AND status='scheduled' AND starts_at < ? AND ends_at > ? LIMIT 1"); $stmt->execute([$room,$end,$start]); if ($stmt->fetch()) $this->fail('Cette salle est déjà occupée sur ce créneau.');
                    $pdo->prepare('INSERT INTO courses (title,room_id,coach_id,starts_at,ends_at,capacity) VALUES (?,?,?,?,?,?)')->execute([$title,$room,$coach ?: null,$start,$end,$capacity]); $this->log($pdo,'Cours créé #'.$pdo->lastInsertId()); $pdo->commit(); $_SESSION['flash']='Cours ajouté au planning.';
                } elseif (in_array($action,['book','cancel_booking','cancel_course'],true)) {
                    $course=filter_var($_POST['course_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if (!$course) $this->fail('Cours invalide.');
                    $pdo->beginTransaction(); $stmt=$pdo->prepare('SELECT * FROM courses WHERE id=? FOR UPDATE'); $stmt->execute([$course]); $c=$stmt->fetch();
                    if (!$c || $c['status'] !== 'scheduled' || $c['starts_at'] <= date('Y-m-d H:i:s')) $this->fail('Ce cours est annulé, commencé ou introuvable.');
                    if ($action==='cancel_course') {
                        $pdo->prepare("UPDATE courses SET status='cancelled' WHERE id=?")->execute([$course]);
                        $pdo->prepare("UPDATE course_bookings SET status='cancelled' WHERE course_id=? AND status<>'cancelled'")->execute([$course]);
                        $this->log($pdo,'Cours annulé #'.$course); $_SESSION['flash']='Cours et réservations annulés.';
                    } elseif ($action==='book') {
                        $member=filter_var($_POST['member_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if (!$member) $this->fail('Sélectionnez un membre.');
                        $stmt=$pdo->prepare("SELECT m.id FROM members m WHERE m.id=? AND m.status='active' AND EXISTS (SELECT 1 FROM subscriptions s WHERE s.member_id=m.id AND s.status='active' AND s.start_date <= DATE(?) AND s.end_date >= DATE(?))"); $stmt->execute([$member,$c['starts_at'],$c['starts_at']]); if (!$stmt->fetch()) $this->fail('Le membre doit être actif et disposer d’un abonnement valide à la date du cours.');
                        $stmt=$pdo->prepare('SELECT id,status FROM course_bookings WHERE course_id=? AND member_id=?'); $stmt->execute([$course,$member]); $existing=$stmt->fetch(); if ($existing && $existing['status']!=='cancelled') $this->fail('Ce membre est déjà inscrit à ce cours.');
                        $stmt=$pdo->prepare("SELECT COUNT(*) FROM course_bookings WHERE course_id=? AND status='confirmed'"); $stmt->execute([$course]); $status=(int)$stmt->fetchColumn() < (int)$c['capacity'] ? 'confirmed' : 'waiting';
                        if ($existing) $pdo->prepare('UPDATE course_bookings SET status=?,created_at=NOW() WHERE id=?')->execute([$status,$existing['id']]); else $pdo->prepare('INSERT INTO course_bookings (course_id,member_id,status) VALUES (?,?,?)')->execute([$course,$member,$status]);
                        $this->log($pdo,'Réservation '.$status.' cours #'.$course.' membre #'.$member); $_SESSION['flash']=$status==='confirmed' ? 'Réservation confirmée.' : 'Cours complet : membre ajouté à la liste d’attente.';
                    } else {
                        $booking=filter_var($_POST['booking_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $stmt=$pdo->prepare("SELECT * FROM course_bookings WHERE id=? AND course_id=? AND status<>'cancelled'"); $stmt->execute([$booking ?: 0,$course]); $b=$stmt->fetch(); if (!$b) $this->fail('Réservation introuvable.');
                        $pdo->prepare("UPDATE course_bookings SET status='cancelled' WHERE id=?")->execute([$booking]);
                        $promoted=false;
                        if ($b['status']==='confirmed') {
                            // Seuls les membres toujours éligibles sont promus, dans l'ordre d'inscription.
                            $stmt=$pdo->prepare("SELECT b.id FROM course_bookings b JOIN members m ON m.id=b.member_id WHERE b.course_id=? AND b.status='waiting' AND m.status='active' AND EXISTS (SELECT 1 FROM subscriptions s WHERE s.member_id=m.id AND s.status='active' AND s.start_date<=DATE(?) AND s.end_date>=DATE(?)) ORDER BY b.created_at,b.id LIMIT 1"); $stmt->execute([$course,$c['starts_at'],$c['starts_at']]); $next=$stmt->fetchColumn();
                            if ($next) { $pdo->prepare("UPDATE course_bookings SET status='confirmed' WHERE id=?")->execute([$next]); $this->log($pdo,'Liste d’attente promue réservation #'.$next); $promoted=true; }
                        }
                        $this->log($pdo,'Réservation annulée #'.$booking); $_SESSION['flash']=$promoted ? 'Réservation annulée ; la première personne éligible en attente a été confirmée.' : 'Réservation annulée.';
                    }
                    $pdo->commit();
                } else $this->fail('Action invalide.');
                redirect(isset($course) && in_array($action, ['book','cancel_booking'], true) ? 'courses&course='.$course : 'courses');
            } catch (DomainException $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); $error=$ex->getMessage(); }
              catch (PDOException $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); if ($ex->getCode()==='23000') $error='Cette salle ou réservation existe déjà.'; else throw $ex; }
        }
        $rooms=db()->query('SELECT * FROM rooms ORDER BY name')->fetchAll();
        $coaches=db()->query("SELECT id,name FROM users WHERE role='Coach' AND is_active=1 ORDER BY name")->fetchAll();
        $members=db()->query("SELECT id,first_name,last_name,card_number FROM members WHERE status='active' ORDER BY last_name,first_name")->fetchAll();
        $courses=db()->query("SELECT c.*, r.name AS room, u.name AS coach, (SELECT COUNT(*) FROM course_bookings b WHERE b.course_id=c.id AND b.status='confirmed') AS confirmed, (SELECT COUNT(*) FROM course_bookings b WHERE b.course_id=c.id AND b.status='waiting') AS waiting FROM courses c JOIN rooms r ON r.id=c.room_id LEFT JOIN users u ON u.id=c.coach_id WHERE c.starts_at >= CURDATE() ORDER BY c.starts_at LIMIT 100")->fetchAll();
        $selected=filter_var($_GET['course'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $selectedCourse=null; $bookings=[];
        if ($selected) {
            $stmt=db()->prepare('SELECT c.*,r.name AS room FROM courses c JOIN rooms r ON r.id=c.room_id WHERE c.id=?'); $stmt->execute([$selected]); $selectedCourse=$stmt->fetch();
            if (!$selectedCourse) { http_response_code(404); exit('Cours introuvable.'); }
            $stmt=db()->prepare("SELECT b.*,m.first_name,m.last_name FROM course_bookings b JOIN members m ON m.id=b.member_id WHERE b.course_id=? AND b.status<>'cancelled' ORDER BY CASE b.status WHEN 'confirmed' THEN 0 ELSE 1 END,b.created_at,b.id"); $stmt->execute([$selected]); $bookings=$stmt->fetchAll();
        }
        $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/courses.php';
    }
}
