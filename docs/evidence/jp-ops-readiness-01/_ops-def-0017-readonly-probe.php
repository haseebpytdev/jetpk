<?php
/**
 * OPS-DEF-0017 READ-ONLY AbhiPay activation probe.
 * Never prints merchant_id/secret ciphertext or raw credentials.
 */
declare(strict_types=1);

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Agency;
use App\Models\BookingPayment;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Services\Payments\PaymentTransactionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

function yn($v): string
{
    return $v ? 'YES' : 'NO';
}

function hostOnly(?string $url): string
{
    if (! $url) {
        return '';
    }
    $p = parse_url($url);
    if (! $p || empty($p['host'])) {
        return 'MALFORMED';
    }
    $host = $p['host'];
    $path = $p['path'] ?? '';
    $scheme = $p['scheme'] ?? '';

    return $scheme.'://'.$host.$path;
}

echo "=== OPS_DEF_0017_READONLY_PROBE ===\n";
echo 'RUNTIME_SHA='.trim((string) @file_get_contents($appRoot.'/.jetpk-runtime-sha'))."\n";
echo 'FRONTEND_BUILD_ID='.trim((string) @file_get_contents($appRoot.'/frontend/.next/BUILD_ID'))."\n";
echo 'DASHBOARD_BUILD_ID='.trim((string) @file_get_contents($appRoot.'/dashboard/.next/BUILD_ID'))."\n";

$disk = trim(shell_exec("df -P /home/pkjetp | awk 'NR==2{print \$5}'") ?? '');
echo 'DISK_USED_PERCENT='.rtrim($disk, '%')."\n";

$failed = (int) DB::table('failed_jobs')->count();
$pendingDefault = (int) DB::table('jobs')->where('queue', 'default')->count();
echo "FAILED_JOBS={$failed}\n";
echo "PENDING_DEFAULT={$pendingDefault}\n";

$fj = DB::table('failed_jobs')->where('id', 148)->first();
if ($fj) {
    $payload = (string) ($fj->payload ?? '');
    $exception = (string) ($fj->exception ?? '');
    $isSmtp = (stripos($exception, 'SMTP') !== false || stripos($exception, 'mail') !== false || stripos($payload, 'mail') !== false);
    $isPayment = (stripos($exception, 'abhipay') !== false || stripos($exception, 'Payment') !== false || stripos($payload, 'AbhiPay') !== false || stripos($payload, 'PaymentTransaction') !== false);
    echo "FAILED_JOB_148_EXISTS=YES\n";
    echo 'FAILED_JOB_148_QUEUE='.($fj->queue ?? '')."\n";
    echo 'FAILED_JOB_148_FAILED_AT='.($fj->failed_at ?? '')."\n";
    if (preg_match('/"displayName"\s*:\s*"([^"]+)"/', $payload, $m)) {
        echo 'FAILED_JOB_148_DISPLAY='.$m[1]."\n";
    } elseif (preg_match('/"job"\s*:\s*"([^"]+)"/', $payload, $m)) {
        echo 'FAILED_JOB_148_JOB='.$m[1]."\n";
    }
    $exShort = preg_replace('/\s+/', ' ', substr($exception, 0, 180));
    $exShort = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[REDACTED_EMAIL]', $exShort);
    echo 'FAILED_JOB_148_EXCEPTION_HEAD='.$exShort."\n";
    echo 'FAILED_JOB_148_SMTPISH='.yn($isSmtp)."\n";
    echo 'FAILED_JOB_PAYMENT_RELATED='.yn($isPayment)."\n";
} else {
    echo "FAILED_JOB_148_EXISTS=NO\n";
}

$rows = PaymentGateway::query()->where('code', PaymentGateway::CODE_ABHIPAY)->orderBy('id')->get();
echo 'ABHIPAY_GATEWAY_ROWS='.$rows->count()."\n";
$platform = 0;
$agencyScoped = 0;
$activeLive = 0;
$activeTest = 0;
foreach ($rows as $g) {
    if ($g->agency_id === null) {
        $platform++;
    } else {
        $agencyScoped++;
    }
    if ($g->is_active && $g->environment === 'live') {
        $activeLive++;
    }
    if ($g->is_active && $g->environment === 'test') {
        $activeTest++;
    }
    echo "--- GATEWAY ---\n";
    echo "GATEWAY_ID={$g->id}\n";
    echo 'AGENCY_ID='.($g->agency_id === null ? 'NULL_PLATFORM' : (string) $g->agency_id)."\n";
    echo "CODE={$g->code}\n";
    echo "ENVIRONMENT={$g->environment}\n";
    echo 'IS_ACTIVE='.yn((bool) $g->is_active)."\n";
    echo 'MERCHANT_ID_CONFIGURED='.yn(filled($g->merchant_id))."\n";
    echo 'MERCHANT_SECRET_CONFIGURED='.yn($g->hasMerchantSecretKey())."\n";
    echo 'BASE_URL_HOST='.hostOnly((string) ($g->base_url ?: PaymentGateway::DEFAULT_BASE_URL))."\n";
    echo 'CALLBACK_URL='.hostOnly((string) $g->callback_url)."\n";
    echo 'SUCCESS_URL='.hostOnly((string) $g->success_url)."\n";
    echo 'CANCEL_URL='.hostOnly((string) $g->cancel_url)."\n";
    echo 'DECLINE_URL='.hostOnly((string) $g->decline_url)."\n";
    echo "CREATED_AT={$g->created_at}\n";
    echo "UPDATED_AT={$g->updated_at}\n";
    echo 'IS_CONFIGURED='.yn($g->isConfigured())."\n";
    echo 'CHECKOUT_AVAILABLE='.yn($g->isAvailableForCheckout())."\n";
}
echo 'PLATFORM_ABHIPAY_ROW_EXISTS='.yn($platform > 0)."\n";
echo "PLATFORM_ABHIPAY_ROW_COUNT={$platform}\n";
echo "AGENCY_SCOPED_ABHIPAY_ROWS={$agencyScoped}\n";
echo "ACTIVE_LIVE_ROWS={$activeLive}\n";
echo "ACTIVE_TEST_ROWS={$activeTest}\n";

echo 'CALLBACK_ROUTE_PRESENT='.yn(Route::has('payments.abhipay.callback'))."\n";
echo 'SUCCESS_ROUTE_PRESENT='.yn(Route::has('payments.success'))."\n";
echo 'CANCEL_ROUTE_PRESENT='.yn(Route::has('payments.cancel'))."\n";
echo 'DECLINE_ROUTE_PRESENT='.yn(Route::has('payments.decline'))."\n";
try {
    echo 'CALLBACK_ROUTE_URL='.route('payments.abhipay.callback')."\n";
    echo 'SUCCESS_ROUTE_URL='.route('payments.success')."\n";
    echo 'CANCEL_ROUTE_URL='.route('payments.cancel')."\n";
    echo 'DECLINE_ROUTE_URL='.route('payments.decline')."\n";
} catch (Throwable $e) {
    echo 'ROUTE_URL_ERROR='.$e->getMessage()."\n";
}

$expectedCb = 'https://jetpakistan.pk/payments/abhipay/callback';
$expectedSuccess = 'https://jetpakistan.pk/payment/success';
$expectedCancel = 'https://jetpakistan.pk/payment/cancel';
$expectedDecline = 'https://jetpakistan.pk/payment/decline';
$norm = function (?string $u) {
    $u = rtrim((string) $u, '/');
    $u = preg_replace('#^http://#i', 'https://', $u);
    $u = preg_replace('#^https://www\.#i', 'https://', $u);

    return strtolower($u);
};
foreach ($rows as $g) {
    $cbMatch = filled($g->callback_url) && (
        $norm($g->callback_url) === $norm($expectedCb)
        || $norm($g->callback_url) === $norm(route('payments.abhipay.callback'))
    );
    $successMatch = filled($g->success_url) && (
        $norm($g->success_url) === $norm($expectedSuccess)
        || $norm($g->success_url) === $norm(route('payments.success'))
    );
    $cancelMatch = filled($g->cancel_url) && (
        $norm($g->cancel_url) === $norm($expectedCancel)
        || $norm($g->cancel_url) === $norm(route('payments.cancel'))
    );
    $declineMatch = filled($g->decline_url) && (
        $norm($g->decline_url) === $norm($expectedDecline)
        || $norm($g->decline_url) === $norm(route('payments.decline'))
    );
    echo "GATEWAY_{$g->id}_CALLBACK_URL_MATCH=".yn($cbMatch)."\n";
    echo "GATEWAY_{$g->id}_SUCCESS_URL_MATCH=".yn($successMatch)."\n";
    echo "GATEWAY_{$g->id}_CANCEL_URL_MATCH=".yn($cancelMatch)."\n";
    echo "GATEWAY_{$g->id}_DECLINE_URL_MATCH=".yn($declineMatch)."\n";
    echo "GATEWAY_{$g->id}_CALLBACK_EMPTY=".yn(! filled($g->callback_url))."\n";
    echo "GATEWAY_{$g->id}_SUCCESS_EMPTY=".yn(! filled($g->success_url))."\n";
    echo "GATEWAY_{$g->id}_CANCEL_EMPTY=".yn(! filled($g->cancel_url))."\n";
    echo "GATEWAY_{$g->id}_DECLINE_EMPTY=".yn(! filled($g->decline_url))."\n";
}

$base = (string) (($rows->first()->base_url ?? null) ?: PaymentGateway::DEFAULT_BASE_URL);
$bp = parse_url($base);
$baseValid = isset($bp['scheme'], $bp['host'])
    && strtolower($bp['scheme']) === 'https'
    && ! in_array(strtolower($bp['host']), ['localhost', '127.0.0.1'], true)
    && stripos($bp['host'], 'staging') === false
    && stripos($bp['host'], 'abhipay.com.pk') !== false;
echo 'ABHIPAY_BASE_URL='.hostOnly($base)."\n";
echo 'ABHIPAY_BASE_URL_VALID='.yn($baseValid)."\n";

echo 'PAYMENT_TRANSACTIONS_TOTAL='.PaymentTransaction::query()->count()."\n";
echo 'ABHIPAY_TRANSACTIONS_TOTAL='.PaymentTransaction::query()->where('gateway', 'abhipay')->count()."\n";
echo 'ABHIPAY_LIVE_TRANSACTIONS_TOTAL='.PaymentTransaction::query()->where('gateway', 'abhipay')->where('environment', 'live')->count()."\n";
echo 'ABHIPAY_TEST_TRANSACTIONS_TOTAL='.PaymentTransaction::query()->where('gateway', 'abhipay')->where('environment', 'test')->count()."\n";
echo 'BOOKING_PAYMENTS_TOTAL='.BookingPayment::query()->count()."\n";
$verifiedAbhi = 0;
try {
    $verifiedAbhi = BookingPayment::query()->where('method', 'abhipay')->where('status', 'verified')->count();
} catch (Throwable $e) {
    $verifiedAbhi = BookingPayment::query()
        ->where(function ($q) {
            $q->where('method', 'abhipay')->orWhere('payment_method', 'abhipay');
        })
        ->where('status', 'verified')
        ->count();
}
echo "VERIFIED_ABHIPAY_BOOKING_PAYMENTS={$verifiedAbhi}\n";
echo 'FAILED_ABHIPAY_TRANSACTIONS='.PaymentTransaction::query()->where('gateway', 'abhipay')->whereIn('status', ['failed', 'declined', 'cancelled'])->count()."\n";
echo 'PENDING_ABHIPAY_TRANSACTIONS='.PaymentTransaction::query()->where('gateway', 'abhipay')->whereIn('status', ['pending', 'initiated', 'redirected', 'processing', 'created'])->count()."\n";

echo 'HAS_BOOKING_PAYMENTS_PTX_COL='.yn(Schema::hasColumn('booking_payments', 'payment_transaction_id'))."\n";
$indexes = DB::select('SHOW INDEX FROM booking_payments');
$bpUniquePtx = false;
foreach ($indexes as $idx) {
    if (($idx->Column_name ?? '') === 'payment_transaction_id' && (int) ($idx->Non_unique ?? 1) === 0) {
        $bpUniquePtx = true;
    }
}
echo 'BOOKING_PAYMENTS_PTX_UNIQUE='.yn($bpUniquePtx)."\n";
$ptIndexes = DB::select('SHOW INDEX FROM payment_transactions');
$clientTxUnique = false;
$gatewayOrderUnique = false;
$gatewayOrderParts = [];
foreach ($ptIndexes as $idx) {
    if (($idx->Column_name ?? '') === 'client_transaction_id' && (int) ($idx->Non_unique ?? 1) === 0) {
        $clientTxUnique = true;
    }
    if ((int) ($idx->Non_unique ?? 1) === 0 && in_array($idx->Column_name ?? '', ['gateway', 'environment', 'gateway_order_id'], true)) {
        $gatewayOrderParts[$idx->Key_name][] = $idx->Column_name;
    }
}
foreach ($gatewayOrderParts as $cols) {
    $cols = array_values(array_unique($cols));
    sort($cols);
    if ($cols === ['environment', 'gateway', 'gateway_order_id']) {
        $gatewayOrderUnique = true;
    }
}
echo 'CLIENT_TX_UNIQUE='.yn($clientTxUnique)."\n";
echo 'GATEWAY_ORDER_COMPOSITE_UNIQUE='.yn($gatewayOrderUnique)."\n";

$svc = app(PaymentTransactionService::class);
$agencies = Agency::query()->orderBy('id')->limit(20)->get(['id', 'name', 'code']);
echo 'AGENCY_RESOLUTION_SAMPLE_COUNT='.$agencies->count()."\n";
foreach ($agencies as $agency) {
    $gw = $svc->activeAbhiPayGateway($agency->id);
    echo 'AGENCY_RESOLVE id='.$agency->id
        .' RESOLVED_GATEWAY_ID='.($gw?->id ?? 'NONE')
        .' ENV='.($gw?->environment ?? 'N/A')
        .' ACTIVE='.yn((bool) ($gw?->is_active))
        .' CONFIGURED='.yn((bool) ($gw?->isConfigured()))
        .' CHECKOUT_AVAILABLE='.yn($gw !== null)
        ."\n";
}
$pgw = $svc->activeAbhiPayGateway(null);
echo 'PLATFORM_ACTIVE_RESOLVED='.($pgw?->id ?? 'NONE')."\n";

$bootstrap = (string) file_get_contents($appRoot.'/bootstrap/app.php');
echo 'CSRF_EXEMPT_CALLBACK_PATH='.yn(str_contains($bootstrap, 'payments/abhipay/callback'))."\n";

echo 'SAFE_CONNECTIVITY_CHECK_AVAILABLE='.yn($rows->contains(fn ($g) => $g->isConfigured()))."\n";
echo "EXTERNAL_CONNECTIVITY_EXECUTED=NO\n";
echo "=== END ===\n";
