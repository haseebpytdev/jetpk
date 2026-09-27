#!/usr/bin/env php
<?php
require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Ai\AiChatOrchestrator;
use Illuminate\Http\Request;

$out = getenv('CQ42R3_RETRY_OUT') ?: '/tmp/cq42r3-retry-out';
@mkdir($out, 0775, true);
$orch = app(AiChatOrchestrator::class);

function t(AiChatOrchestrator $orch, string $m, ?string $cid = null): array
{
    $p = ['message' => $m];
    if ($cid) {
        $p['conversation_id'] = $cid;
    }
    $r = Request::create('/api/public/ai/chat', 'POST', $p);
    $r->headers->set('User-Agent', 'CQ42-R3-Retry/1');
    $r->server->set('REMOTE_ADDR', '127.0.0.1');
    app()->instance('request', $r);
    $res = $orch->resolveConversation($r, $cid);
    $payload = $orch->handleChat($res['conversation'], $m);
    $payload['_resolved_public_id'] = $res['conversation']->public_id;

    return $payload;
}

function dump(string $out, string $key, array $o): void
{
    $meta = is_array($o['meta'] ?? null) ? $o['meta'] : [];
    $intent = is_array($o['intent'] ?? null) ? $o['intent'] : (is_array($meta['intent'] ?? null) ? $meta['intent'] : []);
    $row = [
        'status' => $o['status'] ?? null,
        'state' => $o['state'] ?? null,
        'requires_confirmation' => $o['requires_confirmation'] ?? false,
        'conversation_id' => $o['conversation_id'] ?? $o['_resolved_public_id'] ?? null,
        'resolved_public_id' => $o['_resolved_public_id'] ?? null,
        'src' => $meta['FINAL_RESPONSE_SOURCE'] ?? null,
        'fb' => $meta['SEMANTIC_FALLBACK_REASON'] ?? null,
        'route' => $meta['SERVER_SINGLE_ROUTE'] ?? null,
        'cue' => $meta['EXPLICIT_RETURN_TRIP_CUE'] ?? null,
        'ret_req' => $meta['RETURN_DATE_REQUIRED'] ?? null,
        'confirm_meta' => $meta['CONFIRMATION_REQUIRED'] ?? null,
        'search' => $meta['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0,
        'intent' => $intent,
        'body' => $o['message'] ?? '',
        'meta' => $meta,
    ];
    file_put_contents("$out/$key.json", json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "$key | cid=".($row['conversation_id'] ?? '')." status={$row['status']} conf=".json_encode($row['requires_confirmation'])." dep=".($intent['depart_date'] ?? 'null')." ret=".($intent['return_date'] ?? 'null')." trip=".($intent['trip_type'] ?? 'null')."\n";
    echo '  body='.mb_substr(str_replace("\n", ' ', (string) $row['body']), 0, 160)."\n";
}

// A: dated return without "on" — 3 attempts
for ($i = 1; $i <= 3; $i++) {
    $o = t($orch, 'Lahore to Dubai on 10 October, return 15 October');
    dump($out, "ret_dated_$i", $o);
    usleep(400000);
}

// B: dated return with "on"
$o = t($orch, 'Lahore to Dubai on 10 October, return on 15 October');
dump($out, 'ret_on_dated', $o);
usleep(400000);

// C: return-only follow-up with forced same public_id
$setup = t($orch, 'Lahore to Dubai on 10 October return');
dump($out, 'follow_setup', $setup);
$cid = (string) ($setup['conversation_id'] ?? $setup['_resolved_public_id'] ?? '');
echo "FOLLOW_CID=$cid\n";
$follow = t($orch, 'return on 15 October', $cid !== '' ? $cid : null);
dump($out, 'follow_return_on', $follow);
echo 'SAME_CID='.((($follow['conversation_id'] ?? '') === $cid || ($follow['_resolved_public_id'] ?? '') === $cid) ? 'YES' : 'NO')."\n";

// D: alternate setup then bare "return 15 October"
$setup2 = t($orch, 'Lahore to Dubai on 10 October return');
dump($out, 'follow2_setup', $setup2);
$cid2 = (string) ($setup2['conversation_id'] ?? $setup2['_resolved_public_id'] ?? '');
$follow2 = t($orch, 'return 15 October', $cid2 !== '' ? $cid2 : null);
dump($out, 'follow_return_bare', $follow2);
echo 'SAME_CID2='.((($follow2['conversation_id'] ?? '') === $cid2 || ($follow2['_resolved_public_id'] ?? '') === $cid2) ? 'YES' : 'NO')."\n";

echo "DONE\n";
