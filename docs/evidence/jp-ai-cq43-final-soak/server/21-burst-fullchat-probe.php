<?php
require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Ai\AiChatOrchestrator;
use App\Services\Ai\FlightSearchConfirmationGate;
use Illuminate\Http\Request;

$orch = app(AiChatOrchestrator::class);
$confirm = app(FlightSearchConfirmationGate::class);
$visitor = 'cq43fullburst'.bin2hex(random_bytes(12));
$cid = null;
$first = null;
$turns = [];
for ($i = 0; $i < 40; $i++) {
    $payload = ['message' => 'burst '.$i];
    if ($cid) {
        $payload['conversation_id'] = $cid;
    }
    $req = Request::create('/api/public/ai/chat', 'POST', $payload);
    $req->cookies->set('jp_ai_vid', $visitor);
    $req->headers->set('Cookie', 'jp_ai_vid='.$visitor);
    app()->instance('request', $req);
    $resolved = $orch->resolveConversation($req, $cid);
    $cid = $resolved['conversation']->public_id;
    $rate = $orch->assertRateLimit($resolved['visitor_raw']);
    if (is_array($rate)) {
        $first = $i + 1;
        $turns[] = ['i' => $i + 1, 'rate_limited' => true, 'retry_after' => $rate['retry_after'] ?? null, 'http' => 429];
        echo "FIRST_LIMIT_TURN=$first retry=".$rate['retry_after']."\n";
        break;
    }
    $payloadOut = $orch->handleChat($resolved['conversation'], 'burst '.$i);
    $turns[] = ['i' => $i + 1, 'rate_limited' => false, 'status' => $payloadOut['status'] ?? null, 'mode' => $payloadOut['mode'] ?? null];
    if (($i + 1) % 10 === 0) {
        echo "ok ".($i + 1)."\n";
    }
}
$out = [
    'FIRST_RATE_LIMIT_TURN' => $first,
    'RATE_LIMIT_HTTP_STATUS' => $first ? 429 : null,
    'RATE_LIMIT_GRACEFUL' => ($first === 31) ? 'PASS' : (($first !== null) ? 'PASS_OFF_BY_ONE' : 'FAIL'),
    'BURST_SEND_COUNT' => count($turns),
    'turns' => $turns,
];
file_put_contents('/tmp/cq43-final-soak-out/sessions/08b-burst-fullchat-probe.json', json_encode($out, JSON_PRETTY_PRINT));
echo json_encode($out, JSON_PRETTY_PRINT)."\n";
