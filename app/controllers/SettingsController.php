<?php
declare(strict_types=1);
require_once __DIR__.'/../models/GymSettings.php';
class SettingsController {
    public function index(): void {
        $role=$_SESSION['user']['role'];
        if (!in_array($role,['Admin','Gérant','Comptable'],true)) { http_response_code(403); exit('Accès interdit.'); }
        $canWrite=in_array($role,['Admin','Gérant'],true); $model=new GymSettings(db()); $error='';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf(); if (!$canWrite) { http_response_code(403); exit('Accès interdit.'); }
            try { $model->save($_POST,(int)$_SESSION['user']['id'],$_SERVER['REMOTE_ADDR'] ?? ''); $_SESSION['flash']='Paramètres de la salle enregistrés.'; redirect('settings'); }
            catch (DomainException $ex) { $error=$ex->getMessage(); }
        }
        $settings=$model->get();
        if ($error) foreach (['phone','email','address','tax_id','opening_hours','vat_enabled','vat_rate','fiscal_stamp'] as $key) if (isset($_POST[$key]) && is_string($_POST[$key])) $settings[$key]=$_POST[$key];
        $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/settings.php';
    }
}
