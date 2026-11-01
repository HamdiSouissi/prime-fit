<?php
declare(strict_types=1);
function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function gym_name(): string { return 'Salle'; } function gym_brand_markup(): string { return 'Salle'; } function csrf(): string { return 'test'; } function dt($v): string { return number_format((float)$v,3,',',' ').' DT'; }
$_SESSION=['user'=>['name'=>'Coach','role'=>'Coach']]; $history=['member'=>['id'=>1,'first_name'=>'<Ali>','last_name'=>'Test','card_number'=>'PF1'],'type'=>'subscriptions','rows'=>[['id'=>2,'plan'=>'<Formule>','start_date'=>'2026-01-01','end_date'=>'2026-01-30','status'=>'active']],'total'=>21,'page'=>1,'pages'=>2];
ob_start(); require __DIR__.'/../app/views/member_history.php'; $html=ob_get_clean();
foreach (['&lt;Ali&gt;','&lt;Formule&gt;','01/01/2026','member_history?id=1&amp;type=subscriptions&amp;p=2'] as $needle) if (!str_contains($html,$needle)) throw new RuntimeException('Élément absent : '.$needle);
if (str_contains($html,'<Ali>') || str_contains($html,'<Formule>')) throw new RuntimeException('Affichage non échappé');
$_SESSION=['user'=>['name'=>'Comptable','role'=>'Comptable']]; $history['type']='payments'; $history['rows']=[['id'=>1,'paid_at'=>'2026-01-01 10:00:00','amount'=>'10.000','refunded'=>'2.125','method'=>'D17','subscription_id'=>2]];
ob_start(); require __DIR__.'/../app/views/member_history.php'; $html=ob_get_clean(); if (!str_contains($html,'2,125 DT') || !str_contains($html,'receipt?id=1')) throw new RuntimeException('Paiement non affiché');
echo "Historique membre : rendu, navigation et échappement OK\n";
