<?php

$jetpkRoot = dirname(__DIR__, 3);
$_SERVER['APP_BASE_PATH'] = $jetpkRoot;
$_ENV['APP_BASE_PATH'] = $jetpkRoot;
putenv('APP_BASE_PATH='.$jetpkRoot);

$otaVendor = 'C:/Users/khadi/ota/vendor/autoload.php';
if (! is_file($otaVendor)) {
    fwrite(STDERR, "OTA_VENDOR_AUTOLOAD_MISSING\n");
    exit(1);
}
require $otaVendor;

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = dirname(__DIR__, 3).'/app/'.$relative.'.php';
    if (is_file($path)) {
        require_once $path;
    }
}, true, true);
