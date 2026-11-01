<?php
declare(strict_types=1);
require __DIR__.'/../app/services/CourseBookings.php';
function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); } function gym_name(): string { return 'Salle'; } function gym_brand_markup(): string { return 'Salle'; } function csrf(): string { return 'csrf'; }
$_SESSION=['user'=>['name'=>'Coach','role'=>'Coach']]; $error=''; $flash=''; $course=['id'=>1,'title'=>'<Yoga>','starts_at'=>'2030-01-01 10:00:00','room'=>'<Salle>','status'=>'cancelled']; $status='cancelled';
$result=['total'=>21,'page'=>1,'pages'=>2,'rows'=>[['id'=>1,'first_name'=>'<Ali>','last_name'=>'Test','status'=>'cancelled','created_at'=>'2029-12-01 12:00:00','updated_at'=>'2029-12-02 14:30:00']]];
ob_start(); require __DIR__.'/../app/views/course_bookings.php'; $html=ob_get_clean();
foreach (['&lt;Yoga&gt;','&lt;Salle&gt;','&lt;Ali&gt;','Annulée','01/12/2029 12:00','02/12/2029 14:30','course_bookings?id=1&amp;status=cancelled&amp;p=2','état actuel'] as $v) if (!str_contains($html,$v)) throw new RuntimeException('Rendu absent : '.$v);
if (str_contains($html,'<Yoga>') || str_contains($html,'<Ali>')) throw new RuntimeException('Échappement incorrect');
echo "Cours : rendu du registre et pagination OK\n";
