<?php
require_once __DIR__.'/../services/SubscriptionService.php';
class SubscriptionQuoteController {
    private static function state(string $mode,string $plan,string $promotion,string $start,?int $member,?int $subscription,?string $version,string $price): string {
        return hash('sha256',json_encode([$mode,$plan,$promotion,$start,$mode==='create'?$member:null,$subscription,$version,$price],JSON_THROW_ON_ERROR));
    }
    public function index(): void {
        if (!in_array($_SESSION['user']['role'],['Admin','Gérant','Réceptionniste'],true)) { http_response_code(403); exit('Accès interdit.'); }
        if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit('Méthode non autorisée.'); }
        verify_csrf(); $error=''; $quote=null; $mode=$_POST['mode'] ?? ''; $phase=$_POST['phase'] ?? '';
        $back='index.php?page=subscriptions';
        try {
            if (!is_string($mode) || !in_array($mode,['create','renew'],true) || !is_string($phase) || !in_array($phase,['quote','confirm'],true)) throw new DomainException('Formulaire invalide.');
            $planValue=$_POST['plan'] ?? ''; $promotionValue=$_POST['promotion'] ?? ''; $start=$_POST['start'] ?? '';
            if (!is_string($planValue) || !preg_match('/^(\d+)\|([a-f0-9]{64})$/D',$planValue,$pm) || (int)$pm[1]<1 || !is_string($promotionValue) || !is_string($start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$start)) throw new DomainException('Sélectionnez une formule et une date valides.');
            $d=DateTimeImmutable::createFromFormat('!Y-m-d',$start); if (!$d || $d->format('Y-m-d')!==$start || $start<'1900-01-01' || $start>'2099-12-31') throw new DomainException('Date invalide.');
            $promoId=null; $promoVersion=null;
            if ($promotionValue!=='') {
                if (!preg_match('/^(\d+)\|([a-f0-9]{64})$/D',$promotionValue,$dm) || (int)$dm[1]<1) throw new DomainException('Promotion invalide.');
                $promoId=(int)$dm[1]; $promoVersion=$dm[2];
            }
            $memberId=null; $subId=null; $subVersion=null;
            if ($mode==='create') {
                $memberId=filter_var($_POST['member_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if (!$memberId) throw new DomainException('Sélectionnez un membre.');
            } else {
                $subId=filter_var($_POST['subscription_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $subVersion=$_POST['subscription_version'] ?? '';
                if (!$subId || !is_string($subVersion) || !preg_match('/^[a-f0-9]{64}$/D',$subVersion)) throw new DomainException('Abonnement invalide.');
                $back='index.php?page=subscription_renew&id='.$subId;
            }
            $pdo=db(); $service=new SubscriptionService($pdo,(int)$_SESSION['user']['id'],$_SERVER['REMOTE_ADDR'] ?? '');
            if ($phase==='confirm') {
                $expected=$_POST['expected_price'] ?? '';
                if (!is_string($expected) || !preg_match('/^\d{1,7}\.\d{3}$/D',$expected)) throw new DomainException('Récapitulatif invalide. Recommencez.');
                $token=$_POST['quote_token'] ?? ''; $saved=$_SESSION['subscription_quote'] ?? null; unset($_SESSION['subscription_quote']);
                $state=self::state($mode,$planValue,$promotionValue,$start,$memberId,$subId,$subVersion,$expected);
                if (!is_string($token) || !is_array($saved) || !hash_equals($saved['token'],$token) || !hash_equals($saved['state'],$state)) throw new DomainException('Récapitulatif expiré ou modifié. Recommencez la vérification.');
                $created=$mode==='create' ? $service->create((int)$memberId,(int)$pm[1],$start,$pm[2],$promoId,$promoVersion,$expected) : $service->renew((int)$subId,$pm[2],(int)$pm[1],$subVersion,$start,$promoId,$promoVersion,$expected);
                $_SESSION['flash']='Abonnement enregistré du '.date('d/m/Y',strtotime($created['start'])).' au '.date('d/m/Y',strtotime($created['end'])).', prix fixé : '.dt($created['price']).'. Aucun paiement n’a été enregistré.'; redirect('subscriptions');
            }
            if ($mode==='renew') {
                $stmt=$pdo->prepare('SELECT * FROM subscriptions WHERE id=?'); $stmt->execute([$subId]); $sub=$stmt->fetch();
                if (!$sub || $sub['status']!=='active' || !hash_equals(SubscriptionService::version($sub),$subVersion)) throw new DomainException('L’abonnement a changé ou ne peut pas être renouvelé.');
                $expectedStart=max(date('Y-m-d'),(new DateTimeImmutable($sub['end_date']))->modify('+1 day')->format('Y-m-d'));
                if ($start!==$expectedStart) throw new DomainException('La date de début a changé. Vérifiez le renouvellement.');
                $memberId=(int)$sub['member_id'];
            }
            $stmt=$pdo->prepare('SELECT id,first_name,last_name,status FROM members WHERE id=?'); $stmt->execute([$memberId]); $member=$stmt->fetch();
            if (!$member || $member['status']!=='active') throw new DomainException('Membre introuvable ou inactif.');
            $quote=(new SubscriptionPricing($pdo))->quote((int)$pm[1],$pm[2],$promoId,$promoVersion,date('Y-m-d'));
            $end=$d->modify('+'.((int)$quote['plan']['duration_days']-1).' days')->format('Y-m-d'); if ($end>'2099-12-31') throw new DomainException('La date de fin dépasse la limite autorisée.');
            $quoteToken=bin2hex(random_bytes(32));
            $_SESSION['subscription_quote']=['token'=>$quoteToken,'state'=>self::state($mode,$planValue,$promotionValue,$start,$memberId,$subId,$subVersion,$quote['price'])];
        } catch (DomainException $ex) { $error=$ex->getMessage(); $quote=null; }
        require __DIR__.'/../views/subscription_quote.php';
    }
}
