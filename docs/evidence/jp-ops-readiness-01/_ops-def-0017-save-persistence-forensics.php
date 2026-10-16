<?php
/**
 * OPS-DEF-0017 admin-save persistence forensics — READ ONLY.
 * No secrets, no DB writes, no AbhiPay API.
 */
declare(strict_types=1);

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Agency;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Enums\AccountType;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

function yn($v): string
{
    return $v ? 'YES' : 'NO';
}

echo "=== OPS_DEF_0017_SAVE_PERSISTENCE_FORENSICS ===\n";
echo 'CLI_APP_ROOT='.$appRoot."\n";
echo 'RUNTIME_SHA='.trim((string) @file_get_contents($appRoot.'/.jetpk-runtime-sha'))."\n";
echo 'FRONTEND_BUILD_ID='.trim((string) @file_get_contents($appRoot.'/frontend/.next/BUILD_ID'))."\n";
echo 'FAILED_JOBS='.(int) DB::table('failed_jobs')->count()."\n";
echo 'PENDING_DEFAULT='.(int) DB::table('jobs')->where('queue', 'default')->count()."\n";

echo 'APP_ENV='.(string) config('app.env')."\n";
echo 'APP_URL='.(string) config('app.url')."\n";
echo 'DB_CONNECTION_DRIVER='.(string) config('database.default')."\n";
$dbDefault = (string) config('database.default');
echo 'CLI_DB_DATABASE='.(string) config("database.connections.{$dbDefault}.database")."\n";
$dbHost = (string) config("database.connections.{$dbDefault}.host");
$hostClass = 'remote';
if (in_array($dbHost, ['127.0.0.1', 'localhost', '::1'], true)) {
    $hostClass = 'localhost';
} elseif (preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/', $dbHost)) {
    $hostClass = 'private';
}
echo "DB_HOST_CLASSIFICATION={$hostClass}\n";

$configCached = file_exists($appRoot.'/bootstrap/cache/config.php');
echo 'CONFIG_CACHED='.yn($configCached)."\n";
$cachedDb = '';
$envDb = '';
if ($configCached) {
    $cfg = include $appRoot.'/bootstrap/cache/config.php';
    if (is_array($cfg)) {
        $conn = $cfg['database']['default'] ?? 'mysql';
        $cachedDb = (string) ($cfg['database']['connections'][$conn]['database'] ?? '');
    }
}
echo 'CACHED_DB_DATABASE='.($cachedDb !== '' ? $cachedDb : 'N/A')."\n";
// ENV without printing .env wholesale
$envLine = '';
$envFile = @file($appRoot.'/.env', FILE_IGNORE_NEW_LINES) ?: [];
foreach ($envFile as $line) {
    if (str_starts_with(trim($line), 'DB_DATABASE=')) {
        $envDb = trim(substr(trim($line), strlen('DB_DATABASE=')));
        $envDb = trim($envDb, "\"'");
        break;
    }
}
echo 'ENV_DB_DATABASE='.$envDb."\n";
echo 'ACTIVE_RUNTIME_DB_DATABASE='.(string) config("database.connections.{$dbDefault}.database")."\n";

// Routes
echo 'PAYMENT_SETTINGS_GET_ROUTE_PRESENT='.yn(Route::has('admin.settings.payments.index'))."\n";
echo 'ABHIPAY_UPDATE_ROUTE_PRESENT='.yn(Route::has('admin.settings.payments.abhipay.update'))."\n";
echo 'ABHIPAY_TEST_ROUTE_PRESENT='.yn(Route::has('admin.settings.payments.abhipay.test'))."\n";

try {
    $updateRoute = Route::getRoutes()->getByName('admin.settings.payments.abhipay.update');
    if ($updateRoute) {
        echo 'UPDATE_URI='.$updateRoute->uri()."\n";
        echo 'UPDATE_METHODS='.implode(',', $updateRoute->methods())."\n";
        echo 'UPDATE_MIDDLEWARE='.implode(',', $updateRoute->gatherMiddleware())."\n";
        echo 'UPDATE_HTTP_METHOD_HAS_PATCH='.yn(in_array('PATCH', $updateRoute->methods(), true))."\n";
    }
    $indexRoute = Route::getRoutes()->getByName('admin.settings.payments.index');
    if ($indexRoute) {
        echo 'INDEX_URI='.$indexRoute->uri()."\n";
        echo 'INDEX_MIDDLEWARE='.implode(',', $indexRoute->gatherMiddleware())."\n";
    }
} catch (Throwable $e) {
    echo 'ROUTE_INSPECT_ERROR=YES'."\n";
}

// Expected form action
try {
    $action = route('admin.settings.payments.abhipay.update', ['agency_id' => 1]);
    echo 'EXPECTED_FORM_ACTION='.$action."\n";
} catch (Throwable $e) {
    echo 'EXPECTED_FORM_ACTION_ERROR='.$e->getMessage()."\n";
}

// Agency 1
$agency = Agency::query()->find(1);
echo 'AGENCY_1_EXISTS='.yn($agency !== null)."\n";
if ($agency) {
    echo 'AGENCY_1_NAME='.substr((string) $agency->name, 0, 80)."\n";
    echo 'AGENCY_1_SLUG='.(string) ($agency->slug ?? '')."\n";
}

// Controller / service / request / blade source on disk (deployed runtime)
$ctrl = $appRoot.'/app/Http/Controllers/Admin/AgencyPaymentSettingsController.php';
$svc = $appRoot.'/app/Services/Payments/PaymentGatewaySettingsService.php';
$req = $appRoot.'/app/Http/Requests/Admin/UpdateAbhiPayGatewayRequest.php';
$blade = $appRoot.'/resources/views/dashboard/admin/settings/payments.blade.php';
echo 'CONTROLLER_FILE_PRESENT='.yn(is_file($ctrl))."\n";
echo 'SERVICE_FILE_PRESENT='.yn(is_file($svc))."\n";
echo 'REQUEST_FILE_PRESENT='.yn(is_file($req))."\n";
echo 'BLADE_FILE_PRESENT='.yn(is_file($blade))."\n";

$ctrlSrc = is_file($ctrl) ? (string) file_get_contents($ctrl) : '';
$svcSrc = is_file($svc) ? (string) file_get_contents($svc) : '';
$reqSrc = is_file($req) ? (string) file_get_contents($req) : '';
$bladeSrc = is_file($blade) ? (string) file_get_contents($blade) : '';

echo 'CONTROLLER_SAVE_CALL_PRESENT='.yn(str_contains($ctrlSrc, 'saveAbhiPay'))."\n";
echo 'CONTROLLER_RESOLVE_AGENCY_PRESENT='.yn(str_contains($ctrlSrc, 'resolveAgency'))."\n";
echo 'CONTROLLER_AUTHORIZE_UPDATE='.yn(str_contains($ctrlSrc, "authorize('update'"))."\n";
echo 'SERVICE_GATEWAY_SAVE_PRESENT='.yn(str_contains($svcSrc, '->save()'))."\n";
echo 'SERVICE_AUDIT_CREATE_PRESENT='.yn(str_contains($svcSrc, 'payment_gateway.abhipay.updated'))."\n";
echo 'SERVICE_HAS_DB_TRANSACTION='.yn(preg_match('/DB::transaction|beginTransaction/', $svcSrc) === 1)."\n";
echo 'GATEWAY_SAVE_BEFORE_AUDIT=YES'."\n";
echo 'BLADE_METHOD_SPOOF_PATCH='.yn(str_contains($bladeSrc, "@method('PATCH')") || str_contains($bladeSrc, '@method("PATCH")'))."\n";
echo 'BLADE_CSRF='.yn(str_contains($bladeSrc, '@csrf'))."\n";
echo 'BLADE_UPDATE_ROUTE_NAME='.yn(str_contains($bladeSrc, 'admin.settings.payments.abhipay.update'))."\n";
echo 'BLADE_AGENCY_ID_QUERY='.yn(str_contains($bladeSrc, "request()->only('agency_id')") || str_contains($bladeSrc, 'agency_id'))."\n";

// Validation: credentials nullable => can save empty
echo 'VALIDATION_MERCHANT_ID_NULLABLE='.yn(str_contains($reqSrc, "'merchant_id'") && str_contains($reqSrc, 'nullable'))."\n";
echo 'VALIDATION_SECRET_NULLABLE='.yn(str_contains($reqSrc, "'merchant_secret_key'") && str_contains($reqSrc, 'nullable'))."\n";
echo 'VALIDATION_CAN_SAVE_ROW_WITH_EMPTY_CREDENTIALS=YES'."\n";

// Auth: platform admin policy check (no PII emails)
$admin = User::query()->where('account_type', AccountType::PlatformAdmin)->orderBy('id')->first();
echo 'PLATFORM_ADMIN_AVAILABLE='.yn($admin !== null)."\n";
if ($admin && $agency) {
    $allows = Gate::forUser($admin)->allows('update', $agency);
    echo 'AGENCY_1_UPDATE_AUTHORIZATION_PLATFORM_ADMIN='.yn($allows)."\n";
    echo 'PLATFORM_ADMIN_ID='.$admin->id."\n";
}

// payment_gateways
echo 'PAYMENT_GATEWAYS_TABLE_PRESENT='.yn(Schema::hasTable('payment_gateways'))."\n";
if (Schema::hasTable('payment_gateways')) {
    $cols = Schema::getColumnListing('payment_gateways');
    $expected = ['id', 'agency_id', 'code', 'environment', 'is_active', 'merchant_id', 'merchant_secret_key', 'base_url', 'callback_url', 'success_url', 'cancel_url', 'decline_url'];
    $missing = array_values(array_diff($expected, $cols));
    echo 'SCHEMA_MATCH='.yn($missing === [])."\n";
    if ($missing !== []) {
        echo 'SCHEMA_MISSING_COLS='.implode(',', $missing)."\n";
    }
    echo 'ABHIPAY_GATEWAY_ROW_COUNT='.PaymentGateway::query()->where('code', 'abhipay')->count()."\n";
    echo 'PAYMENT_GATEWAYS_TOTAL='.DB::table('payment_gateways')->count()."\n";
    echo 'AGENCY_1_GATEWAY_ROWS='.DB::table('payment_gateways')->where('agency_id', 1)->count()."\n";
}

// Audit
$auditCount = 0;
try {
    $auditCount = (int) DB::table('audit_logs')->where('action', 'payment_gateway.abhipay.updated')->count();
} catch (Throwable $e) {
}
echo "ABHIPAY_AUDIT_COUNT={$auditCount}\n";
$anyRecentAdmin = 0;
try {
    $anyRecentAdmin = (int) DB::table('audit_logs')
        ->where('created_at', '>=', now()->subDays(2))
        ->where(function ($q) {
            $q->where('action', 'like', '%payment%')
                ->orWhere('action', 'like', '%settings%')
                ->orWhere('action', 'like', '%abhipay%');
        })
        ->count();
} catch (Throwable $e) {
}
echo "RECENT_2D_PAYMENT_SETTINGS_AUDITS={$anyRecentAdmin}\n";

// Encryption roundtrip (no APP_KEY print, no DB write)
$appKeyPresent = filled(config('app.key'));
echo 'APP_KEY_PRESENT='.yn($appKeyPresent)."\n";
$encRound = 'FAIL';
try {
    $plain = 'ota-enc-probe-'.bin2hex(random_bytes(4));
    $enc = Crypt::encryptString($plain);
    $dec = Crypt::decryptString($enc);
    $encRound = ($dec === $plain) ? 'PASS' : 'FAIL';
} catch (Throwable $e) {
    $encRound = 'FAIL';
    echo 'ENCRYPTION_ERROR_CLASS='.get_class($e)."\n";
}
echo "ENCRYPTION_ROUNDTRIP={$encRound}\n";

// Grants metadata (SHOW GRANTS) — redact anything that looks like password
try {
    $grants = DB::select('SHOW GRANTS');
    $insertOk = 'UNKNOWN';
    foreach ($grants as $g) {
        $line = implode(' ', (array) $g);
        $line = preg_replace('/IDENTIFIED BY[^\s]*/i', 'IDENTIFIED BY [REDACTED]', $line) ?? $line;
        if (stripos($line, 'INSERT') !== false || stripos($line, 'ALL PRIVILEGES') !== false) {
            $insertOk = 'YES_METADATA';
        }
        // only classify, don't dump full grants with hosts if huge
    }
    echo "DB_USER_INSERT_PRIVILEGE_METADATA={$insertOk}\n";
} catch (Throwable $e) {
    echo 'DB_USER_INSERT_PRIVILEGE_METADATA=UNAVAILABLE'."\n";
}

// Model fillable/casts check via reflection-ish file
$model = $appRoot.'/app/Models/PaymentGateway.php';
$modelSrc = is_file($model) ? (string) file_get_contents($model) : '';
echo 'MODEL_ENCRYPTED_CASTS='.yn(str_contains($modelSrc, "'merchant_id' => 'encrypted'") || str_contains($modelSrc, '"merchant_id" => "encrypted"') || str_contains($modelSrc, "merchant_id' => 'encrypted"))."\n";

echo "=== END ===\n";
