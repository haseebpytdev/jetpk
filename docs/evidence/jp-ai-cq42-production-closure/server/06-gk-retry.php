#!/usr/bin/env php
<?php
/**
 * CQ42 GK retry + wapis re-probe + thinking-relevant slow turns.
 */
require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AiConversation;
use App\Services\Ai\AiChatOrchestrator;
use Illuminate\Http\Request;

$out = '/tmp/cq42-canary-out';
@mkdir($out, 0775, true);
$orch = $app->make(AiChatOrchestrator::class);

function turn(AiChatOrchestrator $orch, string $message, ?string $cid = null): array
{
    $t0 = microtime(true);
    $params = ['message' => $message];
    if ($cid) {
        $params['conversation_id'] = $cid;
    }
    $request = Request::create('/api/public/ai/chat', 'POST', $params);
    $request->headers->set('User-Agent', 'CQ42-Retry/1.0');
    $request->server->set('REMOTE_ADDR', '127.0.0.1');
    app()->instance('request', $request);
    $resolved = $orch->resolveConversation($request, $cid);
    $payload = $orch->handleChat($resolved['conversation'], $message);
    return [
        'latency_ms' => round((microtime(true) - $t0) * 1000, 1),
        'conversation_id' => $payload['conversation_id'] ?? $resolved['conversation']->public_id,
        'message' => (string) ($payload['message'] ?? ''),
        'meta' => is_array($payload['meta'] ?? null) ? $payload['meta'] : [],
        'state' => $payload['state'] ?? null,
        'mode' => $payload['mode'] ?? null,
    ];
}

function score(array $e): array
{
    $body = mb_strtolower($e['message']);
    $actual = mb_strlen($e['message']) > 40
        && ! str_contains($body, "couldn't produce a reliable")
        && ! str_contains($body, 'could not produce a reliable')
        && ! str_contains($body, 'flight bookings only')
        && ! str_contains($body, 'purpose is to help you with flight');
    return [
        'actual_answer' => $actual,
        'qwen_accept' => ($e['meta']['FINAL_RESPONSE_SOURCE'] ?? '') === 'QWEN_OPEN_DOMAIN'
            || (($e['meta']['OPEN_DOMAIN_ACCEPTED'] ?? '') === 'YES' && ($e['meta']['OPEN_DOMAIN_FALLBACK'] ?? '') === 'NO'),
        'reject' => $e['meta']['OPEN_DOMAIN_REJECT_REASON'] ?? null,
        'src' => $e['meta']['FINAL_RESPONSE_SOURCE'] ?? null,
        'od_attempted' => $e['meta']['OPEN_DOMAIN_ATTEMPTED'] ?? null,
        'SERVER_OPEN_DOMAIN_CATEGORY' => $e['meta']['SERVER_OPEN_DOMAIN_CATEGORY'] ?? null,
        'SEMANTIC_LATENCY_MS' => $e['meta']['SEMANTIC_LATENCY_MS'] ?? null,
        'OPEN_DOMAIN_LATENCY_MS' => $e['meta']['OPEN_DOMAIN_LATENCY_MS'] ?? null,
        'TOTAL_MODEL_LATENCY_MS' => $e['meta']['TOTAL_MODEL_LATENCY_MS'] ?? null,
        'MODEL_CALLS' => $e['meta']['MODEL_CALLS'] ?? null,
    ];
}

$retries = [
    'gk_r_dna' => 'What is DNA?',
    'gk_r_wifi' => 'How does Wi-Fi work?',
    'gk_r_tides' => 'What causes tides?',
    'gk_r_recursion' => 'Explain recursion simply.',
    'gk_r_ram' => 'What is the difference between RAM and storage?',
    'gk_r_gravity' => 'What is gravity?',
    'gk_r_airplanes' => 'Why do airplanes fly?',
    'gk_r_photosynthesis' => 'What is photosynthesis?',
];

foreach ($retries as $key => $msg) {
    $e = turn($orch, $msg);
    $e['checks'] = score($e);
    file_put_contents("$out/$key.json", json_encode($e, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $c = $e['checks'];
    echo "$key | {$e['latency_ms']}ms | src={$c['src']} rej={$c['reject']} actual=".($c['actual_answer']?'Y':'N')." qwen=".($c['qwen_accept']?'Y':'N')." | ".mb_substr(str_replace("\n",' ',$e['message']),0,90).PHP_EOL;
    usleep(500000);
}

// Clean wapis: confirm first then return
$e1 = turn($orch, 'Lahore to Dubai tomorrow for 2 adults');
file_put_contents("$out/wapis_r_leg1.json", json_encode($e1, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "wapis_leg1 | ".mb_substr($e1['message'],0,120).PHP_EOL;
$e2 = turn($orch, 'yes', $e1['conversation_id']);
file_put_contents("$out/wapis_r_confirm.json", json_encode($e2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "wapis_confirm | src=".($e2['meta']['FINAL_RESPONSE_SOURCE']??'')." | ".mb_substr(str_replace("\n",' ',$e2['message']),0,100).PHP_EOL;
// Fresh conversation for wapis-as-return after outbound stated
$e3 = turn($orch, 'Lahore se Dubai jana hai kal, 2 adults');
$e4 = turn($orch, 'Dubai se Lahore wapis', $e3['conversation_id']);
$e4['prior'] = mb_substr($e3['message'], 0, 200);
file_put_contents("$out/wapis_r2.json", json_encode($e4, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "wapis_r2 | intent=".($e4['meta']['SEMANTIC_INTENT']??'')." legs=".($e4['meta']['LEG1']??'').'/'.($e4['meta']['LEG2']??'')." | ".mb_substr(str_replace("\n",' ',$e4['message']),0,140).PHP_EOL;

// Alternate: single-turn reverse
$e5 = turn($orch, 'Dubai se Lahore wapis');
file_put_contents("$out/wapis_fresh.json", json_encode($e5, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "wapis_fresh | ".mb_substr(str_replace("\n",' ',$e5['message']),0,140).PHP_EOL;

echo "DONE\n";
