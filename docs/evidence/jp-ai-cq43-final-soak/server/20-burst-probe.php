<?php
require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'anonymous_per_minute='.json_encode(config('ota.ai_assistant.anonymous_per_minute'))."\n";
echo 'cache_default='.json_encode(config('cache.default'))."\n";

use App\Services\Ai\AiChatOrchestrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

$orch = app(AiChatOrchestrator::class);
$visitor = 'cq43burstprobe'.bin2hex(random_bytes(12));
$cid = null;
$first = null;
for ($i = 0; $i < 45; $i++) {
    $req = Request::create('/api/public/ai/chat', 'POST', ['message' => 'burst '.$i] + ($cid ? ['conversation_id' => $cid] : []));
    $req->cookies->set('jp_ai_vid', $visitor);
    $req->headers->set('Cookie', 'jp_ai_vid='.$visitor);
    app()->instance('request', $req);
    $resolved = $orch->resolveConversation($req, $cid);
    $cid = $resolved['conversation']->public_id;
    $raw = $resolved['visitor_raw'];
    $key = 'ai-chat-send:'.hash('sha256', $raw);
    $attempts = RateLimiter::attempts($key);
    $rate = $orch->assertRateLimit($raw);
    if (is_array($rate)) {
        echo "FIRST_LIMIT_TURN=".($i + 1)." attempts_before=$attempts retry=".$rate['retry_after']." visitor_len=".strlen($raw)."\n";
        $first = $i + 1;
        break;
    }
    if (($i + 1) % 10 === 0) {
        echo "ok turn=".($i + 1)." attempts_after=".RateLimiter::attempts($key)."\n";
    }
}
if ($first === null) {
    echo "NO_LIMIT_IN_45 attempts=".RateLimiter::attempts('ai-chat-send:'.hash('sha256', $visitor))."\n";
    // dump resolved raw vs cookie
    $req = Request::create('/api/public/ai/chat', 'POST', ['message' => 'x', 'conversation_id' => $cid]);
    $req->cookies->set('jp_ai_vid', $visitor);
    app()->instance('request', $req);
    $r = $orch->resolveConversation($req, $cid);
    echo 'raw_equals_cookie='.json_encode($r['visitor_raw'] === $visitor)."\n";
    echo 'raw_prefix='.substr($r['visitor_raw'], 0, 20)."\n";
}
