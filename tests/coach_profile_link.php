<?php
declare(strict_types=1);
function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); } function dt($v): string { return number_format((float)$v,3,',',' ').' DT'; } function csrf(): string { return 'csrf'; } function gym_name(): string { return 'Salle'; } function gym_brand_markup(): string { return 'Salle'; }
$_SESSION=['user'=>['name'=>'Gérant','role'=>'Gérant']]; $_POST=[]; $error=''; $flash=''; $manageCoaches=true; $manageSessions=false; $seeMoney=true;
$coaches=[['id'=>5,'name'=>'<Ali>','email'=>'c@example.tn','specialties'=>'Fitness','phone'=>'','commission_rate'=>'10.00','is_active'=>1]]; $sessions=[]; $commissions=[]; $members=[];
ob_start(); require __DIR__.'/../app/views/coaches.php'; $html=ob_get_clean(); if (!str_contains($html,'page=coach_profile&amp;id=5') || !str_contains($html,'&lt;Ali&gt;')) throw new RuntimeException('Lien profil ou échappement manquant');
$manageCoaches=false; $seeMoney=false; $_SESSION['user']['role']='Coach'; ob_start(); require __DIR__.'/../app/views/coaches.php'; $html=ob_get_clean(); if (str_contains($html,'page=coach_profile')) throw new RuntimeException('Lien affiché au Coach');
echo "Profil coach : lien et visibilité selon le rôle OK\n";
