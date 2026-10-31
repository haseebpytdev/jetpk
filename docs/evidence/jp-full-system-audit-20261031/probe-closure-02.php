<?php

/**
 * JP-AUDIT-CLOSURE-02 production residual probes (safe fields only).
 */

require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SupplierConnection;
use App\Support\Suppliers\RetiredSupplierProviders;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$mode = $argv[1] ?? 'audit';

function mask(?string $v): string
{
    if ($v === null || $v === '') {
        return 'EMPTY';
    }

    return 'SET_LEN_'.strlen($v);
}

echo "MODE={$mode}\n";

echo "=== SUPPLIER_CONNECTIONS ===\n";
$rows = SupplierConnection::query()->orderBy('id')->get();
foreach ($rows as $r) {
    $raw = $r->getAttributes()['provider'] ?? null;
    $cast = $r->provider;
    $castKind = is_object($cast) ? 'enum' : gettype($cast);
    $castVal = is_object($cast) ? $cast->value : (string) $cast;
    $statusRaw = (string) ($r->getAttributes()['status'] ?? '');
    $retired = RetiredSupplierProviders::isRetired(is_string($raw) ? (string) $raw : $castVal) ? 'Y' : 'N';
    $creds = $r->getAttributes()['credentials'] ?? null;
    $settings = $r->getAttributes()['settings'] ?? null;
    $anom = [];
    if ($retired === 'Y') {
        $anom[] = 'retired_provider';
    }
    if ($castKind === 'string') {
        $anom[] = 'string_cast';
    }
    if ((bool) $r->is_active && strtolower($statusRaw) === 'inactive') {
        $anom[] = 'is_active_1_status_inactive';
    }
    if (! (bool) $r->is_active && strtolower($statusRaw) === 'active') {
        $anom[] = 'is_active_0_status_active';
    }
    if ($castVal === 'smtp' || $raw === 'smtp') {
        $anom[] = 'smtp_mail_row';
    }
    $eligible = method_exists($r, 'isEligibleForSupplierSearch')
        ? ($r->isEligibleForSupplierSearch() ? 'Y' : 'N')
        : '?';
    echo implode('|', [
        'CONN',
        (string) $r->id,
        (string) $r->agency_id,
        (string) $raw,
        $castKind,
        $castVal,
        $statusRaw,
        $r->is_active ? '1' : '0',
        (string) ($r->getAttributes()['environment'] ?? ''),
        $retired,
        $eligible,
        filled($creds) ? 'YES' : 'NO',
        filled($settings) ? 'YES' : 'NO',
        (string) ($r->last_test_status ?? ''),
        (string) ($r->name ?? ''),
        $anom === [] ? 'ok' : implode(',', $anom),
    ]).PHP_EOL;
}

echo "=== DUPLICATE_ACTIVE ===\n";
$dup = DB::table('supplier_connections')
    ->select('agency_id', 'provider', DB::raw('COUNT(*) as c'), DB::raw('GROUP_CONCAT(id) as ids'))
    ->where(function ($q) {
        $q->where('is_active', 1)->orWhere('status', 'active');
    })
    ->groupBy('agency_id', 'provider')
    ->having('c', '>', 1)
    ->get();
foreach ($dup as $d) {
    echo "DUP|{$d->agency_id}|{$d->provider}|{$d->c}|{$d->ids}\n";
}
if ($dup->isEmpty()) {
    echo "DUP=none\n";
}

echo "=== MAIL_CONFIG ===\n";
$mailDefault = (string) config('mail.default');
$mailer = config("mail.mailers.{$mailDefault}");
echo 'MAIL_MAILER_ENV='.mask(env('MAIL_MAILER') !== null ? (string) env('MAIL_MAILER') : null)."\n";
echo 'mail.default='.$mailDefault."\n";
echo 'mailer.transport='.(is_array($mailer) ? (string) ($mailer['transport'] ?? '') : '')."\n";
echo 'mail.host_presence='.(is_array($mailer) && filled($mailer['host'] ?? null) ? 'YES' : 'NO')."\n";
echo 'mail.username_presence='.(is_array($mailer) && filled($mailer['username'] ?? null) ? 'YES' : 'NO')."\n";
echo 'mail.password_presence='.(is_array($mailer) && filled($mailer['password'] ?? null) ? 'YES' : 'NO')."\n";
echo 'mail.from.address_presence='.(filled(config('mail.from.address')) ? 'YES' : 'NO')."\n";
echo 'mail.from.name_presence='.(filled(config('mail.from.name')) ? 'YES' : 'NO')."\n";

if (class_exists(\App\Models\AgencyCommunicationSetting::class) && Schema::hasTable('agency_communication_settings')) {
    $acs = DB::table('agency_communication_settings')->count();
    echo "AgencyCommunicationSetting_rows={$acs}\n";
    $sample = DB::table('agency_communication_settings')->orderBy('id')->limit(5)->get();
    foreach ($sample as $s) {
        $arr = (array) $s;
        $safe = [];
        foreach ($arr as $k => $v) {
            if (preg_match('/pass|secret|token|key|credential/i', (string) $k)) {
                $safe[$k] = filled($v) ? 'SET' : 'EMPTY';
            } elseif (preg_match('/host|user|mailer|from|driver|channel|enabled|agency/i', (string) $k)) {
                $safe[$k] = is_bool($v) ? ($v ? '1' : '0') : (is_scalar($v) ? (string) $v : gettype($v));
            }
        }
        echo 'ACS|'.json_encode($safe).PHP_EOL;
    }
} else {
    echo "AgencyCommunicationSetting=MISSING_OR_NO_TABLE\n";
}

echo "=== SMTP_CODE_DEPENDENCY ===\n";
echo "SMTP_SUPPLIER_ROW_REQUIRED_FOR_MAIL=NO\n";
echo "NOTE=mail resolves via config/mail.php + MAIL_* env; smtp is RetiredSupplierProviders\n";

if ($mode === 'deactivate-smtp-5') {
    echo "=== DEACTIVATE_SMTP_5 ===\n";
    $row = SupplierConnection::query()->find(5);
    if ($row === null) {
        echo "SMTP5=MISSING\n";
    } else {
        echo 'BEFORE|'.implode('|', [
            (string) $row->id,
            (string) ($row->getAttributes()['provider'] ?? ''),
            (string) ($row->getAttributes()['status'] ?? ''),
            $row->is_active ? '1' : '0',
            (string) ($row->name ?? ''),
        ]).PHP_EOL;
        // Smallest safe cleanup: flags only
        DB::table('supplier_connections')->where('id', 5)->update([
            'is_active' => 0,
            'status' => 'inactive',
            'updated_at' => now(),
        ]);
        $row = SupplierConnection::query()->find(5);
        echo 'AFTER|'.implode('|', [
            (string) $row->id,
            (string) ($row->getAttributes()['provider'] ?? ''),
            (string) ($row->getAttributes()['status'] ?? ''),
            $row->is_active ? '1' : '0',
            filled($row->getAttributes()['credentials'] ?? null) ? 'creds=YES' : 'creds=NO',
            filled($row->getAttributes()['settings'] ?? null) ? 'settings=YES' : 'settings=NO',
        ]).PHP_EOL;
    }
}

echo "=== GROUP_INVENTORY ===\n";
if (! Schema::hasTable('group_inventories')) {
    echo "group_inventories=MISSING\n";
} else {
    $cols = Schema::getColumnListing('group_inventories');
    echo 'COLUMNS='.implode(',', $cols).PHP_EOL;
    echo 'TOTAL_ROWS='.DB::table('group_inventories')->count().PHP_EOL;
    $today = now()->toDateString();
    $departCol = collect(['departure_date', 'depart_date', 'travel_date', 'flight_date', 'departure_at'])
        ->first(fn ($c) => in_array($c, $cols, true));
    $statusCol = collect(['status', 'package_status', 'state', 'availability_status'])
        ->first(fn ($c) => in_array($c, $cols, true));
    $supplierCol = collect(['supplier_provider', 'provider', 'supplier_key', 'supplier', 'source_provider'])
        ->first(fn ($c) => in_array($c, $cols, true));
    $updatedCol = collect(['last_synced_at', 'synced_at', 'updated_at'])
        ->first(fn ($c) => in_array($c, $cols, true));
    if ($departCol) {
        echo 'ACTIVE_FUTURE_BY_DATE='.DB::table('group_inventories')->whereDate($departCol, '>=', $today)->count().PHP_EOL;
        echo 'EXPIRED_BY_DATE='.DB::table('group_inventories')->whereDate($departCol, '<', $today)->count().PHP_EOL;
    }
    if ($statusCol) {
        foreach (DB::table('group_inventories')->select($statusCol, DB::raw('COUNT(*) as c'))->groupBy($statusCol)->get() as $s) {
            echo 'STATUS|'.$s->{$statusCol}.'|'.$s->c.PHP_EOL;
        }
    }
    if ($supplierCol) {
        foreach (DB::table('group_inventories')->select($supplierCol, DB::raw('COUNT(*) as c'))->groupBy($supplierCol)->get() as $s) {
            echo 'SUPPLIER|'.$s->{$supplierCol}.'|'.$s->c.PHP_EOL;
        }
    }
    if ($updatedCol) {
        echo "LAST_INVENTORY_{$updatedCol}=".DB::table('group_inventories')->max($updatedCol).PHP_EOL;
    }
}

echo "=== FEATURED / HOMEPAGE TABLES ===\n";
foreach (['homepage_featured_fares', 'homepage_trending_routes', 'homepage_sections', 'cms_sections', 'featured_deals'] as $t) {
    if (Schema::hasTable($t)) {
        echo "TABLE={$t} COUNT=".DB::table($t)->count().PHP_EOL;
        $c = Schema::getColumnListing($t);
        echo "COLS_{$t}=".implode(',', array_slice($c, 0, 40)).PHP_EOL;
    }
}

echo "=== AI_ENV ===\n";
foreach ([
    'OTA_AI_ASSISTANT_MODE',
    'OTA_AI_ASSISTANT_ENABLED',
    'OTA_AI_LAB_ADAPTER_ENABLED',
    'OTA_AI_SEMANTIC_PLANNER_ENABLED',
    'OTA_AI_SEMANTIC_COMPOSER_ENABLED',
    'ONE_API_LIVE_SEARCH_ENABLED',
    'ONE_API_LIVE_BOOKING_ENABLED',
] as $k) {
    $v = env($k);
    echo $k.'='.(($v === null || $v === '') ? 'UNSET' : (string) $v).PHP_EOL;
}

echo "DONE\n";
