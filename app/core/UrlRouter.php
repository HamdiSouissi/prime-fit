<?php
declare(strict_types=1);
/** Routes à un seul segment, avec prise en charge d'une installation en sous-dossier. */
final class UrlRouter {
    public static function base(string $scriptName): string {
        $base=str_replace('\\','/',dirname($scriptName));
        return $base==='/' || $base==='.' ? '' : rtrim($base,'/');
    }
    public static function resolve(string $requestUri,string $scriptName,array $query,bool $authenticated): array {
        $base=self::base($scriptName); $path=parse_url($requestUri,PHP_URL_PATH);
        if (!is_string($path) || ($base!=='' && $path!==$base && !str_starts_with($path,$base.'/'))) return ['page'=>null,'legacy'=>false];
        $relative=substr($path,strlen($base));
        if ($relative==='' || $relative==='/' || $relative==='/index.php') {
            $page=$query['page'] ?? ($authenticated ? 'dashboard' : 'login');
            if (!is_string($page) || !preg_match('/^[a-z][a-z0-9_]*$/D',$page)) return ['page'=>null,'legacy'=>false];
            return ['page'=>$page,'legacy'=>array_key_exists('page',$query)];
        }
        if (!preg_match('~^/([a-z][a-z0-9_]*)$~D',$relative,$matches)) return ['page'=>null,'legacy'=>false];
        return ['page'=>$matches[1],'legacy'=>false];
    }
    public static function canonical(string $scriptName,string $page,array $query): string {
        if (!preg_match('/^[a-z][a-z0-9_]*$/D',$page)) throw new InvalidArgumentException('Route invalide.');
        unset($query['page']); $suffix=http_build_query($query,'','&',PHP_QUERY_RFC3986);
        return self::base($scriptName).'/'.$page.($suffix!=='' ? '?'.$suffix : '');
    }
    /** Routes internes historiques : nom ou nom&paramètre=valeur. */
    public static function redirectTarget(string $route): string {
        if (!preg_match('/^([a-z][a-z0-9_]*)(?:&([^\r\n]*))?$/D',$route,$matches)) throw new InvalidArgumentException('Route invalide.');
        return $matches[1].(isset($matches[2]) && $matches[2]!=='' ? '?'.$matches[2] : '');
    }
}
