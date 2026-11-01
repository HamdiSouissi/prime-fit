<?php
declare(strict_types=1);
/** Résolution du nom : la configuration sert de repli tant que la migration est absente. */
class GymBrand {
    public static function clean($name): ?string {
        if (!is_string($name) || !mb_check_encoding($name,'UTF-8')) return null;
        $name=trim($name);
        if ($name==='' || mb_strlen($name)>120 || preg_match('/[\x00-\x1F\x7F]/u',$name)) return null;
        return $name;
    }
    public static function resolve(PDO $db,$fallback): string {
        $fallback=self::clean($fallback) ?? 'Horizon Sportif de Bir El Hafey';
        try { $row=$db->query('SELECT gym_name FROM gym_settings WHERE id=1')->fetch(); return self::clean($row['gym_name'] ?? null) ?? $fallback; }
        catch (PDOException $ex) { return $fallback; }
    }
}
