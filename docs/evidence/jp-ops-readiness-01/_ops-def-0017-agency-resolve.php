<?php
declare(strict_types=1);

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Agency;
use App\Models\PaymentGateway;
use App\Services\Payments\PaymentTransactionService;
use Illuminate\Support\Facades\DB;

$svc = app(PaymentTransactionService::class);
echo 'AGENCY_COUNT='.Agency::query()->count().PHP_EOL;
echo 'TOTAL_PAYMENT_GATEWAYS='.PaymentGateway::query()->count().PHP_EOL;
echo 'ABHIPAY_ROWS='.PaymentGateway::query()->where('code', 'abhipay')->count().PHP_EOL;

foreach (Agency::query()->orderBy('id')->limit(15)->pluck('id') as $id) {
    $gw = $svc->activeAbhiPayGateway((int) $id);
    echo 'AGENCY_ID='.$id
        .' RESOLVED_GATEWAY_ID='.($gw?->id ?? 'NONE')
        .' ENV='.($gw?->environment ?? 'N/A')
        .' ACTIVE='.(($gw && $gw->is_active) ? 'YES' : 'NO')
        .' CONFIGURED='.(($gw && $gw->isConfigured()) ? 'YES' : 'NO')
        .' CHECKOUT_AVAILABLE='.($gw ? 'YES' : 'NO')
        .PHP_EOL;
}

$pg = $svc->activeAbhiPayGateway(null);
echo 'PLATFORM_RESOLVED='.($pg?->id ?? 'NONE').PHP_EOL;

foreach (DB::table('booking_payments')->select('method', DB::raw('count(*) as c'))->groupBy('method')->get() as $r) {
    echo 'BOOKING_PAYMENT_METHOD='.$r->method.' COUNT='.$r->c.PHP_EOL;
}
