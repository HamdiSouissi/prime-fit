<?php
require_once __DIR__.'/../services/MarketingConsent.php';
class MarketingConsentController {
    public function index(): void {
        if (!in_array($_SESSION['user']['role'],['Admin','Gérant','Réceptionniste'],true)) { http_response_code(403); exit('Accès interdit.'); }
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if (!$id) { http_response_code(404); exit('Membre introuvable.'); }
        header('Cache-Control: no-store');
        $pdo=db(); $service=new MarketingConsent($pdo,(int)$_SESSION['user']['id'],$_SERVER['REMOTE_ADDR'] ?? ''); $error='';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf(); $version=filter_var($_POST['version'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]); $decision=$_POST['decision'] ?? null; $evidence=$_POST['evidence'] ?? null; $phone=$_POST['phone_snapshot'] ?? null;
            try {
                if ($version===false || !is_string($decision) || !is_string($evidence) || !is_string($phone)) throw new DomainException('Vérifiez les champs du formulaire.');
                $service->record((int)$id,(int)$version,$decision,$evidence,$phone); $_SESSION['flash']='Préférence WhatsApp enregistrée.'; redirect('marketing_consent&id='.$id);
            } catch (DomainException $ex) { $error=$ex->getMessage(); }
        }
        $stmt=$pdo->prepare('SELECT id,first_name,last_name,phone,status FROM members WHERE id=?'); $stmt->execute([$id]); $member=$stmt->fetch(); if (!$member) { http_response_code(404); exit('Membre introuvable.'); }
        $latest=$service->latest((int)$id); $active=$latest && $latest['decision']==='accepted' && (string)$latest['phone_value']===(string)($member['phone'] ?? '') && MarketingConsent::phone($member['phone'])!==null;
        $stmt=$pdo->prepare('SELECT c.*,u.name AS author FROM marketing_consent_events c LEFT JOIN users u ON u.id=c.created_by WHERE c.member_id=? ORDER BY c.id DESC LIMIT 20'); $stmt->execute([$id]); $history=$stmt->fetchAll();
        $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/marketing_consent.php';
    }
}
