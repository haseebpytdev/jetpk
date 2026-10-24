<?php
declare(strict_types=1);
$appRoot = '/home/pkjetp/jetpk_app';
chdir($appRoot);
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$c = App\Models\SupplierConnection::find(22);
if (! $c) {
    echo "CONN22=MISSING\n";
    exit(2);
}
$cred = is_array($c->credentials) ? $c->credentials : [];
$host = (string) (parse_url((string) $c->base_url, PHP_URL_HOST) ?: '');
$ticketing = filter_var($cred['ticketing_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
echo 'TICKETING_ENABLED_NOW='.($ticketing ? 'true' : 'false').PHP_EOL;
echo 'HOST='.$host.PHP_EOL;
echo 'ENV='.(string) ($c->environment?->value ?? '').PHP_EOL;
