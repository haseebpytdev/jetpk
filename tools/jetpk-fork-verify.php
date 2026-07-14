<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$resolver = $app->make(App\Services\Client\ClientProfileResolver::class);
$profile = $resolver->resolveBySlug('jetpk');

echo 'config_slug='.config('ota_client.slug').PHP_EOL;
echo 'single_root='.(config('ota_client.single_client_root') ? 'yes' : 'no').PHP_EOL;
echo 'parity='.(config('client_route_parity.enabled') ? 'on' : 'off').PHP_EOL;

if ($profile === null) {
    echo "jetpk_profile=MISSING\n";
    exit(1);
}

echo 'jetpk_slug='.$profile->slug.PHP_EOL;
echo 'jetpk_active='.($profile->is_active ? '1' : '0').PHP_EOL;
echo 'jetpk_theme='.$profile->active_frontend_theme.PHP_EOL;
echo 'jetpk_admin_theme='.$profile->active_admin_theme.PHP_EOL;
echo 'jetpk_name='.$profile->name.PHP_EOL;
