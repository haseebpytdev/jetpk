<?php

require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$total = App\Models\PaymentGateway::query()->count();
$abhi = App\Models\PaymentGateway::query()->where('code', App\Models\PaymentGateway::CODE_ABHIPAY)->count();

$activeLive = 0;
$activeTest = 0;
$rows = App\Models\PaymentGateway::query()->get([
    'id', 'code', 'name', 'is_active', 'environment', 'agency_id', 'merchant_id', 'merchant_secret_key',
]);
foreach ($rows as $r) {
    $env = strtolower((string) ($r->environment ?? ''));
    $active = (bool) ($r->is_active ?? false);
    $configured = $r->isConfigured();
    if ($active && $configured) {
        if (str_contains($env, 'live') || $env === 'production') {
            $activeLive++;
        } else {
            $activeTest++;
        }
    }
    echo 'ROW|'.implode('|', [
        (string) $r->id,
        (string) ($r->code ?? ''),
        $active ? '1' : '0',
        (string) ($r->environment ?? ''),
        (string) ($r->agency_id ?? ''),
        $configured ? 'configured' : 'incomplete',
        $r->maskedMerchantId() ?? 'none',
    ]).PHP_EOL;
}

echo "PAYMENT_GATEWAY_ROWS={$total}\n";
echo "ABHIPAY_ROWS={$abhi}\n";
echo "ACTIVE_TEST_ROWS={$activeTest}\n";
echo "ACTIVE_LIVE_ROWS={$activeLive}\n";
echo 'CHECKOUT_AVAILABLE='.(($activeLive + $activeTest) > 0 ? 'YES' : 'NO')."\n";
