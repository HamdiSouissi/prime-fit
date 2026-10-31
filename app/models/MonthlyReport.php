<?php
declare(strict_types=1);
/** Synthèse mensuelle, calculée sans agrégation croisée entre sources financières. */
class MonthlyReport {
    private PDO $db;
    public function __construct(PDO $db) { $this->db=$db; }
    private function scalar(string $sql, array $params): int {
        $stmt=$this->db->prepare($sql); $stmt->execute($params); return (int)$stmt->fetchColumn();
    }
    public function build(DateTimeImmutable $month): array {
        $start=$month->format('Y-m-01'); $next=$month->modify('first day of next month')->format('Y-m-d');
        $previous=$month->modify('first day of previous month')->format('Y-m-d');
        $daily=[];
        for ($day=$month->modify('first day of this month'); $day->format('Y-m-d')<$next; $day=$day->modify('+1 day')) {
            $daily[$day->format('Y-m-d')]=['subscription'=>0,'shop'=>0,'expense'=>0,'visits'=>0,'visitors'=>0];
        }
        // Les montants MySQL DECIMAL sont convertis en millièmes avant addition.
        foreach ([['payments','paid_at','amount','subscription'],['shop_sales','sold_at','total','shop'],['expenses','expense_date','amount','expense']] as [$table,$column,$amount,$key]) {
            $stmt=$this->db->prepare("SELECT DATE($column) AS day, SUM($amount) AS total FROM $table WHERE $column>=? AND $column<? GROUP BY DATE($column)");
            $stmt->execute([$start,$next]);
            foreach ($stmt->fetchAll() as $row) if (isset($daily[$row['day']])) $daily[$row['day']][$key]=(int)round((float)$row['total']*1000);
        }
        $stmt=$this->db->prepare("SELECT DATE(checked_in_at) AS day,COUNT(*) AS visits,COUNT(DISTINCT member_id) AS visitors FROM attendance WHERE result='accepted' AND checked_in_at>=? AND checked_in_at<? GROUP BY DATE(checked_in_at)");
        $stmt->execute([$start,$next]);
        foreach ($stmt->fetchAll() as $row) if (isset($daily[$row['day']])) { $daily[$row['day']]['visits']=(int)$row['visits']; $daily[$row['day']]['visitors']=(int)$row['visitors']; }
        $totals=['subscription'=>0,'shop'=>0,'expense'=>0,'visits'=>0];
        foreach ($daily as $values) foreach ($totals as $key=>$_) $totals[$key]+=$values[$key];
        $totals['balance']=$totals['subscription']+$totals['shop']-$totals['expense'];
        $uniqueVisitors=$this->scalar("SELECT COUNT(DISTINCT member_id) FROM attendance WHERE result='accepted' AND checked_in_at>=? AND checked_in_at<?",[$start,$next]);
        // Statuts actuels : les gels et annulations historiques ne sont pas reconstituables.
        $prior=$this->scalar("SELECT COUNT(*) FROM members m WHERE m.status='active' AND EXISTS (SELECT 1 FROM subscriptions s WHERE s.member_id=m.id AND s.status='active' AND s.start_date<? AND s.end_date>=?)",[$start,$previous]);
        $retained=$this->scalar("SELECT COUNT(*) FROM members m WHERE m.status='active' AND EXISTS (SELECT 1 FROM subscriptions s WHERE s.member_id=m.id AND s.status='active' AND s.start_date<? AND s.end_date>=?) AND EXISTS (SELECT 1 FROM subscriptions s WHERE s.member_id=m.id AND s.status='active' AND s.start_date<? AND s.end_date>=?)",[$start,$previous,$next,$start]);
        $eligible=$this->scalar("SELECT COUNT(*) FROM members m WHERE m.status='active' AND EXISTS (SELECT 1 FROM subscriptions s WHERE s.member_id=m.id AND s.status='active' AND s.start_date<? AND s.end_date>=?)",[$next,$start]);
        $attending=$this->scalar("SELECT COUNT(*) FROM members m WHERE m.status='active' AND EXISTS (SELECT 1 FROM subscriptions s WHERE s.member_id=m.id AND s.status='active' AND s.start_date<? AND s.end_date>=?) AND EXISTS (SELECT 1 FROM attendance a WHERE a.member_id=m.id AND a.result='accepted' AND a.checked_in_at>=? AND a.checked_in_at<?)",[$next,$start,$start,$next]);
        return compact('start','next','daily','totals','uniqueVisitors','prior','retained','eligible','attending');
    }
}
