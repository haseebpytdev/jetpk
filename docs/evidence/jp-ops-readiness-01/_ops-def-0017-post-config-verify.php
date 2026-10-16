<?php
/**
 * OPS-DEF-0017 post-config verify + optional safe connectivity.
 * Never prints merchant_id, secret, ciphertext, or raw API bodies.
 */
declare(strict_types=1);

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\BookingPayment;
use App\Services\Payments\PaymentGatewaySettingsService;
use App\Services\Payments\PaymentTransactionService;
use App\Support\Payments\PublicAbhiPayCheckoutPresenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

function yn($v): string
{
    return $v ? 'YES' : 'NO';
}

function hostOnly(?string $url): string
{
    if (! filled($url)) {
        return '';
    }
    $p = parse_url((string) $url);
    if (! $p || empty($p['host'])) {
        return 'MALFORMED';
    }

    return ($p['scheme'] ?? '').'://'.$p['host'].($p['path'] ?? '');
}

function norm(?string $u): string
{
    $u = rtrim((string) $u, '/');
    $u = preg_replace('#^http://#i', 'https://', $u) ?? $u;
    $u = preg_replace('#^https://www\.#i', 'https://', $u) ?? $u;

    return strtolower($u);
}

$runConnectivity = in_array('--run-connectivity', $argv ?? [], true);

echo "=== OPS_DEF_0017_POST_CONFIG ===\n";
echo 'RUNTIME_SHA='.trim((string) @file_get_contents($appRoot.'/.jetpk-runtime-sha'))."\n";
echo 'FRONTEND_BUILD_ID='.trim((string) @file_get_contents($appRoot.'/frontend/.next/BUILD_ID'))."\n";

$agencyId = 1;
$rows = PaymentGateway::query()
    ->where('code', PaymentGateway::CODE_ABHIPAY)
    ->where('agency_id', $agencyId)
    ->orderBy('id')
    ->get();

echo 'AGENCY_1_ABHIPAY_ROW_COUNT='.$rows->count()."\n";
echo 'ALL_ABHIPAY_ROWS='.PaymentGateway::query()->where('code', PaymentGateway::CODE_ABHIPAY)->count()."\n";
echo 'PLATFORM_ABHIPAY_ROWS='.PaymentGateway::query()->where('code', PaymentGateway::CODE_ABHIPAY)->whereNull('agency_id')->count()."\n";

$ptPre = PaymentTransaction::query()->count();
$bpPre = BookingPayment::query()->count();
$abhiPre = PaymentTransaction::query()->where('gateway', 'abhipay')->count();
$ledgerPre = 0;
$walletPre = 0;
try {
    $ledgerPre = (int) DB::table('ledger_transactions')->count();
} catch (Throwable $e) {
    $ledgerPre = -1;
}
try {
    $walletPre = (int) DB::table('agent_wallets')->sum('balance');
} catch (Throwable $e) {
    $walletPre = -1;
}

echo "PAYMENT_TRANSACTIONS_PRE={$ptPre}\n";
echo "BOOKING_PAYMENTS_PRE={$bpPre}\n";
echo "ABHIPAY_TRANSACTIONS_PRE={$abhiPre}\n";
echo "LEDGER_TX_COUNT_PRE={$ledgerPre}\n";
echo "WALLET_BALANCE_SUM_PRE_PRESENT=".yn($walletPre !== -1)."\n";

$expectedCb = 'https://jetpakistan.pk/payments/abhipay/callback';
$expectedSuccess = 'https://jetpakistan.pk/payment/success';
$expectedCancel = 'https://jetpakistan.pk/payment/cancel';
$expectedDecline = 'https://jetpakistan.pk/payment/decline';

$gateway = $rows->first();
if ($gateway === null) {
    echo "ABHIPAY_ROW_EXISTS=NO\n";
    echo "ABHIPAY_ACTIVE=NO\n";
    echo "ABHIPAY_ENVIRONMENT=\n";
    echo "ABHIPAY_MERCHANT_ID_CONFIGURED=NO\n";
    echo "ABHIPAY_SECRET_CONFIGURED=NO\n";
    echo "ABHIPAY_CHECKOUT_READY=NO\n";
    echo "CONNECTIVITY_TEST_EXECUTED=NO\n";
    echo "CONNECTIVITY_SKIP_REASON=NO_ROW\n";
    echo "=== END ===\n";
    exit(0);
}

echo 'GATEWAY_ID='.$gateway->id."\n";
echo 'AGENCY_ID='.($gateway->agency_id ?? 'NULL')."\n";
echo 'ABHIPAY_ROW_EXISTS=YES'."\n";
echo 'ABHIPAY_ACTIVE='.yn((bool) $gateway->is_active)."\n";
echo 'ABHIPAY_ENVIRONMENT='.(string) $gateway->environment."\n";
echo 'ABHIPAY_MERCHANT_ID_CONFIGURED='.yn(filled($gateway->merchant_id))."\n";
echo 'ABHIPAY_SECRET_CONFIGURED='.yn($gateway->hasMerchantSecretKey())."\n";
echo 'ABHIPAY_MERCHANT_ID_MASKED='.($gateway->maskedMerchantId() ?? 'not set')."\n";
echo 'ABHIPAY_CHECKOUT_READY='.yn($gateway->isAvailableForCheckout())."\n";
echo 'ABHIPAY_BASE_URL='.hostOnly((string) ($gateway->base_url ?: PaymentGateway::DEFAULT_BASE_URL))."\n";
echo 'CALLBACK_URL_STORED='.hostOnly((string) $gateway->callback_url)."\n";
echo 'SUCCESS_URL_STORED='.hostOnly((string) $gateway->success_url)."\n";
echo 'CANCEL_URL_STORED='.hostOnly((string) $gateway->cancel_url)."\n";
echo 'DECLINE_URL_STORED='.hostOnly((string) $gateway->decline_url)."\n";

$routeCb = Route::has('payments.abhipay.callback') ? route('payments.abhipay.callback') : '';
$routeSuccess = Route::has('payments.success') ? route('payments.success') : '';
$routeCancel = Route::has('payments.cancel') ? route('payments.cancel') : '';
$routeDecline = Route::has('payments.decline') ? route('payments.decline') : '';

$cbMatch = filled($gateway->callback_url) && (
    norm($gateway->callback_url) === norm($expectedCb) || norm($gateway->callback_url) === norm($routeCb)
);
$successMatch = filled($gateway->success_url) && (
    norm($gateway->success_url) === norm($expectedSuccess) || norm($gateway->success_url) === norm($routeSuccess)
);
$cancelMatch = filled($gateway->cancel_url) && (
    norm($gateway->cancel_url) === norm($expectedCancel) || norm($gateway->cancel_url) === norm($routeCancel)
);
$declineMatch = filled($gateway->decline_url) && (
    norm($gateway->decline_url) === norm($expectedDecline) || norm($gateway->decline_url) === norm($routeDecline)
);

echo 'CALLBACK_URL_MATCH='.yn($cbMatch)."\n";
echo 'SUCCESS_URL_MATCH='.yn($successMatch)."\n";
echo 'CANCEL_URL_MATCH='.yn($cancelMatch)."\n";
echo 'DECLINE_URL_MATCH='.yn($declineMatch)."\n";

$base = (string) ($gateway->base_url ?: PaymentGateway::DEFAULT_BASE_URL);
$bp = parse_url($base);
$baseValid = isset($bp['scheme'], $bp['host'])
    && strtolower((string) $bp['scheme']) === 'https'
    && stripos((string) $bp['host'], 'abhipay.com.pk') !== false
    && ! in_array(strtolower((string) $bp['host']), ['localhost', '127.0.0.1'], true);
echo 'ABHIPAY_BASE_URL_VALID='.yn($baseValid)."\n";

$svc = app(PaymentTransactionService::class);
$resolved = $svc->activeAbhiPayGateway($agencyId);
echo 'TARGET_AGENCY_CHECKOUT_GATEWAY_RESOLVES='.yn($resolved !== null)."\n";
echo 'RESOLVED_GATEWAY_ID='.($resolved?->id ?? 'NONE')."\n";
echo 'RESOLVED_ENVIRONMENT='.($resolved?->environment ?? 'N/A')."\n";

// UI readiness via presenter on an existing unpaid booking if any (no mutation)
$payUi = 'NOT_TESTED_NO_SAFE_BOOKING';
try {
    $booking = \App\Models\Booking::query()
        ->where('agency_id', $agencyId)
        ->where(function ($q) {
            $q->whereNull('payment_status')
                ->orWhereNotIn('payment_status', ['paid', 'fully_paid']);
        })
        ->orderByDesc('id')
        ->first();
    if ($booking !== null) {
        $presenter = app(PublicAbhiPayCheckoutPresenter::class);
        $view = $presenter->forBooking($booking, true);
        echo 'QA_BOOKING_ID_USED='.$booking->id."\n";
        echo 'PRESENTER_GATEWAY_AVAILABLE='.yn((bool) ($view['gateway_available'] ?? false))."\n";
        echo 'PRESENTER_CAN_START='.yn((bool) ($view['can_start'] ?? false))."\n";
        echo 'PRESENTER_SHOW_PAY_BUTTON='.yn((bool) ($view['show_pay_button'] ?? false))."\n";
        $payUi = ((bool) ($view['gateway_available'] ?? false)) ? 'YES' : 'NO';
    }
} catch (Throwable $e) {
    echo 'PRESENTER_PROBE_ERROR=YES'."\n";
    $payUi = 'NOT_TESTED_PRESENTER_ERROR';
}
echo "PAY_ONLINE_UI_READY={$payUi}\n";

$gatesOk = $gateway->is_active
    && strtolower((string) $gateway->environment) === 'live'
    && $gateway->isConfigured()
    && $gateway->isAvailableForCheckout()
    && $baseValid
    && $cbMatch;

echo 'CONNECTIVITY_GATES_OK='.yn($gatesOk)."\n";

if (! $runConnectivity) {
    echo "CONNECTIVITY_TEST_EXECUTED=NO\n";
    echo "CONNECTIVITY_SKIP_REASON=VERIFY_ONLY_PASS\n";
    echo "=== END ===\n";
    exit(0);
}

if (! $gatesOk) {
    echo "CONNECTIVITY_TEST_EXECUTED=NO\n";
    echo "CONNECTIVITY_SKIP_REASON=GATES_NOT_MET\n";
    echo "=== END ===\n";
    exit(0);
}

$settings = app(PaymentGatewaySettingsService::class);
$result = $settings->testConnection($gateway);

echo 'CONNECTIVITY_TEST_EXECUTED=YES'."\n";
echo 'CONNECTIVITY_HTTP_STATUS='.($result['http_status'] === null ? 'NULL' : (string) $result['http_status'])."\n";
echo 'CONNECTIVITY_OK='.yn((bool) $result['ok'])."\n";
// Safe message only — service already returns non-secret strings
$msg = (string) ($result['message'] ?? '');
$msg = preg_replace('/[A-Za-z0-9_\-]{20,}/', '[REDACTED_TOKENISH]', $msg) ?? $msg;
echo 'CONNECTIVITY_SAFE_MESSAGE='.substr($msg, 0, 200)."\n";

$status = $result['http_status'];
if ($status === 401 || $status === 403) {
    echo "CONNECTIVITY_RESULT=CREDENTIALS_REJECTED\n";
} elseif ($result['ok'] === true) {
    echo "CONNECTIVITY_RESULT=REACHABLE_WITH_CREDENTIALS\n";
} elseif ($status === null) {
    echo "CONNECTIVITY_RESULT=CONNECTIVITY_FAILED\n";
} else {
    echo "CONNECTIVITY_RESULT=API_REACHED_STATUS_".$status."\n";
}

$ptPost = PaymentTransaction::query()->count();
$bpPost = BookingPayment::query()->count();
$abhiPost = PaymentTransaction::query()->where('gateway', 'abhipay')->count();
$ledgerPost = $ledgerPre;
try {
    $ledgerPost = (int) DB::table('ledger_transactions')->count();
} catch (Throwable $e) {
}

echo 'PAYMENT_TRANSACTIONS_POST='.$ptPost."\n";
echo 'BOOKING_PAYMENTS_POST='.$bpPost."\n";
echo 'ABHIPAY_TRANSACTIONS_POST='.$abhiPost."\n";
echo 'PAYMENT_TRANSACTIONS_CREATED='.($ptPost - $ptPre)."\n";
echo 'BOOKING_PAYMENTS_CREATED='.($bpPost - $bpPre)."\n";
echo 'ABHIPAY_TRANSACTIONS_CREATED='.($abhiPost - $abhiPre)."\n";
echo 'LEDGER_TX_DELTA='.($ledgerPost - $ledgerPre)."\n";

echo "=== END ===\n";
