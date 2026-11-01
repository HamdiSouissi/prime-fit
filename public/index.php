<?php
declare(strict_types=1);
require __DIR__ . '/../app/core/bootstrap.php';
require __DIR__ . '/../app/controllers/AuthController.php';
require __DIR__ . '/../app/controllers/AccountController.php';
require __DIR__ . '/../app/controllers/DashboardController.php';
require __DIR__ . '/../app/controllers/ManagementController.php';
require __DIR__ . '/../app/controllers/PaymentController.php';
require __DIR__ . '/../app/controllers/AttendanceController.php';
require __DIR__ . '/../app/controllers/CourseController.php';
require __DIR__ . '/../app/controllers/CourseEditController.php';
require __DIR__ . '/../app/controllers/CoachController.php';
require __DIR__ . '/../app/controllers/FitnessController.php';
require __DIR__ . '/../app/controllers/ShopController.php';
require __DIR__ . '/../app/controllers/EquipmentController.php';
require __DIR__ . '/../app/controllers/FinanceController.php';
require __DIR__ . '/../app/controllers/MarketingController.php';
require __DIR__ . '/../app/controllers/ReportController.php';
require __DIR__ . '/../app/controllers/SettingsController.php';
require __DIR__ . '/../app/controllers/SubscriptionPriceController.php';
require __DIR__ . '/../app/controllers/PlanController.php';
require __DIR__ . '/../app/controllers/ActivityController.php';
require __DIR__ . '/../app/controllers/UserAdminController.php';
$page = $_GET['page'] ?? (isset($_SESSION['user']) ? 'dashboard' : 'login');
try {
    // Révoque les sessions périmées après changement de mot de passe ou désactivation.
    if (isset($_SESSION['user']) && !in_array($page, ['login','logout'], true)) {
        $stmt = db()->prepare('SELECT id,name,email,role,is_active,password_hash FROM users WHERE id=?');
        $stmt->execute([(int)$_SESSION['user']['id']]); $sessionUser = $stmt->fetch();
        $fingerprint = $_SESSION['auth_fingerprint'] ?? '';
        if (!$sessionUser || !$sessionUser['is_active'] || !is_string($fingerprint) || !hash_equals(hash('sha256', $sessionUser['password_hash']), $fingerprint)) {
            $_SESSION = []; session_regenerate_id(true);
            $_SESSION['login_notice'] = 'Session expirée ou compte modifié. Veuillez vous reconnecter.'; redirect('login');
        }
        unset($sessionUser['password_hash']); $_SESSION['user'] = $sessionUser;
    }
    if ($page === 'logout') { (new AuthController())->logout(); }
    elseif ($page === 'login') { if (isset($_SESSION['user'])) redirect('dashboard'); (new AuthController())->login(); }
    elseif ($page === 'account') { if (!isset($_SESSION['user'])) redirect('login'); (new AccountController())->index(); }
    elseif (in_array($page, ['users','user'], true)) { if (!isset($_SESSION['user'])) redirect('login'); $controller=new UserAdminController(); if ($page==='user') $controller->edit(); else $controller->index(); }
    elseif (in_array($page, ['activity','activity_export'], true)) { if (!isset($_SESSION['user'])) redirect('login'); $controller=new ActivityController(); if ($page==='activity_export') $controller->export(); else $controller->index(); }
    elseif ($page === 'dashboard') { if (!isset($_SESSION['user'])) redirect('login'); (new DashboardController())->index(); }
    elseif ($page === 'member_profile') { if (!isset($_SESSION['user'])) redirect('login'); (new ManagementController())->memberProfile(); }
    elseif ($page === 'member_card') { if (!isset($_SESSION['user'])) redirect('login'); (new ManagementController())->memberCard(); }
    elseif ($page === 'plan') { if (!isset($_SESSION['user'])) redirect('login'); (new PlanController())->index(); }
    elseif ($page === 'subscription_detail') { if (!isset($_SESSION['user'])) redirect('login'); (new ManagementController())->subscriptionDetail(); }
    elseif ($page === 'subscription_prices') { if (!isset($_SESSION['user'])) redirect('login'); (new SubscriptionPriceController())->index(); }
    elseif (in_array($page, ['members', 'subscriptions'], true)) { if (!isset($_SESSION['user'])) redirect('login'); (new ManagementController())->$page(); }
    elseif (in_array($page, ['payments', 'receipt'], true)) { if (!isset($_SESSION['user'])) redirect('login'); $controller = new PaymentController(); if ($page === 'receipt') $controller->receipt(); else $controller->index(); }
    elseif ($page === 'attendance') { if (!isset($_SESSION['user'])) redirect('login'); (new AttendanceController())->index(); }
    elseif ($page === 'course_edit') { if (!isset($_SESSION['user'])) redirect('login'); (new CourseEditController())->index(); }
    elseif ($page === 'courses') { if (!isset($_SESSION['user'])) redirect('login'); (new CourseController())->index(); }
    elseif ($page === 'coaches') { if (!isset($_SESSION['user'])) redirect('login'); (new CoachController())->index(); }
    elseif ($page === 'fitness') { if (!isset($_SESSION['user'])) redirect('login'); (new FitnessController())->index(); }
    elseif ($page === 'shop') { if (!isset($_SESSION['user'])) redirect('login'); (new ShopController())->index(); }
    elseif (in_array($page, ['equipment','equipment_export'], true)) { if (!isset($_SESSION['user'])) redirect('login'); $controller=new EquipmentController(); if ($page==='equipment_export') $controller->export(); else $controller->index(); }
    elseif (in_array($page, ['finance', 'finance_export'], true)) { if (!isset($_SESSION['user'])) redirect('login'); $controller = new FinanceController(); if ($page === 'finance_export') $controller->export(); else $controller->index(); }
    elseif ($page === 'marketing') { if (!isset($_SESSION['user'])) redirect('login'); (new MarketingController())->index(); }
    elseif (in_array($page, ['reports', 'reports_export'], true)) { if (!isset($_SESSION['user'])) redirect('login'); $controller = new ReportController(); if ($page === 'reports_export') $controller->export(); else $controller->index(); }
    elseif ($page === 'settings') { if (!isset($_SESSION['user'])) redirect('login'); (new SettingsController())->index(); }
    else { http_response_code(404); echo 'Page introuvable'; }
} catch (PDOException $ex) {
    error_log($ex->getMessage()); http_response_code(503);
    if (in_array($page, ['member_card', 'attendance'], true) && ManagementController::missingQrColumn($ex)) {
        echo 'La base de données doit être mise à jour pour utiliser les cartes QR. Après sauvegarde, importez database/member_qr.sql dans la base utilisée par l’application, puis réessayez.';
    } elseif ($page === 'settings' && ($ex->errorInfo[0] ?? '') === '42S02' && (int)($ex->errorInfo[1] ?? 0) === 1146 && stripos((string)($ex->errorInfo[2] ?? ''), 'gym_settings') !== false) {
        echo 'La base doit être mise à jour : après sauvegarde, importez database/gym_settings.sql dans la base utilisée, puis réessayez.';
    } elseif ($page === 'course_edit' && ($ex->errorInfo[0] ?? '') === '42S02' && (int)($ex->errorInfo[1] ?? 0) === 1146 && stripos((string)($ex->errorInfo[2] ?? ''), 'private_sessions') !== false) {
        echo 'La table des séances privées est absente. Après sauvegarde, installez le module Coachs pour vérifier la disponibilité du coach.';
    } elseif ($page === 'subscription_detail' && ($ex->errorInfo[0] ?? '') === '42S02' && (int)($ex->errorInfo[1] ?? 0) === 1146 && stripos((string)($ex->errorInfo[2] ?? ''), 'subscription_freezes') !== false) {
        echo 'La base doit être mise à jour : après sauvegarde, importez database/subscription_freezes.sql dans la base utilisée.';
    } elseif ($page === 'settings' && ($ex->errorInfo[0] ?? '') === '42S22' && (int)($ex->errorInfo[1] ?? 0) === 1054 && stripos((string)($ex->errorInfo[2] ?? ''), 'gym_name') !== false) {
        echo 'La base doit être mise à jour : après sauvegarde, importez database/gym_name.sql dans la base utilisée, puis réessayez.';
    } elseif (($ex->errorInfo[0] ?? '') === '42S22' && (int)($ex->errorInfo[1] ?? 0) === 1054 && stripos((string)($ex->errorInfo[2] ?? ''), 'agreed_price') !== false) {
        echo 'La base doit être mise à jour : après sauvegarde, importez database/subscription_prices.sql dans la base utilisée. Les prix des anciens abonnements devront être vérifiés.';
    } else {
        echo 'Service indisponible. Vérifiez la configuration de la base de données et consultez les journaux du serveur.';
    }
}
