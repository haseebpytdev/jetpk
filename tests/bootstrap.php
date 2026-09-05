<?php

require dirname(__DIR__).'/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = dirname(__DIR__).'/app/'.$relative.'.php';
    if (is_file($path)) {
        require_once $path;
    }
}, true, true);
