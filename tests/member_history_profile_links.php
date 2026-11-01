<?php
declare(strict_types=1);
function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function gym_name(): string { return 'Salle'; } function gym_brand_markup(): string { return 'Salle'; } function csrf(): string { return 'csrf'; } function dt($v): string { return number_format((float)$v,3,',',' ').' DT'; }
$member=['id'=>1,'first_name'=>'Ali','last_name'=>'Test','phone'=>null,'card_number'=>'PF1','governorate'=>null,'status'=>'active','created_at'=>'2026-01-01'];
$profile=['member'=>$member,'canContacts'=>false,'canPayments'=>false,'canAttendance'=>true,'subscriptions'=>[],'subscriptionCount'=>0,'attendance'=>[],'attendanceCount'=>0]; $canWrite=false; $_SESSION=['user'=>['name'=>'Coach','role'=>'Coach']];
ob_start(); require __DIR__.'/../app/views/member_profile.php'; $coach=ob_get_clean();
if (!str_contains($coach,'type=subscriptions') || !str_contains($coach,'type=attendance') || str_contains($coach,'type=payments')) throw new RuntimeException('Liens coach incorrects');
$profile['canAttendance']=false; $profile['canPayments']=true; $profile['payments']=[]; $profile['paymentCount']=0; $_SESSION=['user'=>['name'=>'Comptable','role'=>'Comptable']];
ob_start(); require __DIR__.'/../app/views/member_profile.php'; $accountant=ob_get_clean();
if (!str_contains($accountant,'type=payments') || str_contains($accountant,'type=attendance')) throw new RuntimeException('Liens comptable incorrects');
echo "Fiche membre : liens d’historique limités aux rôles autorisés OK\n";
