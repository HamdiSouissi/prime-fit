<?php
class Dashboard {
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }
    private function scalar(string $sql) { return $this->db->query($sql)->fetchColumn(); }
    public function data(): array {
        $active = (int)$this->scalar("SELECT COUNT(DISTINCT s.member_id) FROM subscriptions s JOIN members m ON m.id=s.member_id WHERE m.status='active' AND s.status='active' AND s.start_date <= CURDATE() AND s.end_date >= CURDATE()");
        $today = (float)$this->scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(paid_at)=CURDATE()");
        $month = (float)$this->scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND paid_at < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, '%Y-%m-01')");
        $visits = (int)$this->scalar("SELECT COUNT(*) FROM attendance WHERE DATE(checked_in_at)=CURDATE() AND result='accepted'");
        $expiring = (int)$this->scalar("SELECT COUNT(*) FROM subscriptions s JOIN members m ON m.id=s.member_id WHERE m.status='active' AND s.status='active' AND s.end_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY");
        $recent = $this->db->query("SELECT m.first_name,m.last_name,m.phone,m.avatar_color,s.end_date,s.status,p.name AS plan FROM subscriptions s JOIN members m ON m.id=s.member_id JOIN plans p ON p.id=s.plan_id ORDER BY s.created_at DESC LIMIT 5")->fetchAll();
        $alerts = $this->db->query("SELECT m.first_name,m.last_name,m.phone,s.end_date,p.name AS plan FROM subscriptions s JOIN members m ON m.id=s.member_id JOIN plans p ON p.id=s.plan_id WHERE m.status='active' AND s.status='active' AND s.end_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY ORDER BY s.end_date LIMIT 4")->fetchAll();
        $monthly = $this->db->query("SELECT DATE_FORMAT(paid_at,'%Y-%m') AS period, SUM(amount) AS total FROM payments WHERE paid_at >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m-01') AND paid_at < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, '%Y-%m-01') GROUP BY period ORDER BY period")->fetchAll();
        $chart = []; foreach ($monthly as $row) { $chart[$row['period']] = (float)$row['total']; }
        $labels = []; $values = []; $months = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'];
        for ($i=5; $i>=0; $i--) { $date = new DateTimeImmutable('first day of -'.$i.' month'); $labels[] = $months[(int)$date->format('n')-1]; $values[] = $chart[$date->format('Y-m')] ?? 0; }
        $plans = $this->db->query("SELECT p.name, COUNT(DISTINCT m.id) AS total FROM plans p LEFT JOIN subscriptions s ON s.plan_id=p.id AND s.status='active' AND s.start_date<=CURDATE() AND s.end_date>=CURDATE() LEFT JOIN members m ON m.id=s.member_id AND m.status='active' GROUP BY p.id,p.name ORDER BY total DESC")->fetchAll();
        return compact('active','today','month','visits','expiring','recent','alerts','labels','values','plans');
    }
}
