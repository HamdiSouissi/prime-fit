<?php
require_once __DIR__.'/../models/ActivityJournal.php';
class ActivityController {
    private function requireAdmin(): void { try { ActivityJournal::authorize($_SESSION['user']['role'] ?? ''); } catch (DomainException $ex) { http_response_code(403); exit('Accès interdit.'); } }
    public function index(): void {
        $this->requireAdmin(); header('Cache-Control: no-store, private'); $model=new ActivityJournal(db()); $error='';
        try { $filters=ActivityJournal::filters($_GET); $result=$model->page($filters,(int)($_GET['p'] ?? 1)); }
        catch (DomainException $ex) { $error=$ex->getMessage(); $filters=ActivityJournal::filters([]); $result=['rows'=>[],'total'=>0,'page'=>1]; }
        $users=$model->users(); require __DIR__.'/../views/activity.php';
    }
    public function export(): void {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit('Méthode non autorisée.'); }
        verify_csrf();
        try { $filters=ActivityJournal::filters($_POST); } catch (DomainException $ex) { http_response_code(400); exit(e($ex->getMessage())); }
        $pdo=db();
        // Enregistrer la demande, et non affirmer que le téléchargement s'est achevé.
        $pdo->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$_SESSION['user']['id'],'Export du journal demandé',substr($_SERVER['REMOTE_ADDR'] ?? '',0,45)]);
        header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="journal-activite.csv"'); header('Cache-Control: no-store, private');
        $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,['ID','Date','Utilisateur','ID utilisateur','Action','Adresse IP'],';','"','');
        (new ActivityJournal($pdo))->export($filters,static function(array $row) use ($out): void {
            fputcsv($out,[(string)$row['id'],date('d/m/Y H:i:s',strtotime($row['created_at'])),ActivityJournal::csvSafe($row['name'] ?? 'Compte supprimé / système'),$row['user_id']===null ? '' : (string)$row['user_id'],ActivityJournal::csvSafe($row['action']),ActivityJournal::csvSafe($row['ip_address'] ?? '')],';','"','');
        });
        fclose($out);
    }
}
