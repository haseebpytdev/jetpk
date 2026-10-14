<?php
declare(strict_types=1);
/** READ-ONLY: report presence of QA password keys in .env without printing values. */
$envFile = '/home/pkjetp/jetpk_app/.env';
$keys = [
    'JP_DASH_03_QA_ADMIN_PASSWORD',
    'JP_DASH_03_QA_STAFF_PASSWORD',
    'JP_DASH_03_QA_AGENT_PASSWORD',
    'JP_DASH_03_QA_AGENT_STAFF_PASSWORD',
    'JP_DASH_03_QA_CUSTOMER_PASSWORD',
];
$lines = file($envFile, FILE_IGNORE_NEW_LINES) ?: [];
$found = [];
foreach ($keys as $key) {
    $present = false;
    $nonEmpty = false;
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), $key.'=')) {
            $present = true;
            $val = substr(trim($line), strlen($key) + 1);
            $val = trim($val, " \t\"'");
            $nonEmpty = $val !== '';
            break;
        }
    }
    $found[] = [
        'KEY' => $key,
        'PRESENT_IN_DOTENV' => $present ? 'YES' : 'NO',
        'NON_EMPTY' => $nonEmpty ? 'YES' : 'NO',
    ];
}

// FINAL-11 QA users agency_role column if exists
require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AgencyUser;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

$finalUsers = User::query()
    ->where('username', 'like', 'jp-final-11-qa-%')
    ->get(['id', 'username', 'account_type', 'status', 'meta']);

$details = [];
foreach ($finalUsers as $u) {
    $au = AgencyUser::query()->where('user_id', $u->id)->first();
    $meta = is_array($u->meta) ? $u->meta : [];
    $details[] = [
        'username' => $u->username,
        'account_type' => $u->account_type?->value,
        'status' => $u->status?->value,
        'agency_user_role' => $au?->role,
        'agency_role_col' => $au?->agency_role ?? null,
        'has_agency_role_attr' => isset($au?->agency_role),
        'meta_agency_role' => $meta['agency_role'] ?? null,
        'agent_permissions_count' => isset($meta['agent_permissions']) && is_array($meta['agent_permissions'])
            ? count($meta['agent_permissions']) : 0,
        'staff_permissions_count' => isset($meta['staff_permissions']) && is_array($meta['staff_permissions'])
            ? count($meta['staff_permissions']) : 0,
    ];
}

$cols = Schema::hasTable('agency_user')
    ? Schema::getColumnListing('agency_user')
    : (Schema::hasTable('agency_users') ? Schema::getColumnListing('agency_users') : []);

echo json_encode([
    'PASSWORD_KEYS' => $found,
    'AGENCY_USER_COLUMNS' => $cols,
    'FINAL11_QA_USERS' => $details,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
