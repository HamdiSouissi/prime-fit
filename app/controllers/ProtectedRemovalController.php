<?php
declare(strict_types=1);
require_once __DIR__.'/../services/ProtectedRemoval.php';
require_once __DIR__.'/../services/LoginRateLimiter.php';
final class ProtectedRemovalController {
    public function index(): void {
        if (($_SESSION['user']['role'] ?? '')!=='Admin') { http_response_code(403); exit('Accès interdit.'); }
        header('Cache-Control: no-store, private'); $pdo=db(); $error='';
        $type=$_GET['type'] ?? 'members'; if (!is_string($type) || !isset(ProtectedRemoval::TYPES[$type])) { http_response_code(404); exit('Type introuvable.'); }
        $search=is_string($_GET['q'] ?? null) ? trim($_GET['q']) : ''; $page=filter_var($_GET['p'] ?? 1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]) ?: 1;
        $service=new ProtectedRemoval($pdo,(int)$_SESSION['user']['id'],(string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf();
            try {
                $retry=(new LoginRateLimiter($pdo))->reserve((string)($_SERVER['REMOTE_ADDR'] ?? ''),'removal-admin:'.(int)$_SESSION['user']['id']);
                if ($retry>0) { http_response_code(429); header('Retry-After: '.$retry); throw new DomainException('Trop de tentatives. Réessayez plus tard.'); }
                $ids=$_POST['ids'] ?? []; $versions=$_POST['versions'] ?? []; $password=$_POST['admin_password'] ?? '';
                if (!is_array($ids) || !is_array($versions) || !is_string($password)) throw new DomainException('Formulaire invalide.');
                $results=$service->execute($type,$ids,$versions,$password);
                $_SESSION['flash']='Opération terminée : '.implode(' ; ',array_map(static fn($id,$result)=>'#'.$id.' : '.$result,array_keys($results),$results)).'.';
                redirect('removals&type='.$type);
            } catch (DomainException $ex) { $error=$ex->getMessage(); }
              catch (PDOException $ex) {
                  error_log('Suppression protégée indisponible : '.$ex->getMessage());
                  $error='Opération annulée : vérifiez les modules et les données liées, puis réessayez. Aucun élément du lot n’a été modifié.';
              }
        }
        try { $result=$service->listing($type,$search,(int)$page); }
        catch (DomainException $e) { $error=$e->getMessage(); $result=$service->listing($type,'',1); }
        $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/protected_removals.php';
    }
}
