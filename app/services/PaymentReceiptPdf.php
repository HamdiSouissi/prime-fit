<?php
declare(strict_types=1);
require_once __DIR__.'/../vendor/tcpdf/tcpdf.php';
/** Document d'encaissement non fiscal ; données reçues et figées à la création. */
final class PaymentReceiptPdf {
    public static function render(array $payment, string $gym, bool $legacy=false): string {
        $pdf=new TCPDF('P','mm','A4',true,'UTF-8',false);
        $pdf->SetCreator('Horizon Sportif'); $pdf->SetTitle('Reçu de paiement n° '.(int)$payment['id']);
        $pdf->setPrintHeader(false); $pdf->setPrintFooter(false); $pdf->SetMargins(19,20,19); $pdf->SetAutoPageBreak(true,20); $pdf->AddPage();
        $esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $date=date('d/m/Y à H:i',strtotime((string)$payment['paid_at']));
        $amount=number_format((float)$payment['amount'],3,',',' ').' DT';
        $name=trim((string)$payment['first_name'].' '.(string)$payment['last_name']);
        $pdf->SetFont('dejavusans','',10);
        $html='<table cellpadding="12" style="background-color:#111111;color:#ffffff;"><tr><td><span style="font-size:18pt;font-weight:bold;">'.$esc($gym).'</span><br><span style="color:#e8ff00;font-size:9pt;">CLUB DE SPORT · TUNISIE</span></td></tr></table>';
        $html.='<br><br><span style="color:#555555;font-size:9pt;">JUSTIFICATIF D’ENCAISSEMENT</span><br><span style="font-size:22pt;font-weight:bold;">Reçu n° '.(int)$payment['id'].'</span><br><br>';
        $rows=['Date'=>$date,'Membre'=>$name,'N° de carte'=>$payment['card_number'] ?: 'Non renseigné','Abonnement'=>$payment['plan'] ?: 'Non renseigné','Moyen de paiement'=>$payment['method'],'Montant reçu'=>$amount];
        $html.='<table cellpadding="8" border="0">'; foreach ($rows as $label=>$value) $html.='<tr><td width="42%" style="color:#555555;border-bottom:1px solid #dddddd;">'.$esc($label).'</td><td width="58%" style="font-weight:bold;border-bottom:1px solid #dddddd;">'.$esc($value).'</td></tr>'; $html.='</table><br><br>';
        $html.='<p style="font-size:9pt;color:#555555;">Ce document est un justificatif d’encaissement, pas une facture fiscale. TVA et timbre fiscal ne sont pas calculés. Le montant correspond au paiement initial ; les éventuels remboursements sont enregistrés séparément.</p>';
        if ($legacy) $html.='<p style="font-size:9pt;color:#555555;">Reçu reconstitué : le nom du membre, de la formule et de la salle sont les informations disponibles actuellement.</p>';
        $pdf->writeHTML($html,true,false,true,false,'');
        return $pdf->Output('recu.pdf','S');
    }
}
