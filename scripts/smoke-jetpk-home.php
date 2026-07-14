<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/jetpk/home', 'GET');
$response = $kernel->handle($request);

echo 'status='.$response->getStatusCode().PHP_EOL;

$body = (string) $response->getContent();
$checks = [
    'ota-flight-search' => str_contains($body, 'ota-flight-search'),
    'Return trip mode' => str_contains($body, 'Return'),
    'One-way trip mode' => str_contains($body, 'One-way'),
    'Multi-city' => str_contains($body, 'Multi-city'),
    'Groups tab' => str_contains($body, 'data-product-tab="groups"'),
    'Umrah hidden' => ! preg_match('/Umrah/i', $body),
    'airport autocomplete' => str_contains($body, 'js-airport-autocomplete'),
    'pax picker' => str_contains($body, 'ota-hero-search-pax'),
    'logo.svg' => str_contains($body, 'jetpk-assets/logo/logo.svg'),
    'favicon.ico' => str_contains($body, 'jetpk-assets/favicon/favicon.ico'),
    'client_route home' => str_contains($body, '/jetpk/home') || str_contains($body, '/jetpk/login'),
];

foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS' : 'FAIL').' '.$label.PHP_EOL;
}

$kernel->terminate($request, $response);
