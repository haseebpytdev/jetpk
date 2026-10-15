<?php
declare(strict_types=1);

/**
 * READ-ONLY Wave A auth inventory + health. Never prints passwords/tokens.
 */
$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Enums\AgencyRole;
use App\Models\Agency;
use App\Models\AgencyUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$runtime = is_file($appRoot.'/.jetpk-runtime-sha')
    ? trim((string) file_get_contents($appRoot.'/.jetpk-runtime-sha'))
    : 'MISSING';
$build = is_file($appRoot.'/dashboard/.next/BUILD_ID')
    ? trim((string) file_get_contents($appRoot.'/dashboard/.next/BUILD_ID'))
    : (is_file($appRoot.'/storage/app/dashboard-build-id.txt')
        ? trim((string) file_get_contents($appRoot.'/storage/app/dashboard-build-id.txt'))
        : 'MISSING');

$roles = [
    'admin' => ['marker' => 'jp-dash-03-qa-admin', 'env' => 'JP_DASH_03_QA_ADMIN_PASSWORD'],
    'staff' => ['marker' => 'jp-dash-03-qa-staff', 'env' => 'JP_DASH_03_QA_STAFF_PASSWORD'],
    'agent' => ['marker' => 'jp-dash-03-qa-agent', 'env' => 'JP_DASH_03_QA_AGENT_PASSWORD'],
    'agent_staff' => ['marker' => 'jp-dash-03-qa-agent-staff', 'env' => 'JP_DASH_03_QA_AGENT_STAFF_PASSWORD'],
    'customer' => ['marker' => 'jp-dash-03-qa-customer', 'env' => 'JP_DASH_03_QA_CUSTOMER_PASSWORD'],
];

$out = [
    'PROBED_AT_UTC' => now()->utc()->toIso8601String(),
    'RUNTIME_SHA' => $runtime,
    'DASHBOARD_BUILD_ID' => $build,
    'FAILED_JOBS' => (int) DB::table('failed_jobs')->count(),
    'PENDING_DEFAULT' => (int) DB::table('jobs')->where('queue', 'default')->count(),
    'PENDING_ALL' => (int) DB::table('jobs')->count(),
    'PENDING_BY_QUEUE' => DB::table('jobs')->selectRaw('queue, count(*) as c')->groupBy('queue')->pluck('c', 'queue'),
];

$agency = Agency::query()->where('slug', 'jetpk-production-qa')->first();
$out['QA_AGENCY_EXISTS'] = $agency !== null ? 'YES' : 'NO';
$out['QA_AGENCY_ID'] = $agency?->id;

$inventory = [];
foreach ($roles as $role => $def) {
    $user = User::query()->where('username', $def['marker'])->first();
    $envPresent = env($def['env']) !== null && (string) env($def['env']) !== '';
    $membership = null;
    $agencyRole = null;
    if ($user && $agency) {
        $membership = AgencyUser::query()
            ->where('user_id', $user->id)
            ->where('agency_id', $agency->id)
            ->first();
        $agencyRole = $membership?->role?->value ?? $membership?->role;
    }
    $inventory[] = [
        'QA_ROLE' => $role,
        'ACCOUNT_MARKER' => $def['marker'],
        'ACCOUNT_EXISTS' => $user ? 'YES' : 'NO',
        'USER_ID' => $user?->id,
        'STATUS' => $user?->status?->value,
        'ACCOUNT_TYPE' => $user?->account_type?->value,
        'EMAIL_VERIFIED' => $user?->email_verified_at ? 'YES' : 'NO',
        'MUST_CHANGE_PASSWORD' => $user?->must_change_password ? 'YES' : 'NO',
        'PASSWORD_SOURCE_AVAILABLE' => $envPresent ? 'YES' : 'NO',
        'PASSWORD_ENV_KEY' => $def['env'],
        'AGENCY_MEMBERSHIP' => $membership ? 'YES' : 'NO',
        'AGENCY_ROLE' => $agencyRole,
        'SAFE_TO_USE' => ($user && $envPresent && ! $user->must_change_password) ? 'YES' : 'PARTIAL',
    ];
}
$out['QA_INVENTORY'] = $inventory;

// Extra agency roles under QA agency (manager/accountant/viewer etc.)
$extra = [];
if ($agency) {
    $rows = AgencyUser::query()
        ->where('agency_id', $agency->id)
        ->with('user:id,username,account_type,status')
        ->get();
    foreach ($rows as $row) {
        $extra[] = [
            'user_id' => $row->user_id,
            'username' => $row->user?->username,
            'role' => $row->role?->value ?? (string) $row->role,
            'account_type' => $row->user?->account_type?->value,
        ];
    }
}
$out['QA_AGENCY_MEMBERSHIPS'] = $extra;
$out['AGENCY_ROLE_ENUM'] = array_map(static fn ($c) => $c->value, AgencyRole::cases());

// OPS-DEF-0021 sample
$out['COMM_LOGS_QUEUED_24H'] = (int) DB::table('communication_logs')
    ->where('status', 'queued')
    ->where('created_at', '>=', now()->subDay())
    ->count();
$out['COMM_LOGS_QUEUED_TOTAL'] = (int) DB::table('communication_logs')->where('status', 'queued')->count();

// Supplier flags read-only (config/DB if available)
try {
    $out['SUPPLIER_FLAGS'] = [
        'SABRE_BOOKING_ENABLED' => (bool) config('suppliers.sabre.booking_enabled', config('services.sabre.booking_enabled')),
        'NOTE' => 'raw config probe; refined in phase 13',
    ];
} catch (Throwable $e) {
    $out['SUPPLIER_FLAGS'] = ['ERROR' => 'config_probe_failed'];
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
