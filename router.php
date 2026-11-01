<?php
// Routeur pour « php -S ... -t public router.php » (Apache utilise public/.htaccess).
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$assets=realpath(__DIR__.'/public/assets');
$file=is_string($path) ? realpath(__DIR__.'/public'.$path) : false;
if ($file && $assets && str_starts_with($file,$assets.DIRECTORY_SEPARATOR) && is_file($file) && !preg_match('/\.php(?:\d*)$/i',$file)) return false;
$_SERVER['SCRIPT_NAME']='/index.php';
require __DIR__.'/public/index.php';
