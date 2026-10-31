<?php
declare(strict_types=1);
require __DIR__ . '/../app/core/bootstrap.php';
require __DIR__ . '/../app/controllers/AuthController.php';
require __DIR__ . '/../app/controllers/DashboardController.php';
require __DIR__ . '/../app/controllers/ManagementController.php';
require __DIR__ . '/../app/controllers/PaymentController.php';
require __DIR__ . '/../app/controllers/AttendanceController.php';
require __DIR__ . '/../app/controllers/CourseController.php';
require __DIR__ . '/../app/controllers/CoachController.php';
require __DIR__ . '/../app/controllers/FitnessController.php';
require __DIR__ . '/../app/controllers/ShopController.php';
require __DIR__ . '/../app/controllers/EquipmentController.php';
require __DIR__ . '/../app/controllers/FinanceController.php';
require __DIR__ . '/../app/controllers/MarketingController.php';
$page = $_GET['page'] ?? (isset($_SESSION['user']) ? 'dashboard' : 'login');
try {
    if ($page === 'logout') { (new AuthController())->logout(); }
    elseif ($page === 'login') { if (isset($_SESSION['user'])) redirect('dashboard'); (new AuthController())->login(); }
    elseif ($page === 'dashboard') { if (!isset($_SESSION['user'])) redirect('login'); (new DashboardController())->index(); }
    elseif (in_array($page, ['members', 'subscriptions'], true)) { if (!isset($_SESSION['user'])) redirect('login'); (new ManagementController())->$page(); }
    elseif (in_array($page, ['payments', 'receipt'], true)) { if (!isset($_SESSION['user'])) redirect('login'); $controller = new PaymentController(); if ($page === 'receipt') $controller->receipt(); else $controller->index(); }
    elseif ($page === 'attendance') { if (!isset($_SESSION['user'])) redirect('login'); (new AttendanceController())->index(); }
    elseif ($page === 'courses') { if (!isset($_SESSION['user'])) redirect('login'); (new CourseController())->index(); }
    elseif ($page === 'coaches') { if (!isset($_SESSION['user'])) redirect('login'); (new CoachController())->index(); }
    elseif ($page === 'fitness') { if (!isset($_SESSION['user'])) redirect('login'); (new FitnessController())->index(); }
    elseif ($page === 'shop') { if (!isset($_SESSION['user'])) redirect('login'); (new ShopController())->index(); }
    elseif ($page === 'equipment') { if (!isset($_SESSION['user'])) redirect('login'); (new EquipmentController())->index(); }
    elseif (in_array($page, ['finance', 'finance_export'], true)) { if (!isset($_SESSION['user'])) redirect('login'); $controller = new FinanceController(); if ($page === 'finance_export') $controller->export(); else $controller->index(); }
    elseif ($page === 'marketing') { if (!isset($_SESSION['user'])) redirect('login'); (new MarketingController())->index(); }
    else { http_response_code(404); echo 'Page introuvable'; }
} catch (PDOException $ex) {
    error_log($ex->getMessage()); http_response_code(503); echo 'Service indisponible. Vérifiez la configuration de la base de données.';
}
