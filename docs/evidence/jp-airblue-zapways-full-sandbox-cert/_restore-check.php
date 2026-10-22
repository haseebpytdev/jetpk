<?php
require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c = App\Models\SupplierConnection::find(22);
$cred = is_array($c->credentials) ? $c->credentials : [];
echo 'AIRBLUE_EXTERNAL_CALLS_EFFECTIVELY_ENABLED_AFTER='.(filter_var(env('AIRBLUE_EXTERNAL_CALLS_ENABLED', false), FILTER_VALIDATE_BOOLEAN) ? 'YES' : 'NO').PHP_EOL;
echo 'AIRBLUE_CLI_LIVE_ENABLED_AFTER='.(filter_var(env('AIRBLUE_CLI_LIVE_ENABLED', false), FILTER_VALIDATE_BOOLEAN) ? 'YES' : 'NO').PHP_EOL;
echo 'AIRBLUE_SEARCH_CERTIFIED='.(filter_var($cred['search_certified'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'YES' : 'NO').PHP_EOL;
echo 'AIRBLUE_CERTIFICATION_STATUS='.((string) ($cred['certification_status'] ?? 'unset')).PHP_EOL;
echo 'AIRBLUE_PUBLIC_SEARCH_ENABLED_AFTER='.(filter_var($cred['search_certified'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'YES' : 'NO').PHP_EOL;
echo 'HOST='.(parse_url((string) $c->base_url, PHP_URL_HOST) ?: '').PHP_EOL;
echo 'ENV='.((string) ($c->environment?->value ?? '')).PHP_EOL;
