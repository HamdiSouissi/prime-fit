<?php
require_once __DIR__.'/../services/MemberHistory.php';
class MemberHistoryController {
    public function index(): void {
        $role=$_SESSION['user']['role']; $type=$_GET['type'] ?? '';
        if (!is_string($type) || !in_array($type,['subscriptions','payments','attendance'],true)) { http_response_code(404); exit('Historique introuvable.'); }
        if (!MemberHistory::allowed($role,$type)) { http_response_code(403); exit('Accès interdit.'); }
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if (!$id) { http_response_code(404); exit('Membre introuvable.'); }
        $page=filter_var($_GET['p'] ?? 1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: 1;
        $history=(new MemberHistory(db()))->get((int)$id,$role,$type,(int)$page);
        if (!$history) { http_response_code(404); exit('Membre introuvable.'); }
        header('Cache-Control: no-store'); require __DIR__.'/../views/member_history.php';
    }
}
