<?php
/**
 * READ-ONLY forensics: why AbhiPay row may be missing after claimed admin save.
 * No secrets.
 */
declare(strict_types=1);

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== ROW_FORENSICS ===\n";
echo 'PAYMENT_GATEWAYS_TOTAL='.DB::table('payment_gateways')->count()."\n";

if (Schema::hasTable('payment_gateways')) {
    $cols = Schema::getColumnListing('payment_gateways');
    echo 'PG_COLUMNS='.implode(',', $cols)."\n";
    $all = DB::table('payment_gateways')->orderBy('id')->get();
    echo 'PG_ROW_COUNT='.$all->count()."\n";
    foreach ($all as $r) {
        // never print merchant fields
        echo 'PG_ROW id='.$r->id
            .' agency_id='.($r->agency_id ?? 'NULL')
            .' code='.($r->code ?? '')
            .' env='.($r->environment ?? '')
            .' active='.(isset($r->is_active) ? ((int) $r->is_active) : '?')
            .' merchant_id_len='.(isset($r->merchant_id) && $r->merchant_id !== null && $r->merchant_id !== '' ? 'SET' : 'EMPTY')
            .' secret_len='.(isset($r->merchant_secret_key) && $r->merchant_secret_key !== null && $r->merchant_secret_key !== '' ? 'SET' : 'EMPTY')
            .' base_host='.(parse_url((string) ($r->base_url ?? ''), PHP_URL_HOST) ?: '')
            .' cb_host='.(parse_url((string) ($r->callback_url ?? ''), PHP_URL_HOST) ?: '')
            .' updated_at='.($r->updated_at ?? '')
            ."\n";
    }
}

if (Schema::hasTable('audit_logs')) {
    $audits = DB::table('audit_logs')
        ->where('action', 'like', '%abhipay%')
        ->orWhere('action', 'like', '%payment_gateway%')
        ->orderByDesc('id')
        ->limit(20)
        ->get(['id', 'agency_id', 'user_id', 'action', 'auditable_type', 'auditable_id', 'created_at']);
    echo 'AUDIT_ABHIPAY_COUNT_SHOWN='.$audits->count()."\n";
    foreach ($audits as $a) {
        echo 'AUDIT id='.$a->id
            .' agency_id='.($a->agency_id ?? 'NULL')
            .' user_id='.($a->user_id ?? 'NULL')
            .' action='.$a->action
            .' auditable_id='.($a->auditable_id ?? '')
            .' at='.$a->created_at
            ."\n";
    }

    $recent = DB::table('audit_logs')
        ->where('created_at', '>=', now()->subHours(6))
        ->orderByDesc('id')
        ->limit(30)
        ->get(['id', 'agency_id', 'action', 'created_at']);
    echo "RECENT_6H_AUDITS:\n";
    foreach ($recent as $a) {
        echo '  id='.$a->id.' agency='.($a->agency_id ?? 'NULL').' action='.$a->action.' at='.$a->created_at."\n";
    }
}

// Check if settings table might hold alternate gateway config
foreach (['agency_settings', 'settings', 'client_settings'] as $t) {
    if (Schema::hasTable($t)) {
        echo "TABLE_EXISTS={$t}\n";
    }
}

echo 'DB_DATABASE='.(string) config('database.connections.'.config('database.default').'.database')."\n";
echo 'APP_ENV='.(string) config('app.env')."\n";
echo 'APP_URL='.(string) config('app.url')."\n";
echo "=== END ===\n";
