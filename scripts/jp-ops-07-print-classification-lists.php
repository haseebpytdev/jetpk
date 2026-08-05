<?php

declare(strict_types=1);

$path = __DIR__.'/../storage/app/jp-ops-07-mutation-classification.json';
/** @var array<string, mixed> $j */
$j = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

$classes = [
    'CONNECTED',
    'INTENTIONAL_BLADE_FALLBACK',
    'DEFERRED_TO_JP-UX-CMS-01',
    'DEFERRED_TO_JP-RUNTIME-01',
    'DEFERRED_TO_JP-FULLSTACK-01',
    'DEFERRED_TO_JP-DEPLOY-01_BLADE_RETIREMENT',
    'BACKEND_WITHOUT_NEXT_BINDING',
];

foreach ($classes as $class) {
    $names = [];
    foreach ($j['rows'] as $row) {
        if (($row['jp_ops_07_class'] ?? '') === $class) {
            $names[] = (string) $row['name'];
        }
    }
    echo $class.'='.count($names)."\n";
    foreach ($names as $name) {
        echo $name."\n";
    }
    echo "---\n";
}
