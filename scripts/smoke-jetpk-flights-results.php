<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$checks = [];

foreach ([
    '/jetpk/flights/results?from=KHI&to=DXB&depart='.date('Y-m-d', strtotime('+14 days')).'&trip_type=one_way&adults=1&children=0&infants=0&cabin=economy',
    '/jetpk/home',
] as $uri) {
    $request = Illuminate\Http\Request::create($uri, 'GET');
    $response = $kernel->handle($request);
    $body = (string) $response->getContent();
    $checks[$uri] = [
        'status' => $response->getStatusCode(),
        'jp_results_view' => str_contains($body, 'jp-flights-results'),
        'results_partial' => str_contains($body, 'data-results-root'),
        'jp_results_css' => str_contains($body, 'results.css'),
        'ota_public_css' => str_contains($body, 'ota-public.css'),
        'jp_result_card_class' => str_contains($body, 'jp-result-card'),
    ];
    $kernel->terminate($request, $response);
}

foreach ($checks as $uri => $row) {
    echo $uri.PHP_EOL;
    foreach ($row as $k => $v) {
        echo '  '.($v === true || $v === 1 || (is_string($v) && $v !== '') ? 'OK' : 'FAIL')." {$k}: ".json_encode($v).PHP_EOL;
    }
}

// View resolver
$resolver = app(\App\Services\Client\RuntimeViewResolver::class);
$sample = $resolver->resolveSample('frontend.flights.results', 'frontend');
echo 'resolved_view: '.$sample['resolved_view_name'].PHP_EOL;
