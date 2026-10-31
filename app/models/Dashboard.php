<?php
declare(strict_types=1);
/** Données limitées aux rubriques autorisées pour le rôle courant. */
class Dashboard {
    public function __construct(private PDO $db) {}
    private function scalar(string $sql,array $params=[]) { $stmt=$this->db->prepare($sql); $stmt->execute($params); return $stmt->fetchColumn(); }
    private function rows(string $sql,array $params=[]): array { $stmt=$this->db->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(); }
    public function data(string $role,?DateTimeImmutable $now=null): array {
        if (!in_array($role,['Admin','Gérant','Réceptionniste','Coach','Comptable'],true)) throw new DomainException('Rôle non autorisé.');
        $canFinance=in_array($role,['Admin','Gérant','Réceptionniste','Comptable'],true);
        $canAttendance=in_array($role,['Admin','Gérant','Réceptionniste','Coach'],true);
        $now=($now ?? new DateTimeImmutable('today'))->setTime(0,0); $today=$now->format('Y-m-d'); $tomorrow=$now->modify('+1 day')->format('Y-m-d'); $week=$now->modify('+7 days')->format('Y-m-d');
        $active=(int)$this->scalar("SELECT COUNT(DISTINCT s.member_id) FROM subscriptions s JOIN members m ON m.id=s.member_id WHERE m.status='active' AND s.status='active' AND s.start_date<=? AND s.end_date>=?",[$today,$today]);
        $expiring=(int)$this->scalar("SELECT COUNT(*) FROM subscriptions s JOIN members m ON m.id=s.member_id WHERE m.status='active' AND s.status='active' AND s.start_date<=? AND s.end_date BETWEEN ? AND ?",[$today,$today,$week]);
        $recent=$this->rows('SELECT m.first_name,m.last_name,m.phone,m.avatar_color,s.start_date,s.end_date,s.status,p.name AS plan FROM subscriptions s JOIN members m ON m.id=s.member_id JOIN plans p ON p.id=s.plan_id ORDER BY s.created_at DESC,s.id DESC LIMIT 5');
        $alerts=$this->rows("SELECT m.first_name,m.last_name,s.end_date,p.name AS plan FROM subscriptions s JOIN members m ON m.id=s.member_id JOIN plans p ON p.id=s.plan_id WHERE m.status='active' AND s.status='active' AND s.start_date<=? AND s.end_date BETWEEN ? AND ? ORDER BY s.end_date,s.id LIMIT 4",[$today,$today,$week]);
        $plans=$this->rows("SELECT p.name, COUNT(DISTINCT m.id) AS total FROM plans p LEFT JOIN subscriptions s ON s.plan_id=p.id AND s.status='active' AND s.start_date<=? AND s.end_date>=? LEFT JOIN members m ON m.id=s.member_id AND m.status='active' GROUP BY p.id,p.name ORDER BY total DESC,p.name",[$today,$today]);
        $data=compact('active','expiring','recent','alerts','plans','canFinance','canAttendance');
        if ($canAttendance) $data['visits']=(int)$this->scalar("SELECT COUNT(*) FROM attendance WHERE checked_in_at>=? AND checked_in_at<? AND result='accepted'",[$today,$tomorrow]);
        if ($canFinance) {
            $first=$now->modify('first day of this month'); $start=$first->modify('-5 months'); $end=$first->modify('+1 month');
            $data['today']=(float)$this->scalar('SELECT COALESCE(SUM(amount),0) FROM payments WHERE paid_at>=? AND paid_at<?',[$today,$tomorrow]);
            $data['month']=(float)$this->scalar('SELECT COALESCE(SUM(amount),0) FROM payments WHERE paid_at>=? AND paid_at<?',[$first->format('Y-m-d'),$end->format('Y-m-d')]);
            $monthly=$this->rows("SELECT DATE_FORMAT(paid_at,'%Y-%m') AS period,SUM(amount) AS total FROM payments WHERE paid_at>=? AND paid_at<? GROUP BY period ORDER BY period",[$start->format('Y-m-d'),$end->format('Y-m-d')]);
            $chart=[]; foreach ($monthly as $row) $chart[$row['period']]=(float)$row['total'];
            $labels=[]; $values=[]; $months=['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'];
            for ($i=0;$i<6;$i++) { $date=$start->modify('+'.$i.' months'); $labels[]=$months[(int)$date->format('n')-1]; $values[]=$chart[$date->format('Y-m')] ?? 0; }
            $data['labels']=$labels; $data['values']=$values;
        }
        return $data;
    }
}
