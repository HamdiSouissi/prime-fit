<?php
require_once __DIR__.'/../services/CourseEditService.php';
class CourseEditController {
    public function index(): void {
        if (!in_array($_SESSION['user']['role'],['Admin','Gérant','Réceptionniste'],true)) { http_response_code(403); exit('Accès interdit.'); }
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if (!$id) { http_response_code(404); exit('Cours introuvable.'); }
        $pdo=db(); $error='';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf(); $version=$_POST['version'] ?? '';
            try {
                if (!is_string($version) || !preg_match('/^[a-f0-9]{64}$/D',$version)) throw new DomainException('Formulaire invalide. Actualisez la page.');
                $promoted=(new CourseEditService($pdo,(int)$_SESSION['user']['id'],$_SERVER['REMOTE_ADDR'] ?? ''))->edit($id,$version,$_POST);
                $_SESSION['flash']='Cours mis à jour. '.($promoted ? $promoted.' inscription(s) en attente confirmée(s). Aucun message automatique n’a été envoyé.' : 'Aucune notification automatique n’a été envoyée.'); redirect('courses&course='.$id);
            } catch (DomainException $ex) { $error=$ex->getMessage(); }
              catch (PDOException $ex) {
                  if (in_array((int)($ex->errorInfo[1] ?? 0),[1205,1213],true)) $error='Une modification concurrente a empêché l’opération. Vérifiez le planning actuel puis réessayez.'; else throw $ex;
              }
        }
        $stmt=$pdo->prepare('SELECT * FROM courses WHERE id=?'); $stmt->execute([$id]); $course=$stmt->fetch(); if (!$course) { http_response_code(404); exit('Cours introuvable.'); }
        $version=CourseEditService::version($course); $rooms=$pdo->query('SELECT id,name FROM rooms ORDER BY name')->fetchAll();
        $stmt=$pdo->prepare("SELECT id,name,is_active FROM users WHERE role='Coach' AND (is_active=1 OR id=?) ORDER BY name"); $stmt->execute([$course['coach_id'] ?? 0]); $coaches=$stmt->fetchAll();
        $stmt=$pdo->prepare("SELECT COUNT(*) FROM course_bookings WHERE course_id=? AND status='confirmed'"); $stmt->execute([$id]); $confirmed=(int)$stmt->fetchColumn();
        $stmt=$pdo->prepare("SELECT COUNT(*) FROM course_bookings WHERE course_id=? AND status='waiting'"); $stmt->execute([$id]); $waiting=(int)$stmt->fetchColumn();
        require __DIR__.'/../views/course_edit.php';
    }
}
