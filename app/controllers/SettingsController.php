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
            catch (PDOException $ex) {
                if (($ex->errorInfo[0] ?? '')==='42S22' && (int)($ex->errorInfo[1] ?? 0)===1054 && stripos((string)($ex->errorInfo[2] ?? ''),'gym_name')!==false) $error='Après sauvegarde, importez database/gym_name.sql dans la base utilisée, puis réessayez.';
                else throw $ex;
            }
        }
        $settings=$model->get();
        if (GymBrand::clean($settings['gym_name'] ?? null)===null) $settings['gym_name']=gym_name();
        if ($error) foreach (['gym_name','phone','email','address','tax_id','opening_hours','vat_enabled','vat_rate','fiscal_stamp'] as $key) if (isset($_POST[$key]) && is_string($_POST[$key])) $settings[$key]=$_POST[$key];
        $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/settings.php';
    }
}
