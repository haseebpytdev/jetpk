#!/usr/bin/env php
<?php
require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Ai\AiChatOrchestrator;
use App\Services\Ai\ConversationIntentRouter;
use Illuminate\Http\Request;

$orch = app(AiChatOrchestrator::class);
$router = app(ConversationIntentRouter::class);

function t(AiChatOrchestrator $orch, string $m, ?string $cid = null): array
{
    $p = ['message' => $m];
    if ($cid) {
        $p['conversation_id'] = $cid;
    }
    $r = Request::create('/api/public/ai/chat', 'POST', $p);
    $r->headers->set('User-Agent', 'CQ42-R2-Retry/1');
    $r->server->set('REMOTE_ADDR', '127.0.0.1');
    app()->instance('request', $r);
    $res = $orch->resolveConversation($r, $cid);

    return $orch->handleChat($res['conversation'], $m);
}

$outDir = '/tmp/cq42r2-retry-out';
@mkdir($outDir, 0775, true);

$msgs = [
    'wapas' => 'Dubai se Lahore wapas',
    'spell' => 'dubay se lahor wapis',
    'ret_tomorrow' => 'Lahore to Dubai tomorrow return',
    'ret_dated' => 'Lahore to Dubai on 10 October, return 15 October',
    'stock' => 'What is a stock?',
    'ret_plain' => 'Lahore to Dubai return',
];

foreach ($msgs as $key => $m) {
    $o = t($orch, $m);
    $meta = is_array($o['meta'] ?? null) ? $o['meta'] : [];
    $row = [
        'message' => $m,
        'classify' => $router->classifyOpenDomain($m),
        'status' => $o['status'] ?? null,
        'src' => $meta['FINAL_RESPONSE_SOURCE'] ?? null,
        'cue' => $meta['EXPLICIT_RETURN_TRIP_CUE'] ?? null,
        'ret_req' => $meta['RETURN_DATE_REQUIRED'] ?? null,
        'oj' => $meta['OPEN_JAW_DETECTED'] ?? null,
        'route' => $meta['SERVER_SINGLE_ROUTE'] ?? null,
        'fb' => $meta['SEMANTIC_FALLBACK_REASON'] ?? null,
        'intent' => $o['intent'] ?? null,
        'body' => $o['message'] ?? '',
        'meta' => $meta,
        'requires_confirmation' => $o['requires_confirmation'] ?? false,
    ];
    file_put_contents("$outDir/$key.json", json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "$key | classify={$row['classify']} src={$row['src']} cue={$row['cue']} ret={$row['ret_req']} route={$row['route']} fb={$row['fb']}\n";
    echo '  body='.mb_substr(str_replace("\n", ' ', (string) $row['body']), 0, 160)."\n";
    usleep(400000);
}
echo "DONE\n";
