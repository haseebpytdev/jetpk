#!/usr/bin/env php
<?php
// CQ41 remaining live probes via in-process orchestrator (no HTTP CSRF).
require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AiConversation;
use App\Services\Ai\AiChatOrchestrator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

$out = '/tmp/cq41-canary-out';
@mkdir($out, 0775, true);

$cases = [
    '18b_news' => 'What happened in the news today?',
    '18c_btc' => "What is Bitcoin's price right now?",
    '18d_aapl' => 'What is the current stock price of Apple?',
    '19_fare' => 'What is the latest Emirates fare Lahore to Dubai?',
    '20_booking' => 'Check my booking please',
    '20b_see' => 'I want to see my booking',
    '20c_status' => 'Booking status',
    '21_handoff' => 'Talk to support',
];

$orch = $app->make(AiChatOrchestrator::class);

foreach ($cases as $key => $message) {
    $t0 = microtime(true);
    try {
        $request = Request::create('/api/public/ai/chat', 'POST', [
            'message' => $message,
        ]);
        $request->headers->set('User-Agent', 'CQ41-InProcess/1.0');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $app->instance('request', $request);

        $resolved = $orch->resolveConversation($request, null);
        /** @var AiConversation $conversation */
        $conversation = $resolved['conversation'];
        $payload = $orch->handleChat($conversation, $message);
        $ms = (microtime(true) - $t0) * 1000;
        $entry = [
            'http_status' => 200,
            'latency_ms' => round($ms, 1),
            'conversation_id' => $payload['conversation_id'] ?? $conversation->public_id,
            'message' => mb_substr((string) ($payload['message'] ?? ''), 0, 1200),
            'meta' => $payload['meta'] ?? [],
            'actions' => $payload['actions'] ?? null,
            'state' => $payload['state'] ?? $conversation->state,
            'mode' => 'in_process_orchestrator',
        ];
        file_put_contents("$out/$key.json", json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $src = $entry['meta']['FINAL_RESPONSE_SOURCE'] ?? '';
        echo "$key OK {$entry['latency_ms']} $src " . mb_substr($entry['message'], 0, 100) . PHP_EOL;
    } catch (Throwable $e) {
        $ms = (microtime(true) - $t0) * 1000;
        file_put_contents("$out/$key.json", json_encode([
            'error' => $e->getMessage(),
            'latency_ms' => round($ms, 1),
        ], JSON_PRETTY_PRINT));
        echo "$key ERR {$e->getMessage()}" . PHP_EOL;
    }
    usleep(500000);
}

echo "DONE\n";
