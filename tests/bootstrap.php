<?php

$basePath = dirname(__DIR__);

$_ENV['APP_BASE_PATH'] = $basePath;
$_SERVER['APP_BASE_PATH'] = $basePath;

$loader = require $basePath.'/vendor/autoload.php';

// Shared/junctioned vendor classmaps may resolve App\ to another worktree.
// Rebind project namespaces to this checkout when that happens.
$probe = $loader->findFile(\App\Services\Payments\BookingPaymentService::class);
$probeReal = is_string($probe) ? (realpath($probe) ?: $probe) : null;
$baseReal = realpath($basePath) ?: $basePath;

if (is_string($probeReal) && ! str_starts_with($probeReal, $baseReal.DIRECTORY_SEPARATOR)) {
    $ref = new ReflectionClass($loader);

    $classMapProp = $ref->getProperty('classMap');
    $classMap = $classMapProp->getValue($loader);
    foreach (array_keys($classMap) as $class) {
        if (str_starts_with($class, 'App\\')
            || str_starts_with($class, 'Database\\Factories\\')
            || str_starts_with($class, 'Database\\Seeders\\')) {
            unset($classMap[$class]);
        }
    }
    $classMapProp->setValue($loader, $classMap);

    $authoritative = $ref->getProperty('classMapAuthoritative');
    $authoritative->setValue($loader, false);

    $loader->setPsr4('App\\', [$basePath.'/app']);
    $loader->setPsr4('Database\\Factories\\', [$basePath.'/database/factories']);
    $loader->setPsr4('Database\\Seeders\\', [$basePath.'/database/seeders']);
}
