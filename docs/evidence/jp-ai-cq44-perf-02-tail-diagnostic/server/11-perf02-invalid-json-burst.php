#!/usr/bin/env php
<?php
/**
 * Supplemental burst to reproduce invalid_json on CURRENT + explicit.
 * Uses lsphp83. Read-only chat + raw HTTP shape capture.
 */
require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Ai\AiChatOrchestrator;
use App\Services\Ai\FlightSearchConfirmationGate;
use Illuminate\Http\Request;

$outRoot = getenv('CQ44_OUT') ?: '/tmp/cq44-perf02-out';
$EXPECTED = 'a0e616747a95d632f270a6edafc3f6e8d9230fca';
$sha = trim((string) @file_get_contents('/home/pkjetp/jetpk_app/.jetpk-runtime-sha'));
if ($sha !== $EXPECTED) {
    fwrite(STDERR, "ABORT sha\n");
    exit(2);
}

$orch = app(AiChatOrchestrator::class);
$confirm = app(FlightSearchConfirmationGate::class);

function vtok(string $p): string
{
    return substr(preg_replace('/[^A-Za-z0-9]/', '', $p).bin2hex(random_bytes(12)), 0, 48);
}

function one(AiChatOrchestrator $orch, string $msg): array
{
    $t0 = microtime(true);
    $visitor = vtok('burst');
    $request = Request::create('/api/public/ai/chat', 'POST', ['message' => $msg]);
    $request->headers->set('User-Agent', 'CQ44-PERF02-BURST/1.0');
    $request->cookies->set('jp_ai_vid', $visitor);
    $request->headers->set('Cookie', 'jp_ai_vid='.$visitor);
    $request->server->set('REMOTE_ADDR', '127.0.0.1');
    app()->instance('request', $request);
    $resolved = $orch->resolveConversation($request, null);
    $c = $resolved['conversation'];
    $payload = $orch->handleChat($c, $msg);
    $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
    $ms = (int) round((microtime(true) - $t0) * 1000);

    return [
        'message' => $msg,
        'latency_ms' => $ms,
        'SEMANTIC_LATENCY_MS' => (int) ($meta['SEMANTIC_LATENCY_MS'] ?? 0),
        'MODEL_CALLS' => (int) ($meta['MODEL_CALLS'] ?? 0),
        'SEMANTIC_FALLBACK_REASON' => (string) ($meta['SEMANTIC_FALLBACK_REASON'] ?? ''),
        'FINAL_RESPONSE_SOURCE' => (string) ($meta['FINAL_RESPONSE_SOURCE'] ?? ''),
        'message_out' => mb_substr(str_replace("\n", ' ', (string) ($payload['message'] ?? '')), 0, 160),
    ];
}

$msgs = [
    "What is Bitcoin's price right now?",
    'Now Islamabad to Dubai next Monday',
];
$rows = [];
foreach ($msgs as $msg) {
    for ($i = 0; $i < 5; $i++) {
        $row = one($orch, $msg);
        $rows[] = $row;
        echo "BURST {$msg} #$i fb={$row['SEMANTIC_FALLBACK_REASON']} total={$row['latency_ms']} sem={$row['SEMANTIC_LATENCY_MS']}\n";
        usleep(1200 * 1000);
    }
}

file_put_contents($outRoot.'/05b-invalid-json-burst.json', json_encode($rows, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
$inv = array_values(array_filter($rows, fn ($r) => ($r['SEMANTIC_FALLBACK_REASON'] ?? '') === 'invalid_json'));
echo 'INVALID_JSON_COUNT='.count($inv).'/'.count($rows)."\n";
