#!/usr/bin/env php
<?php
require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Ai\AiChatOrchestrator;
use Illuminate\Http\Request;

$out = getenv('CQ42R3_RETRY3_OUT') ?: '/tmp/cq42r3-retry3-out';
@mkdir($out, 0775, true);
$orch = app(AiChatOrchestrator::class);
// Must be alphanumeric, length >= 32 (AiChatOrchestrator::resolveVisitorToken).
$visitor = 'cq42r3stablevisitor'.bin2hex(random_bytes(16));

function t(AiChatOrchestrator $orch, string $visitor, string $m, ?string $cid = null): array
{
    $p = ['message' => $m];
    if ($cid) {
        $p['conversation_id'] = $cid;
    }
    $r = Request::create('/api/public/ai/chat', 'POST', $p);
    $r->headers->set('User-Agent', 'CQ42-R3-Retry3/1');
    $r->headers->set('Cookie', 'jp_ai_vid='.$visitor);
    $r->server->set('REMOTE_ADDR', '127.0.0.1');
    $r->cookies->set('jp_ai_vid', $visitor);
    app()->instance('request', $r);
    $res = $orch->resolveConversation($r, $cid);
    $payload = $orch->handleChat($res['conversation'], $m);
    $payload['_resolved_public_id'] = $res['conversation']->public_id;
    $payload['_visitor_hash'] = $res['visitor_hash'] ?? null;

    return $payload;
}

function dump(string $out, string $key, array $o): void
{
    $meta = is_array($o['meta'] ?? null) ? $o['meta'] : [];
    $intent = is_array($o['intent'] ?? null) ? $o['intent'] : (is_array($meta['intent'] ?? null) ? $meta['intent'] : []);
    $row = [
        'status' => $o['status'] ?? null,
        'requires_confirmation' => (bool) ($o['requires_confirmation'] ?? false) || (bool) ($meta['CONFIRMATION_REQUIRED'] ?? false),
        'conversation_id' => $o['conversation_id'] ?? $o['_resolved_public_id'] ?? null,
        'visitor_hash' => $o['_visitor_hash'] ?? null,
        'src' => $meta['FINAL_RESPONSE_SOURCE'] ?? null,
        'fb' => $meta['SEMANTIC_FALLBACK_REASON'] ?? null,
        'op' => $meta['QWEN_OPERATION'] ?? null,
        'search' => (int) ($meta['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0),
        'intent' => $intent,
        'body' => $o['message'] ?? '',
    ];
    file_put_contents("$out/$key.json", json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "$key | status={$row['status']} conf=".json_encode($row['requires_confirmation'])." dep=".($intent['depart_date'] ?? 'null')." ret=".($intent['return_date'] ?? 'null')." trip=".($intent['trip_type'] ?? 'null')."\n";
    echo '  body='.mb_substr(str_replace("\n", ' ', (string) $row['body']), 0, 160)."\n";
}

echo "VISITOR=$visitor len=".strlen($visitor)."\n";

$setup = t($orch, $visitor, 'Lahore to Dubai on 10 October return');
dump($out, 'follow_setup', $setup);
$cid = (string) ($setup['conversation_id'] ?? $setup['_resolved_public_id'] ?? '');
$hash1 = (string) ($setup['_visitor_hash'] ?? '');
$follow = t($orch, $visitor, 'return on 15 October', $cid);
dump($out, 'follow_return_on', $follow);
$hash2 = (string) ($follow['_visitor_hash'] ?? '');
$same = (($follow['conversation_id'] ?? '') === $cid) || (($follow['_resolved_public_id'] ?? '') === $cid);
echo 'SAME_CID='.($same ? 'YES' : 'NO')." SAME_HASH=".($hash1 !== '' && $hash1 === $hash2 ? 'YES' : 'NO')."\n";
echo "setup=$cid follow=".($follow['conversation_id'] ?? '')."\n";

$setup2 = t($orch, $visitor, 'Lahore to Dubai on 10 October return');
dump($out, 'follow2_setup', $setup2);
$cid2 = (string) ($setup2['conversation_id'] ?? $setup2['_resolved_public_id'] ?? '');
$follow2 = t($orch, $visitor, 'return 15 October', $cid2);
dump($out, 'follow_return_bare', $follow2);
$same2 = (($follow2['conversation_id'] ?? '') === $cid2) || (($follow2['_resolved_public_id'] ?? '') === $cid2);
echo 'SAME_CID2='.($same2 ? 'YES' : 'NO')."\n";

// Also re-check dated confirm once
dump($out, 'ret_dated', t($orch, $visitor, 'Lahore to Dubai on 10 October, return 15 October'));
dump($out, 'ret_on_dated', t($orch, $visitor, 'Lahore to Dubai on 10 October, return on 15 October'));

echo "DONE\n";
