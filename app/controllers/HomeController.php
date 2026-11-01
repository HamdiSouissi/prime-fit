<?php
declare(strict_types=1);
/** Page publique : seulement les coordonnées publiables configurées pour la salle. */
class HomeController {
    public function index(): void {
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET',['GET','HEAD'],true)) { http_response_code(405); header('Allow: GET, HEAD'); exit('Méthode non autorisée.'); }
        $contact=['phone'=>'','email'=>'','address'=>'','opening_hours'=>''];
        try {
            $row=db()->query('SELECT phone,email,address,opening_hours FROM gym_settings WHERE id=1')->fetch();
            if ($row) foreach ($contact as $key=>$_) $contact[$key]=is_string($row[$key] ?? null) ? trim($row[$key]) : '';
        } catch (PDOException $ex) { error_log('Coordonnées du site indisponibles : '.$ex->getMessage()); }
        require __DIR__.'/../views/home.php';
    }
}
