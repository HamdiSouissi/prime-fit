<?php
declare(strict_types=1);
require __DIR__.'/../app/services/SubscriptionService.php';
function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function dt($v): string { return number_format((float)$v,3,',',' ').' DT'; }
function gym_name(): string { return 'Salle'; } function gym_brand_markup(): string { return 'Salle'; } function csrf(): string { return 'csrf'; }
$_SESSION=['user'=>['name'=>'Admin','role'=>'Admin']]; $error=''; $flash='';
$subscription=['id'=>1,'member_id'=>1,'plan_id'=>1,'start_date'=>'2026-01-01','end_date'=>'2026-01-30','status'=>'active','member_status'=>'active','agreed_price'=>'90.000','first_name'=>'<Ali>','last_name'=>'Test','plan'=>'Mensuel'];
$plans=[['id'=>2,'name'=>'<Trimestriel>','duration_days'=>90,'price'=>'230.125','is_active'=>1]]; $chosenPlan=2; $start='2026-01-31'; $version=SubscriptionService::version($subscription);
ob_start(); require __DIR__.'/../app/views/subscription_renew.php'; $html=ob_get_clean();
foreach (['&lt;Ali&gt;','&lt;Trimestriel&gt;','230,125 DT','31/01/2026','name="subscription_version"',' selected'] as $needle) if (!str_contains($html,$needle)) throw new RuntimeException('Élément manquant : '.$needle);
if (str_contains($html,'<Trimestriel>') || !str_contains($html,'Aucun paiement')) throw new RuntimeException('Protection ou indication incorrecte');
$subscription['status']='frozen'; ob_start(); require __DIR__.'/../app/views/subscription_renew.php'; $html=ob_get_clean(); if (str_contains($html,'Confirmer le renouvellement</button>')) throw new RuntimeException('Formulaire visible pour un abonnement gelé');
echo "Renouvellement : vue, sélection et échappement OK\n";
