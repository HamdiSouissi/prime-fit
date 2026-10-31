<?php
/** Dépenses et synthèse de trésorerie : ce n'est pas une comptabilité fiscale. */
class FinanceController {
    private const CATEGORIES = ['loyer','salaires','électricité','eau','maintenance','fournitures','marketing','autre'];
    private const METHODS = ['espèces','chèque','virement','D17','Flouci','carte bancaire'];
    private function fail(string $message): void { throw new DomainException($message); }
    private function dateValid(string $value): bool {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        return $date && $date->format('Y-m-d')===$value;
    }
    private function period(): array {
        $month=$_GET['month'] ?? date('Y-m');
        $date=DateTimeImmutable::createFromFormat('!Y-m',$month);
        if (!$date || $date->format('Y-m')!==$month || ((int)$date->format('Y')<1900 || (int)$date->format('Y')>2099)) { $date=new DateTimeImmutable('first day of this month'); }
        return [$date->format('Y-m'),$date->format('Y-m-d'),$date->modify('+1 month')->format('Y-m-d')];
    }
    private function requireRole(): void {
        if (!in_array($_SESSION['user']['role'],['Admin','Gérant','Comptable'],true)) { http_response_code(403); exit('Accès interdit.'); }
    }
    public function index(): void {
        $this->requireRole(); $pdo=db(); $error='';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf();
            try {
                $date=trim($_POST['expense_date'] ?? ''); $category=$_POST['category'] ?? ''; $description=trim($_POST['description'] ?? '');
                $amount=str_replace(',','.',trim($_POST['amount'] ?? '')); $method=$_POST['method'] ?? '';
                if (!$this->dateValid($date) || $date>date('Y-m-d') || !in_array($category,self::CATEGORIES,true) || $description==='' || mb_strlen($description)>500 || !preg_match('/^\d{1,7}(\.\d{1,3})?$/',$amount) || (float)$amount<=0 || !in_array($method,self::METHODS,true)) $this->fail('Vérifiez la date, la catégorie, la description, le montant positif et le moyen de paiement.');
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO expenses (expense_date,category,description,amount,method,created_by) VALUES (?,?,?,?,?,?)')->execute([$date,$category,$description,$amount,$method,$_SESSION['user']['id']]);
                $id=(int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$_SESSION['user']['id'],'Dépense enregistrée #'.$id,substr($_SERVER['REMOTE_ADDR'] ?? '',0,45)]);
                $pdo->commit(); $_SESSION['flash']='Dépense enregistrée.'; redirect('finance&month='.substr($date,0,7));
            } catch (DomainException $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); $error=$ex->getMessage(); }
              catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $ex; }
        }
        [$month,$start,$end]=$this->period();
        $sum=function(string $sql) use ($pdo,$start,$end) { $stmt=$pdo->prepare($sql); $stmt->execute([$start,$end]); return $stmt->fetchColumn(); };
        $subscriptionTotal=$sum('SELECT COALESCE(SUM(amount),0) FROM payments WHERE paid_at>=? AND paid_at<?');
        $shopTotal=$sum('SELECT COALESCE(SUM(total),0) FROM shop_sales WHERE sold_at>=? AND sold_at<?');
        $expenseTotal=$sum('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date>=? AND expense_date<?');
        $stmt=$pdo->prepare('SELECT category,SUM(amount) AS total FROM expenses WHERE expense_date>=? AND expense_date<? GROUP BY category ORDER BY total DESC,category'); $stmt->execute([$start,$end]); $categories=$stmt->fetchAll();
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM expenses WHERE expense_date>=? AND expense_date<?'); $stmt->execute([$start,$end]); $total=(int)$stmt->fetchColumn();
        $pageNumber=max(1,(int)($_GET['p'] ?? 1)); $pageNumber=min($pageNumber,max(1,(int)ceil($total/10))); $offset=($pageNumber-1)*10;
        $stmt=$pdo->prepare('SELECT e.*,u.name AS author FROM expenses e LEFT JOIN users u ON u.id=e.created_by WHERE e.expense_date>=? AND e.expense_date<? ORDER BY e.expense_date DESC,e.id DESC LIMIT 10 OFFSET '.$offset); $stmt->execute([$start,$end]); $expenses=$stmt->fetchAll();
        $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/finance.php';
    }
    public function export(): void {
        $this->requireRole(); [$month,$start,$end]=$this->period();
        $stmt=db()->prepare('SELECT e.id,e.expense_date,e.category,e.description,e.amount,e.method,u.name AS author FROM expenses e LEFT JOIN users u ON u.id=e.created_by WHERE e.expense_date>=? AND e.expense_date<? ORDER BY e.expense_date DESC,e.id DESC'); $stmt->execute([$start,$end]);
        header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="depenses-'.$month.'.csv"'); header('Cache-Control: no-store');
        $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,['ID','Date','Catégorie','Description','Montant DT','Moyen','Saisi par'],';', '"', '');
        // Éviter l'interprétation de données non fiables comme formules de tableur.
        $safe=static function($value): string { $value=(string)$value; return preg_match('/^[\s\x00-\x1F]*[=+@\-\t\r]/u',$value) ? "'".$value : $value; };
        while ($row=$stmt->fetch()) fputcsv($out,[(string)$row['id'],date('d/m/Y',strtotime($row['expense_date'])),$safe($row['category']),$safe($row['description']),str_replace('.',',',$row['amount']),$safe($row['method']),$safe($row['author'] ?? '')],';','"','');
        fclose($out);
    }
}
