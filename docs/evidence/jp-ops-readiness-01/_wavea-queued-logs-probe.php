<?php

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('communication_logs')
    ->where('status', 'queued')
    ->where('created_at', '>=', now()->subDay())
    ->orderByDesc('id')
    ->limit(20)
    ->get(['id', 'event', 'channel', 'created_at', 'updated_at', 'agency_id', 'booking_id']);

$out = [];
foreach ($rows as $r) {
    $out[] = [
        'id' => (int) $r->id,
        'event' => (string) $r->event,
        'channel' => (string) $r->channel,
        'created_at' => (string) $r->created_at,
        'updated_at' => (string) $r->updated_at,
        'has_booking' => $r->booking_id !== null,
        // no recipient PII
    ];
}

echo json_encode([
    'queued_24h_sample' => $out,
    'queued_24h_total' => (int) DB::table('communication_logs')->where('status', 'queued')->where('created_at', '>=', now()->subDay())->count(),
    'jobs_pending_all' => (int) DB::table('jobs')->count(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
