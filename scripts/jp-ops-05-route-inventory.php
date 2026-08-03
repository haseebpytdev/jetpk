<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Artisan::call('route:list', ['--json' => true]);
$json = Artisan::output();

/** @var list<array<string, mixed>> $routes */
$routes = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

$mutationMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
$rows = [];

foreach ($routes as $route) {
    $uri = (string) ($route['uri'] ?? '');
    $methodField = (string) ($route['method'] ?? '');
    $methods = explode('|', $methodField);
    $hasMutation = (bool) array_intersect($methods, $mutationMethods);
    if (! $hasMutation) {
        continue;
    }

    $portal = null;
    if (str_starts_with($uri, 'admin/page-settings')) {
        $portal = 'admin_page_settings';
    } elseif (str_starts_with($uri, 'admin')) {
        $portal = 'admin';
    } elseif (str_starts_with($uri, 'staff')) {
        $portal = 'staff';
    } else {
        continue;
    }

    $rows[] = [
        'portal' => $portal,
        'methods' => array_values(array_intersect($methods, $mutationMethods)),
        'uri' => $uri,
        'name' => (string) ($route['name'] ?? ''),
        'action' => (string) ($route['action'] ?? ''),
    ];
}

usort($rows, static fn (array $a, array $b): int => [$a['portal'], $a['uri']] <=> [$b['portal'], $b['uri']]);

$out = [
    'generated_at' => gmdate('c'),
    'denominator_semantics' => 'JP-OPS-01 blade_operational_mutations: POST|PUT|PATCH|DELETE on admin/*, staff/*, admin/page-settings/* route records including admin and staff fallback catch-alls',
    'total' => count($rows),
    'by_portal' => [
        'admin' => count(array_filter($rows, static fn (array $r): bool => $r['portal'] === 'admin')),
        'staff' => count(array_filter($rows, static fn (array $r): bool => $r['portal'] === 'staff')),
        'admin_page_settings' => count(array_filter($rows, static fn (array $r): bool => $r['portal'] === 'admin_page_settings')),
    ],
    'rows' => array_values(array_map(static function (array $row, int $index): array {
        return ['number' => $index + 1] + $row;
    }, $rows, array_keys($rows))),
];

$path = __DIR__.'/../storage/app/jp-ops-05-mutation-inventory.json';
file_put_contents($path, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "total={$out['total']}\n";
echo "admin={$out['by_portal']['admin']}\n";
echo "staff={$out['by_portal']['staff']}\n";
echo "admin_page_settings={$out['by_portal']['admin_page_settings']}\n";
echo "written={$path}\n";
