<?php
require_once __DIR__.'/../services/CoachProfileService.php';
class CoachProfileController {
    public function index(): void {
        if (!in_array($_SESSION['user']['role'] ?? '',['Admin','Gérant'],true)) { http_response_code(403); exit('Accès interdit.'); }
        header('Cache-Control: private, no-store');
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id) { http_response_code(404); exit('Profil introuvable.'); }
        $pdo=db(); $service=new CoachProfileService($pdo,(int)$_SESSION['user']['id'],$_SERVER['REMOTE_ADDR'] ?? ''); $error='';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf(); $version=$_POST['version'] ?? '';
            try {
                if (!is_string($version)) throw new DomainException('Formulaire invalide. Actualisez la page.');
                $service->update((int)$id,$version,$_POST);
                $_SESSION['flash']='Profil du coach mis à jour. Les commissions des séances existantes sont inchangées.'; redirect('coach_profile&id='.$id);
            } catch (DomainException $ex) { $error=$ex->getMessage(); }
              catch (PDOException $ex) { if (in_array((int)($ex->errorInfo[1] ?? 0),[1205,1213],true)) $error='Une modification concurrente a empêché l’enregistrement. Vérifiez le profil puis réessayez.'; else throw $ex; }
        } elseif ($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); exit('Méthode non autorisée.'); }
        $coach=$service->get((int)$id); if (!$coach) { http_response_code(404); exit('Profil introuvable.'); }
        $version=CoachProfileService::version($coach); $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
        require __DIR__.'/../views/coach_profile.php';
    }
}
