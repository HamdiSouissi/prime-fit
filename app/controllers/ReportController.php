<?php
declare(strict_types=1);
require_once __DIR__.'/../models/MonthlyReport.php';
class ReportController {
    private function requireRole(): void {
        if (!in_array($_SESSION['user']['role'],['Admin','Gérant','Comptable'],true)) { http_response_code(403); exit('Accès interdit.'); }
    }
    private function period(): DateTimeImmutable {
        $value=$_GET['month'] ?? date('Y-m');
        if (!is_string($value)) $value='';
        $date=DateTimeImmutable::createFromFormat('!Y-m',$value);
        if (!$date || $date->format('Y-m')!==$value || (int)$date->format('Y')<1900 || (int)$date->format('Y')>2099) return new DateTimeImmutable('first day of this month');
        return $date;
    }
    public function index(): void {
        $this->requireRole(); $period=$this->period(); $month=$period->format('Y-m');
        $report=(new MonthlyReport(db()))->build($period);
        $flash=''; $error=''; require __DIR__.'/../views/reports.php';
    }
    public function export(): void {
        $this->requireRole(); $period=$this->period(); $report=(new MonthlyReport(db()))->build($period);
        header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="rapport-'.$period->format('Y-m').'.csv"'); header('Cache-Control: no-store');
        $output=fopen('php://output','w'); fwrite($output,"\xEF\xBB\xBF");
        fputcsv($output,['Date','Abonnements nets (DT)','Boutique (DT)','Dépenses (DT)','Solde (DT)','Passages acceptés','Membres distincts du jour'],';','"','');
        foreach ($report['daily'] as $date=>$row) {
            $format=static fn(int $m): string => number_format($m/1000,3,',','');
            fputcsv($output,[date('d/m/Y',strtotime($date)),$format($row['subscription']),$format($row['shop']),$format($row['expense']),$format($row['subscription']+$row['shop']-$row['expense']),$row['visits'],$row['visitors']],';','"','');
        }
        fclose($output);
    }
}
