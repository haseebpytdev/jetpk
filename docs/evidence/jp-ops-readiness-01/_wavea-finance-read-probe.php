<?php

/** Read-only wallet/payment uniqueness probe. No mutations. */
$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dupWallets = [];
if (Schema::hasTable('agent_wallets')) {
    $dupWallets = DB::table('agent_wallets')
        ->select('agency_id', DB::raw('count(*) as c'))
        ->groupBy('agency_id')
        ->havingRaw('count(*) > 1')
        ->orderByDesc('c')
        ->limit(20)
        ->get()
        ->map(fn ($r) => ['agency_id' => (int) $r->agency_id, 'wallet_count' => (int) $r->c])
        ->all();
}

$indexes = [];
foreach (['booking_payments', 'payment_transactions', 'agent_wallets', 'agent_deposit_requests'] as $table) {
    if (! Schema::hasTable($table)) {
        $indexes[$table] = 'MISSING_TABLE';
        continue;
    }
    $indexes[$table] = DB::select('SHOW INDEX FROM `'.$table.'`');
}

$indexSummary = [];
foreach ($indexes as $table => $rows) {
    if (! is_array($rows)) {
        $indexSummary[$table] = $rows;
        continue;
    }
    $uniq = [];
    foreach ($rows as $row) {
        $arr = (array) $row;
        if ((int) ($arr['Non_unique'] ?? 1) === 0) {
            $uniq[] = ($arr['Key_name'] ?? '?').':'.($arr['Column_name'] ?? '?');
        }
    }
    $indexSummary[$table] = array_values(array_unique($uniq));
}

echo json_encode([
    'PROBED_AT_UTC' => gmdate('c'),
    'DUPLICATE_AGENCY_WALLET_GROUPS' => count($dupWallets),
    'DUPLICATE_AGENCY_WALLET_SAMPLE' => $dupWallets,
    'UNIQUE_INDEX_SUMMARY' => $indexSummary,
    'NOTE' => 'Read-only; no wallet/payment mutations',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
