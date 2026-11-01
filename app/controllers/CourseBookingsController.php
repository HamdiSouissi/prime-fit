<?php
require_once __DIR__.'/../services/CourseBookings.php';
class CourseBookingsController {
    public function index(): void {
        if (!in_array($_SESSION['user']['role'] ?? '',['Admin','Gérant','Réceptionniste','Coach'],true)) { http_response_code(403); exit('Accès interdit.'); }
        if ($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); exit('Méthode non autorisée.'); }
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if (!$id) { http_response_code(404); exit('Cours introuvable.'); }
        $service=new CourseBookings(db()); $course=$service->course((int)$id); if (!$course) { http_response_code(404); exit('Cours introuvable.'); }
        $error=''; $status=''; $result=['rows'=>[],'total'=>0,'page'=>1,'pages'=>1];
        try {
            $status=CourseBookings::status($_GET);
            $page=filter_var($_GET['p'] ?? 1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: 1;
            $result=$service->page((int)$id,$status,(int)$page);
        } catch (DomainException $ex) { $error=$ex->getMessage(); http_response_code(422); }
        header('Cache-Control: private, no-store'); require __DIR__.'/../views/course_bookings.php';
    }
}
