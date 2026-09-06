<?php

$appRoot = dirname(__DIR__);
$_ENV['APP_BASE_PATH'] = $appRoot;
$_SERVER['APP_BASE_PATH'] = $appRoot;
putenv('APP_BASE_PATH='.$appRoot);

require $appRoot.'/vendor/autoload.php';

$appRoot = dirname(__DIR__);
foreach (spl_autoload_functions() ?: [] as $fn) {
    if (! is_array($fn) || ! ($fn[0] instanceof Composer\Autoload\ClassLoader)) {
        continue;
    }
    $loader = $fn[0];
    $loader->setPsr4('App\\', [$appRoot.'/app']);
    $loader->setPsr4('Tests\\', [$appRoot.'/tests']);
    $loader->setPsr4('Database\\Factories\\', [$appRoot.'/database/factories']);
    $loader->setPsr4('Database\\Seeders\\', [$appRoot.'/database/seeders']);
    $ref = new ReflectionClass($loader);
    if ($ref->hasProperty('classMap')) {
        $prop = $ref->getProperty('classMap');
        $prop->setAccessible(true);
        $map = $prop->getValue($loader);
        foreach (array_keys($map) as $class) {
            if (str_starts_with($class, 'App\\') || str_starts_with($class, 'Tests\\') || str_starts_with($class, 'Database\\')) {
                unset($map[$class]);
            }
        }
        $prop->setValue($loader, $map);
    }
}

spl_autoload_register(static function (string $class) use ($appRoot): void {
    $prefix = 'App\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = $appRoot.'/app/'.$relative.'.php';
    if (is_file($path)) {
        require_once $path;
    }
}, true, true);
