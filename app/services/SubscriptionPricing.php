<?php
declare(strict_types=1);
require_once __DIR__.'/PlanService.php';
require_once __DIR__.'/SubscriptionPriceService.php';
/** Calcul en millièmes et centièmes de pourcentage, sans flottants. */
class SubscriptionPricing {
    public function __construct(private PDO $db) {}
    public static function promotionVersion(array $p): string {
        return hash('sha256',json_encode([(int)$p['id'],$p['code'],$p['label'],(string)$p['discount_percent'],$p['starts_on'],$p['ends_on'],(int)$p['is_active']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }
    public static function amount(int $millimes): string { return intdiv($millimes,1000).'.'.str_pad((string)($millimes%1000),3,'0',STR_PAD_LEFT); }
    public static function discount(string $base,string $percent): array {
        if (PHP_INT_SIZE<8) throw new RuntimeException('Le calcul des montants requiert PHP 64 bits.');
        if (!preg_match('/^\d{1,7}(?:\.\d{1,3})?$/D',$base) || !preg_match('/^\d{1,3}(?:\.\d{1,2})?$/D',$percent)) throw new DomainException('Prix ou réduction invalide.');
        $parts=explode('.',$percent,2); $points=(int)$parts[0]*100+(int)str_pad($parts[1] ?? '',2,'0');
        if ($points<1 || $points>10000) throw new DomainException('Réduction invalide.');
        $baseMillimes=SubscriptionPriceService::millimes($base); $discount=intdiv($baseMillimes*$points+5000,10000);
        return ['base'=>self::amount($baseMillimes),'discount'=>self::amount($discount),'price'=>self::amount($baseMillimes-$discount)];
    }
    public function quote(int $planId,?string $planVersion,?int $promotionId,?string $promotionVersion,string $today,bool $lock=false): array {
        if ($lock && !$this->db->inTransaction()) throw new LogicException('Une transaction est nécessaire.');
        $suffix=$lock ? ' FOR UPDATE' : '';
        $stmt=$this->db->prepare('SELECT id,name,duration_days,price,is_active FROM plans WHERE id=?'.$suffix); $stmt->execute([$planId]); $plan=$stmt->fetch();
        if (!$plan || !$plan['is_active'] || (int)$plan['duration_days']<1 || (int)$plan['duration_days']>3650) throw new DomainException('Formule inactive ou invalide.');
        if ($planVersion!==null && !hash_equals(PlanService::version($plan),$planVersion)) throw new DomainException('La formule a changé. Vérifiez son tarif et sa durée.');
        $price=(string)$plan['price'];
        if (!preg_match('/^\d{1,7}(?:\.\d{1,3})?$/D',$price)) throw new DomainException('Prix de formule invalide.');
        $base=self::amount(SubscriptionPriceService::millimes($price)); $result=['base'=>$base,'discount'=>'0.000','price'=>$base,'plan'=>$plan,'promotion'=>null];
        if ($promotionId!==null) {
            $stmt=$this->db->prepare('SELECT id,code,label,discount_percent,starts_on,ends_on,is_active FROM promotions WHERE id=?'.$suffix); $stmt->execute([$promotionId]); $promo=$stmt->fetch();
            if (!$promo || !$promo['is_active'] || $promo['starts_on']>$today || $promo['ends_on']<$today) throw new DomainException('Cette promotion est inactive, expirée ou pas encore disponible.');
            if ($promotionVersion===null || !hash_equals(self::promotionVersion($promo),$promotionVersion)) throw new DomainException('La promotion a changé. Vérifiez-la avant de continuer.');
            $result=array_merge($result,self::discount($price,(string)$promo['discount_percent']),['promotion'=>$promo]);
        }
        return $result;
    }
    public function available(string $today): array {
        $stmt=$this->db->prepare('SELECT id,code,label,discount_percent,starts_on,ends_on,is_active FROM promotions WHERE is_active=1 AND starts_on<=? AND ends_on>=? ORDER BY code,id');
        $stmt->execute([$today,$today]); return $stmt->fetchAll();
    }
}
