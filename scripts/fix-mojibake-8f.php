<?php

declare(strict_types=1);

$roots = [
    __DIR__.'/../resources/views/frontend/booking',
    __DIR__.'/../resources/views/frontend/flights/partials',
    __DIR__.'/../resources/views/themes/frontend/jetpakistan',
    __DIR__.'/../public/themes/frontend/jetpakistan',
];

$map = [
    "â†’" => '→',
    'Â·' => '·',
    'â€”' => '—',
    'â€“' => '–',
    'â€¦' => '…',
    'Ã—' => '×',
    'âˆ’' => '−',
    'Â→' => '→',
];

$extensions = ['php', 'blade.php', 'css', 'js'];

foreach ($roots as $root) {
    if (! is_dir($root)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $ext = $file->getExtension();
        if ($file->getExtension() === 'php' && str_ends_with($file->getFilename(), '.blade.php')) {
            $ext = 'blade.php';
        }

        if (! in_array($ext, $extensions, true) && ! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $path = $file->getPathname();
        $content = (string) file_get_contents($path);
        $updated = str_replace(array_keys($map), array_values($map), $content);

        if ($updated !== $content) {
            file_put_contents($path, $updated);
            echo 'fixed: '.str_replace(__DIR__.'/../', '', $path).PHP_EOL;
        }
    }
}
