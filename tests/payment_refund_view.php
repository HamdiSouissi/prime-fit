<?php
declare(strict_types=1);
function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function dt($v): string { return number_format((float)$v,3,',',' ').' DT'; }
function gym_name(): string { return 'Salle'; } function gym_brand_markup(): string { return 'Salle'; } function csrf(): string { return 'test'; }
$_SESSION=['user'=>['name'=>'Admin','role'=>'Admin']]; $_POST=[]; $error=''; $flash='';
$payment=['id'=>1,'subscription_id'=>2,'first_name'=>'<Ali>','last_name'=>'Test','paid_at'=>'2030-01-01 10:00:00','amount'=>'50.000','refunded'=>'10.000','method'=>'D17','card_number'=>'PF1','plan'=>'Mensuel']; $remaining=40000; $token=str_repeat('a',64); $gymSettings=[];
ob_start(); require __DIR__.'/../app/views/payment_refund.php'; $html=ob_get_clean();
if (!str_contains($html,'&lt;Ali&gt;') || !str_contains($html,'max="40.000"') || !str_contains($html,'ne transfère pas d’argent')) throw new RuntimeException('Rendu de remboursement incorrect');
$refunds=[['id'=>1,'amount'=>'10.000','reason'=>'<motif>','refunded_at'=>'2030-01-02 10:00:00']];
ob_start(); require __DIR__.'/../app/views/receipt.php'; $html=ob_get_clean();
if (!str_contains($html,'&lt;motif&gt;') || str_contains($html,'<motif>') || !str_contains($html,'10,000 DT')) throw new RuntimeException('Reçu ou échappement incorrect');
echo "Remboursements : vue, reçu et échappement OK\n";
