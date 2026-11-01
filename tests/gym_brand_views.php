<?php
declare(strict_types=1);
// Les vues doivent échapper le nom choisi par l'administrateur.
function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function gym_name(): string { return 'Énergie <Sport> & Co'; }
function gym_brand_markup(): string { return e(gym_name()).'<small>CLUB DE SPORT</small>'; }
function csrf(): string { return 'test'; }
function dt($amount): string { return number_format((float)$amount,3,',',' ').' DT'; }
$_SESSION=['user'=>['id'=>1,'name'=>'Admin','role'=>'Admin']]; $error=''; $flash='';
ob_start(); require __DIR__.'/../app/views/login.php'; $login=ob_get_clean();
$payment=['id'=>1,'paid_at'=>'2026-01-01 12:00:00','first_name'=>'Ali','last_name'=>'Test','card_number'=>'PF-1','plan'=>'Mensuel','method'=>'espèces','amount'=>'10.000']; $gymSettings=[];
ob_start(); require __DIR__.'/../app/views/receipt.php'; $receipt=ob_get_clean();
$member=['id'=>1,'first_name'=>'Ali','last_name'=>'Test','card_number'=>'PF-1','qr_token'=>null];
ob_start(); require __DIR__.'/../app/views/member_card.php'; $card=ob_get_clean();
foreach ([$login,$receipt,$card] as $html) {
    if (!str_contains($html,'Énergie &lt;Sport&gt; &amp; Co') || str_contains($html,'Énergie <Sport>')) throw new RuntimeException('Nom absent ou non échappé dans une vue');
}
if (!str_contains($card,'data-app-name="Énergie &lt;Sport&gt; &amp; Co"')) throw new RuntimeException('Nom absent des notifications');
echo "Identité : affichage sécurisé dans la connexion, le reçu, la carte et le logo OK\n";
