<?php
declare(strict_types=1);
/** Diagnostic non sensible : le détail complet reste dans le journal PHP. */
final class PaymentFailureMessage {
    public static function describe(string $stage, PDOException $error): string {
        $allowed=['ouverture de la transaction','lecture de l’abonnement','lecture des paiements','lecture des remboursements','enregistrement du paiement','journal d’activité','validation de la transaction'];
        if (!in_array($stage,$allowed,true)) $stage='traitement';
        $info=$error->errorInfo ?? [];
        $state=(string)($info[0] ?? $error->getCode());
        $state=preg_match('/\A[A-Z0-9]{5}\z/D',$state) ? $state : 'inconnu';
        $driver=isset($info[1]) && is_numeric($info[1]) ? (string)(int)$info[1] : 'inconnu';
        $message='Le paiement n’a pas pu être confirmé (étape : '.$stage.' ; SQLSTATE '.$state.' ; code base '.$driver.'). ';
        if ($stage==='validation de la transaction') {
            $message.='La confirmation a échoué : vérifiez impérativement la caisse et le reçu avant toute nouvelle tentative.';
        } else {
            $message.='Vérifiez la caisse avant de réessayer et communiquez ces codes à votre administrateur.';
        }
        if ($stage==='enregistrement du paiement' && in_array($driver,['1265','1366'],true)) {
            $message.=' Vérifiez notamment la définition des colonnes de la table payments (SHOW CREATE TABLE payments).';
        }
        return $message;
    }
}
