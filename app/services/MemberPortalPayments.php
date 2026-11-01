<?php
declare(strict_types=1);
require_once __DIR__.'/PaymentReceiptPdf.php';
/** L'identité du membre doit provenir d'une session membre préalablement revalidée. */
final class MemberPortalPayments {
    public function __construct(private PDO $db) {}
    public function listing(int $memberId,int $requestedPage=1): array {
        if ($memberId<1) throw new DomainException('Membre introuvable.');
        $stmt=$this->db->prepare('SELECT COUNT(*) FROM payments WHERE member_id=?'); $stmt->execute([$memberId]); $total=(int)$stmt->fetchColumn();
        $pages=max(1,(int)ceil($total/10)); $page=min(max(1,$requestedPage),$pages); $offset=($page-1)*10;
        $stmt=$this->db->prepare('SELECT pay.id,pay.amount,pay.method,pay.paid_at,p.name AS plan FROM payments pay LEFT JOIN subscriptions s ON s.id=pay.subscription_id LEFT JOIN plans p ON p.id=s.plan_id WHERE pay.member_id=? ORDER BY pay.paid_at DESC,pay.id DESC LIMIT 10 OFFSET '.$offset);
        $stmt->execute([$memberId]); return ['rows'=>$stmt->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
    /** Retourne null pour un paiement absent ou appartenant à un autre membre. */
    public function pdf(int $memberId,int $paymentId,string $gym): ?string {
        if ($memberId<1 || $paymentId<1) return null;
        $stmt=$this->db->prepare('SELECT pay.id,pay.amount,pay.method,pay.paid_at,m.first_name,m.last_name,m.card_number,p.name AS plan,r.pdf FROM payments pay JOIN members m ON m.id=pay.member_id LEFT JOIN subscriptions s ON s.id=pay.subscription_id LEFT JOIN plans p ON p.id=s.plan_id LEFT JOIN payment_receipts r ON r.payment_id=pay.id WHERE pay.id=? AND pay.member_id=?');
        $stmt->execute([$paymentId,$memberId]); $row=$stmt->fetch(); if (!$row) return null;
        $pdf=$row['pdf']; if (is_resource($pdf)) $pdf=stream_get_contents($pdf);
        // Les reçus antérieurs à l'archivage sont signalés comme reconstitués.
        if ($pdf===null) $pdf=PaymentReceiptPdf::render($row,$gym,true);
        if (!is_string($pdf) || !str_starts_with($pdf,'%PDF-')) throw new RuntimeException('Reçu PDF indisponible.');
        return $pdf;
    }
}
