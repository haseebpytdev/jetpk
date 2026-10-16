<?php
/**
 * OPS-DEF-0017 config-connectivity — READ-ONLY agency selection probe.
 * No secrets. No mutations.
 */
declare(strict_types=1);

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Agency;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\BookingPayment;
use App\Services\Payments\PaymentTransactionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function yn($v): string
{
    return $v ? 'YES' : 'NO';
}

echo "=== OPS_DEF_0017_AGENCY_SELECT ===\n";
echo 'RUNTIME_SHA='.trim((string) @file_get_contents($appRoot.'/.jetpk-runtime-sha'))."\n";
echo 'FRONTEND_BUILD_ID='.trim((string) @file_get_contents($appRoot.'/frontend/.next/BUILD_ID'))."\n";
echo 'FAILED_JOBS_PRE='.(int) DB::table('failed_jobs')->count()."\n";
echo 'PENDING_DEFAULT_PRE='.(int) DB::table('jobs')->where('queue', 'default')->count()."\n";
echo 'PAYMENT_GATEWAY_ROWS_PRE='.PaymentGateway::query()->count()."\n";
echo 'ABHIPAY_ROWS_PRE='.PaymentGateway::query()->where('code', 'abhipay')->count()."\n";
echo 'ABHIPAY_TRANSACTIONS_PRE='.PaymentTransaction::query()->where('gateway', 'abhipay')->count()."\n";
echo 'PAYMENT_TRANSACTIONS_PRE='.PaymentTransaction::query()->count()."\n";
echo 'BOOKING_PAYMENTS_PRE='.BookingPayment::query()->count()."\n";

$cols = Schema::getColumnListing('agencies');
echo 'AGENCY_COLUMNS='.implode(',', $cols)."\n";

$agencies = Agency::query()->orderBy('id')->get();
echo 'AGENCY_COUNT='.$agencies->count()."\n";

foreach ($agencies as $agency) {
    $id = (int) $agency->id;
    $name = (string) ($agency->name ?? $agency->display_name ?? 'agency-'.$id);
    // sanitize name length
    $name = substr(preg_replace('/\s+/', ' ', $name) ?? $name, 0, 80);

    $status = '';
    foreach (['status', 'is_active', 'active', 'state'] as $f) {
        if (in_array($f, $cols, true)) {
            $val = $agency->{$f};
            $status = is_bool($val) ? ($val ? 'active' : 'inactive') : (string) $val;
            break;
        }
    }

    $userCount = 0;
    try {
        $userCount = (int) DB::table('users')->where('current_agency_id', $id)->count();
    } catch (Throwable $e) {
        try {
            $userCount = (int) DB::table('users')->where('agency_id', $id)->count();
        } catch (Throwable $e2) {
            $userCount = -1;
        }
    }

    $bookingCount = (int) DB::table('bookings')->where('agency_id', $id)->count();
    $recentBookingCount = (int) DB::table('bookings')
        ->where('agency_id', $id)
        ->where('created_at', '>=', now()->subDays(30))
        ->count();

    $agentCount = 0;
    try {
        $agentCount = (int) DB::table('agents')->where('agency_id', $id)->count();
    } catch (Throwable $e) {
        $agentCount = -1;
    }

    $nullAgencyBookings = 0; // reported once below

    $svc = app(PaymentTransactionService::class);
    $gw = $svc->activeAbhiPayGateway($id);

    echo "--- AGENCY ---\n";
    echo "AGENCY_ID={$id}\n";
    echo "DISPLAY_NAME={$name}\n";
    echo "STATUS={$status}\n";
    echo "ACTIVE_USER_COUNT={$userCount}\n";
    echo "BOOKING_COUNT={$bookingCount}\n";
    echo "RECENT_BOOKING_COUNT={$recentBookingCount}\n";
    echo "AGENT_COUNT={$agentCount}\n";
    echo 'CHECKOUT_AVAILABLE_PRE='.yn($gw !== null)."\n";
}

$nullAgencyBookings = (int) DB::table('bookings')->whereNull('agency_id')->count();
$publicish = (int) DB::table('bookings')
    ->whereNull('agent_id')
    ->orWhere(function ($q) {
        // keep simple: count by channel if column exists
    })
    ->count();

echo "BOOKINGS_NULL_AGENCY={$nullAgencyBookings}\n";

// booking distribution by agency_id
$rows = DB::table('bookings')
    ->select('agency_id', DB::raw('COUNT(*) as c'), DB::raw('MAX(created_at) as last_at'))
    ->groupBy('agency_id')
    ->orderByDesc('c')
    ->get();
foreach ($rows as $r) {
    echo 'BOOKING_DIST agency_id='.($r->agency_id ?? 'NULL').' count='.$r->c.' last='.$r->last_at."\n";
}

// public/B2C hint: bookings without agent_id
if (Schema::hasColumn('bookings', 'agent_id')) {
    $b2c = DB::table('bookings')
        ->select('agency_id', DB::raw('COUNT(*) as c'))
        ->whereNull('agent_id')
        ->groupBy('agency_id')
        ->orderByDesc('c')
        ->get();
    foreach ($b2c as $r) {
        echo 'B2C_NULL_AGENT_DIST agency_id='.($r->agency_id ?? 'NULL').' count='.$r->c."\n";
    }
}

if (Schema::hasColumn('bookings', 'channel') || Schema::hasColumn('bookings', 'source')) {
    $col = Schema::hasColumn('bookings', 'channel') ? 'channel' : 'source';
    $ch = DB::table('bookings')
        ->select($col, 'agency_id', DB::raw('COUNT(*) as c'))
        ->groupBy($col, 'agency_id')
        ->orderByDesc('c')
        ->limit(20)
        ->get();
    foreach ($ch as $r) {
        echo 'CHANNEL_DIST '.$col.'='.$r->{$col}.' agency_id='.($r->agency_id ?? 'NULL').' count='.$r->c."\n";
    }
}

// branding / client profile hints
if (Schema::hasTable('agencies')) {
    foreach (['slug', 'code', 'domain', 'is_primary', 'is_default', 'type'] as $f) {
        if (in_array($f, $cols, true)) {
            foreach ($agencies as $agency) {
                $v = $agency->{$f};
                if ($v !== null && $v !== '') {
                    echo "AGENCY_META id={$agency->id} {$f}=".substr((string) $v, 0, 60)."\n";
                }
            }
        }
    }
}

echo "=== END ===\n";
