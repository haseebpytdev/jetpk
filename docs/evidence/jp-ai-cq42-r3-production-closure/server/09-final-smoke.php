#!/usr/bin/env php
<?php
require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Ai\AiChatOrchestrator;
use Illuminate\Http\Request;

$out = getenv('CQ42R3_SMOKE_OUT') ?: '/tmp/cq42r3-final-smoke';
@mkdir($out, 0775, true);
$orch = app(AiChatOrchestrator::class);
$visitor = 'cq42r3finalsmoke'.bin2hex(random_bytes(12));

function t(AiChatOrchestrator $orch, string $visitor, string $m, ?string $cid = null): array
{
    $p = ['message' => $m];
    if ($cid) {
        $p['conversation_id'] = $cid;
    }
    $r = Request::create('/api/public/ai/chat', 'POST', $p);
    $r->headers->set('User-Agent', 'CQ42-R3-FinalSmoke/1');
    $r->headers->set('Cookie', 'jp_ai_vid='.$visitor);
    $r->cookies->set('jp_ai_vid', $visitor);
    $r->server->set('REMOTE_ADDR', '127.0.0.1');
    app()->instance('request', $r);
    $res = $orch->resolveConversation($r, $cid);
    $payload = $orch->handleChat($res['conversation'], $m);
    $payload['_pid'] = $res['conversation']->public_id;

    return $payload;
}

function row(string $out, string $k, array $o): void
{
    $meta = is_array($o['meta'] ?? null) ? $o['meta'] : [];
    $intent = is_array($o['intent'] ?? null) ? $o['intent'] : [];
    $body = (string) ($o['message'] ?? '');
    $data = [
        'status' => $o['status'] ?? null,
        'state' => $o['state'] ?? null,
        'confirm' => (bool) ($o['requires_confirmation'] ?? false) || (bool) ($meta['CONFIRMATION_REQUIRED'] ?? false),
        'route' => $meta['SERVER_SINGLE_ROUTE'] ?? null,
        'override' => $meta['SERVER_ROUTE_OVERRIDES_QWEN_DOMAIN'] ?? null,
        'search' => (int) ($meta['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0),
        'calls' => $meta['GENERAL_MODEL_CALLS'] ?? $meta['MODEL_CALLS'] ?? null,
        'src' => $meta['FINAL_RESPONSE_SOURCE'] ?? null,
        'fb' => $meta['OPEN_DOMAIN_FALLBACK'] ?? null,
        'rej' => $meta['OPEN_DOMAIN_REJECT_REASON'] ?? null,
        'retry' => $meta['OPEN_DOMAIN_RETRY'] ?? null,
        'intent' => $intent,
        'body' => $body,
        'empty' => trim($body) === '',
    ];
    file_put_contents("$out/$k.json", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "$k | status={$data['status']} conf=".json_encode($data['confirm'])." route={$data['route']} search={$data['search']} empty=".json_encode($data['empty'])." | ".mb_substr(str_replace("\n", ' ', $body), 0, 100)."\n";
}

echo 'RUNTIME='.trim((string) @file_get_contents('/home/pkjetp/jetpk_app/.jetpk-runtime-sha'))."\n";

$cases = [
    'wapas' => 'Dubai se Lahore wapas',
    'spell' => 'dubay se lahor wapis',
    'ret_tomorrow' => 'Lahore to Dubai tomorrow return',
    'ret_dated' => 'Lahore to Dubai on 10 October, return 15 October',
    'ret_on' => 'Lahore to Dubai on 10 October, return on 15 October',
    'oj' => 'Lahore to Jeddah then Medina to Lahore',
    'order39' => 'Lahore to Dubai tomorrow for 2 adults',
    'gk' => 'What is gravity?',
    'stock' => 'What is a stock?',
    'handoff' => 'Talk to support',
    'booking' => 'Check my booking please',
    'btc' => "What is Bitcoin's price right now?",
];
foreach ($cases as $k => $m) {
    row($out, $k, t($orch, $visitor, $m));
    usleep(300000);
}

$setup = t($orch, $visitor, 'Lahore to Dubai on 10 October return');
row($out, 'follow_setup', $setup);
$cid = (string) ($setup['conversation_id'] ?? $setup['_pid'] ?? '');
row($out, 'follow_on', t($orch, $visitor, 'return on 15 October', $cid));

echo "DONE\n";
