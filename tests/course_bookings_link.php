<?php
declare(strict_types=1);
function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); } function gym_name(): string { return 'Salle'; } function gym_brand_markup(): string { return 'Salle'; } function csrf(): string { return 'csrf'; }
$_SESSION=['user'=>['id'=>1,'name'=>'Coach','role'=>'Coach']]; $_POST=[]; $error=''; $flash=''; $canWrite=false; $filterError=''; $filters=['q'=>'','from'=>'','to'=>'','status'=>'']; $result=['total'=>0,'page'=>1,'pages'=>1]; $courseUrl=static fn(array $extra=[])=>'courses?'.http_build_query($extra);
$selectedCourse=['id'=>7,'title'=>'<Yoga>','starts_at'=>'2020-01-01 10:00:00','room'=>'Salle','status'=>'cancelled']; $bookings=[]; $courses=[]; $members=[]; $rooms=[]; $coaches=[];
ob_start(); require __DIR__.'/../app/views/courses.php'; $html=ob_get_clean();
if (!str_contains($html,'course_bookings?id=7') || !str_contains($html,'&lt;Yoga&gt;')) throw new RuntimeException('Lien vers le registre absent');
echo "Cours : lien au registre pour un cours annulé OK\n";
