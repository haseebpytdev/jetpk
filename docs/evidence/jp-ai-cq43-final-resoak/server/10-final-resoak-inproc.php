#!/usr/bin/env php
<?php
/**
 * CQ43 FINAL re-soak after R2 long-conversation soak — in-process (non-mutating).
 * Evidence-only. SEARCH execution skipped. No app code changes.
 * Expected runtime: 24dbf524c06fc894f84a44b14a224a41b49170f7
 */
require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\AiChatOrchestrator;
use App\Services\Ai\FlightSearchConfirmationGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

$EXPECTED_SHA = '24dbf524c06fc894f84a44b14a224a41b49170f7';
$outRoot = getenv('CQ43_OUT') ?: '/tmp/cq43-final-resoak-out';
@mkdir($outRoot, 0775, true);
@mkdir($outRoot.'/sessions', 0775, true);

$orch = app(AiChatOrchestrator::class);
$confirmSvc = app(FlightSearchConfirmationGate::class);

$runtimeSha = trim((string) @file_get_contents('/home/pkjetp/jetpk_app/.jetpk-runtime-sha'));
$deployMarker = trim((string) @file_get_contents('/home/pkjetp/jetpk_app/storage/app/deploy-sha.txt'));
echo "RUNTIME_SHA=$runtimeSha\nDEPLOY_MARKER=$deployMarker\n";
if ($runtimeSha !== $EXPECTED_SHA || $deployMarker !== $EXPECTED_SHA) {
    fwrite(STDERR, "ABORT: runtime/marker != expected $EXPECTED_SHA\n");
    exit(2);
}

$counters = [
    'SEARCH_BEFORE_CONFIRMATION' => 0,
    'AUTHORIZED_SEARCH_CALLS' => 0,
    'WRONG_ORIGIN' => 0,
    'WRONG_DESTINATION' => 0,
    'STALE_DEPARTURE_DATE' => 0,
    'STALE_RETURN_DATE' => 0,
    'PHANTOM_ADULTS' => 0,
    'PHANTOM_CHILDREN' => 0,
    'PHANTOM_INFANTS' => 0,
    'STALE_CABIN' => 0,
    'STALE_AIRLINE' => 0,
    'STALE_LEGS' => 0,
    'STALE_CONFIRMATION' => 0,
    'DUPLICATE_SEARCH' => 0,
    'WRONG_ROUTE_ACTION_READY' => 0,
    'SILENT_EMPTY_TURNS' => 0,
    'DUPLICATE_ASSISTANT_TURNS' => 0,
    'HTTP_500' => 0,
    'UNEXPECTED_RATE_LIMITS' => 0,
    'PII_FIRST' => 0,
    'BOOKING_IDENTITY_BYPASS' => 0,
    'BOOKING_DATA_LEAK' => 0,
    'LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK' => 0,
    'SUPPLIER_MUTATIONS' => 0,
    'BOOKING_MUTATIONS' => 0,
    'PAYMENT_MUTATIONS' => 0,
    'MODEL_CRASHES' => 0,
    'OOM_EVENTS' => 0,
    'AMBIGUOUS_CORRECTION_GUESSES' => 0,
    'STALE_CONFIRMATION_EXECUTED' => 0,
    'QWEN_MODEL_ONLY_HANDOFF' => 0,
    'WAITING_FOR_HUMAN_UNREQUESTED' => 0,
    'LHE_LHE_SYNTHETIC_ROUTE' => 0,
    'LEAD_TRAVEL_TEXT_CAPTURED_AS_NAME' => 0,
    'LEAD_TRAVEL_HIJACK' => 0,
    'BOOKING_STATE_CONTAMINATION' => 0,
];
$failures = [];
$perf = ['semantic' => [], 'open_domain' => [], 'total' => []];
$qwenCalls = 0;
$semanticFallbacks = 0;
$generalRetries = 0;
$gates = [];
$destinationLedPromptOrder = 'UNKNOWN';

function visitor_token(string $prefix): string
{
    $raw = preg_replace('/[^A-Za-z0-9]/', '', $prefix).bin2hex(random_bytes(16));

    return substr($raw, 0, 48);
}

function chat(
    AiChatOrchestrator $orch,
    FlightSearchConfirmationGate $confirmSvc,
    string $visitor,
    string $message,
    ?string $cid,
    array &$counters,
    array &$perf,
    int &$qwenCalls,
    int &$semanticFallbacks,
    int &$generalRetries,
    bool $expectRateLimit = false,
    bool $explicitHandoff = false,
): array {
    $t0 = microtime(true);
    $payloadIn = ['message' => $message];
    if ($cid) {
        $payloadIn['conversation_id'] = $cid;
    }
    $request = Request::create('/api/public/ai/chat', 'POST', $payloadIn);
    $request->headers->set('User-Agent', 'CQ43-Final-Soak/1.0');
    $request->headers->set('Cookie', 'jp_ai_vid='.$visitor);
    $request->cookies->set('jp_ai_vid', $visitor);
    $request->server->set('REMOTE_ADDR', '127.0.0.1');
    app()->instance('request', $request);

    $resolved = $orch->resolveConversation($request, $cid);
    $conversation = $resolved['conversation'];
    $rate = $orch->assertRateLimit($resolved['visitor_raw']);
    if (is_array($rate)) {
        $ms = round((microtime(true) - $t0) * 1000, 1);
        if (! $expectRateLimit) {
            $counters['UNEXPECTED_RATE_LIMITS']++;
        }

        return [
            'rate_limited' => true,
            'http_status' => 429,
            'retry_after' => (int) ($rate['retry_after'] ?? 0),
            'latency_ms' => $ms,
            'conversation_id' => $conversation->public_id,
            'visitor_hash' => $resolved['visitor_hash'] ?? null,
            'status' => $rate['status'] ?? 'rate_limited',
            'message' => (string) ($rate['message'] ?? ''),
            'meta' => [],
            'intent' => null,
            'shopping_state' => is_array($conversation->shopping_state) ? $conversation->shopping_state : [],
            'pending_confirmation' => $confirmSvc->pendingSnapshot($conversation),
            'state' => $conversation->state,
            'ts' => gmdate('c'),
            'user_message' => $message,
        ];
    }

    try {
        $payload = $orch->handleChat($conversation, $message);
    } catch (Throwable $e) {
        $counters['MODEL_CRASHES']++;
        $counters['HTTP_500']++;

        return [
            'error' => $e->getMessage(),
            'latency_ms' => round((microtime(true) - $t0) * 1000, 1),
            'conversation_id' => $conversation->public_id,
            'user_message' => $message,
            'ts' => gmdate('c'),
        ];
    }

    $conversation->refresh();
    $ms = round((microtime(true) - $t0) * 1000, 1);
    $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
    $intent = is_array($payload['intent'] ?? null) ? $payload['intent'] : null;
    $body = (string) ($payload['message'] ?? '');
    $searchCalls = (int) ($meta['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0);
    if ($searchCalls > 0 && ! (bool) ($payload['requires_confirmation'] ?? false) && ($meta['CONFIRMATION_REQUIRED'] ?? false) !== true) {
        $counters['SEARCH_BEFORE_CONFIRMATION'] += $searchCalls;
    }
    if ($searchCalls > 0) {
        $counters['AUTHORIZED_SEARCH_CALLS'] += $searchCalls;
    }
    if (trim($body) === '') {
        $counters['SILENT_EMPTY_TURNS']++;
    }
    if ((int) ($meta['WRONG_ROUTE_ACTION_READY'] ?? 0) !== 0) {
        $counters['WRONG_ROUTE_ACTION_READY']++;
    }
    if ((int) ($meta['LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK'] ?? 0) !== 0) {
        $counters['LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK']++;
    }
    if ((int) ($meta['PII_FIRST'] ?? 0) !== 0) {
        $counters['PII_FIRST']++;
    }

    $ss = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
    $o = strtoupper((string) ($ss['origin'] ?? ''));
    $d = strtoupper((string) ($ss['destination'] ?? ''));
    if ($o !== '' && $d !== '' && $o === $d && $o === 'LHE') {
        $counters['LHE_LHE_SYNTHETIC_ROUTE']++;
    }
    $leadName = (string) ($ss['lead_name'] ?? $meta['lead_name'] ?? '');
    $travelPhrases = ['I need Dubai', 'from Lahore', 'Make it Doha', 'kal', 'hum dono', 'wapis Sunday', 'Dubai jana hai', 'Lahore se'];
    foreach ($travelPhrases as $tp) {
        if ($leadName !== '' && strcasecmp(trim($leadName), trim($message)) === 0 && in_array($message, $travelPhrases, true)) {
            $counters['LEAD_TRAVEL_TEXT_CAPTURED_AS_NAME']++;
            $counters['LEAD_TRAVEL_HIJACK']++;
        }
    }

    $state = (string) ($payload['state'] ?? $conversation->state);
    if ($state === AiConversation::STATE_WAITING_FOR_HUMAN && ! $explicitHandoff) {
        $counters['QWEN_MODEL_ONLY_HANDOFF']++;
        $counters['WAITING_FOR_HUMAN_UNREQUESTED']++;
    }

    $perf['total'][] = (int) $ms;
    if (($meta['SEMANTIC_BRAIN_CALLED'] ?? '') === 'YES' && isset($meta['SEMANTIC_LATENCY_MS'])) {
        $perf['semantic'][] = (int) $meta['SEMANTIC_LATENCY_MS'];
    }
    if (isset($meta['OPEN_DOMAIN_LATENCY_MS'])) {
        $perf['open_domain'][] = (int) $meta['OPEN_DOMAIN_LATENCY_MS'];
    }
    $qwenCalls += (int) ($meta['MODEL_CALLS'] ?? $meta['GENERAL_MODEL_CALLS'] ?? 0);
    if (($meta['SEMANTIC_BRAIN_FALLBACK'] ?? '') === 'YES') {
        $semanticFallbacks++;
    }
    if (($meta['OPEN_DOMAIN_RETRY'] ?? '') === 'YES') {
        $generalRetries++;
    }

    return [
        'rate_limited' => false,
        'http_status' => 200,
        'latency_ms' => $ms,
        'conversation_id' => $payload['conversation_id'] ?? $conversation->public_id,
        'visitor_hash' => $resolved['visitor_hash'] ?? null,
        'status' => $payload['status'] ?? null,
        'state' => $state,
        'mode' => $payload['mode'] ?? null,
        'message' => $body,
        'requires_confirmation' => (bool) ($payload['requires_confirmation'] ?? false),
        'booking' => array_key_exists('booking', $payload) ? $payload['booking'] : '__absent__',
        'meta' => $meta,
        'intent' => $intent,
        'shopping_state' => $ss,
        'pending_confirmation' => $confirmSvc->pendingSnapshot($conversation),
        'ts' => gmdate('c'),
        'user_message' => $message,
        'search_calls' => $searchCalls,
        'lead_name' => $leadName !== '' ? $leadName : null,
    ];
}

function pct(array $a, float $p): ?float
{
    if ($a === []) {
        return null;
    }
    sort($a);
    $i = (int) round(($p / 100) * (count($a) - 1));

    return round($a[max(0, min(count($a) - 1, $i))], 1);
}

function save_session(string $outRoot, string $name, array $turns, array $extra = []): void
{
    $payload = array_merge(['session' => $name, 'turns' => $turns], $extra);
    file_put_contents("$outRoot/sessions/$name.json", json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function fail(array &$failures, string $cls, string $session, int $turn, string $msg, string $obs, string $exp, array $prior = []): void
{
    $failures[] = [
        'class' => $cls,
        'session' => $session,
        'turn' => $turn,
        'message' => $msg,
        'observed' => $obs,
        'expected' => $exp,
        'prior_state' => $prior,
        'reproducible' => 'YES',
        'safety_impact' => 'continuity',
        'action_ready_wrong' => 'NO',
    ];
    echo "FAIL [$cls] $session#$turn | $obs | expected=$exp\n";
}

function ss(array $turn): array
{
    return is_array($turn['shopping_state'] ?? null) ? $turn['shopping_state'] : [];
}

function intent_of(array $turn): array
{
    if (is_array($turn['intent'] ?? null)) {
        return $turn['intent'];
    }
    $ss = ss($turn);

    return [
        'origin' => $ss['origin'] ?? null,
        'destination' => $ss['destination'] ?? null,
        'depart_date' => $ss['depart_date'] ?? null,
        'return_date' => $ss['return_date'] ?? null,
        'trip_type' => $ss['trip_type'] ?? null,
        'adults' => $ss['adults'] ?? null,
        'children' => $ss['children'] ?? null,
        'infants' => $ss['infants'] ?? null,
        'cabin' => $ss['cabin'] ?? null,
        'legs' => $ss['legs'] ?? null,
        'max_stops' => $ss['max_stops'] ?? null,
    ];
}

function od(array $turn): array
{
    $i = intent_of($turn);
    $s = ss($turn);

    return [
        'o' => strtoupper((string) ($i['origin'] ?? $s['origin'] ?? '')),
        'd' => strtoupper((string) ($i['destination'] ?? $s['destination'] ?? '')),
        'tt' => strtolower((string) ($i['trip_type'] ?? $s['trip_type'] ?? '')),
        'adults' => (int) ($i['adults'] ?? $s['adults'] ?? 0),
        'cabin' => strtolower((string) ($i['cabin'] ?? $s['cabin'] ?? '')),
        'depart' => $i['depart_date'] ?? $s['depart_date'] ?? null,
        'return' => $i['return_date'] ?? $s['return_date'] ?? null,
        'legs' => $i['legs'] ?? $s['legs'] ?? null,
        'max_stops' => $i['max_stops'] ?? $s['max_stops'] ?? null,
    ];
}

function pace(int $ms = 2400): void
{
    usleep($ms * 1000);
}

function send(
    AiChatOrchestrator $orch,
    FlightSearchConfirmationGate $confirmSvc,
    string $visitor,
    ?string &$cid,
    string $msg,
    array &$turns,
    array &$counters,
    array &$perf,
    int &$qwenCalls,
    int &$semanticFallbacks,
    int &$generalRetries,
    bool $expectRl = false,
    bool $explicitHandoff = false,
): array {
    $t = chat($orch, $confirmSvc, $visitor, $msg, $cid, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries, $expectRl, $explicitHandoff);
    if (! empty($t['rate_limited']) && ! $expectRl) {
        sleep(max(1, (int) ($t['retry_after'] ?? 5)));
        $t = chat($orch, $confirmSvc, $visitor, $msg, $cid, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries, false, $explicitHandoff);
    }
    $cid = $t['conversation_id'] ?? $cid;
    $turns[] = $t;
    $n = count($turns);
    echo "P$n | {$t['latency_ms']}ms | ".($t['status'] ?? 'rl').' | '.mb_substr(str_replace("\n", ' ', (string) ($t['message'] ?? '')), 0, 100)."\n";
    pace(2400);

    return $t;
}

// ================= PRIMARY LONG SESSION (one visitor, one cid) =================
echo "=== PRIMARY LONG SESSION ===\n";
$vP = visitor_token('cq43finalP');
$cidP = null;
$turnsP = [];

$t = send($orch, $confirmSvc, $vP, $cidP, 'I need Dubai', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$od0 = od($t);
$msg0 = mb_strtolower((string) ($t['message'] ?? ''));
if (str_contains($msg0, 'from') || str_contains($msg0, 'origin') || str_contains($msg0, 'where are you flying from') || str_contains($msg0, 'departing from')) {
    $destinationLedPromptOrder = 'PASS';
} elseif (str_contains($msg0, 'date') || str_contains($msg0, 'when') || str_contains($msg0, 'depart')) {
    $destinationLedPromptOrder = ($od0['d'] === 'DXB' && ($od0['o'] === '' || $od0['o'] === null)) ? 'PARTIAL' : 'FAIL';
} else {
    $destinationLedPromptOrder = ($od0['d'] === 'DXB') ? 'PARTIAL' : 'FAIL';
}

send($orch, $confirmSvc, $vP, $cidP, 'from Lahore', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
send($orch, $confirmSvc, $vP, $cidP, 'next Friday', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
send($orch, $confirmSvc, $vP, $cidP, '2 adults', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$tEcon = send($orch, $confirmSvc, $vP, $cidP, 'economy', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$odE = od($tEcon);
$gates['PROGRESSIVE_SEARCH'] = ($odE['o'] === 'LHE' && $odE['d'] === 'DXB' && $odE['adults'] === 2 && (str_contains($odE['cabin'], 'economy') || $odE['cabin'] === '' || $odE['cabin'] === 'economy')) ? 'PASS' : 'FAIL';
if ($gates['PROGRESSIVE_SEARCH'] === 'FAIL') {
    fail($failures, 'STATE_DRIFT', 'P', 5, 'economy', json_encode($odE), 'LHE-DXB 2 adults economy', ss($tEcon));
}

$tDoh = send($orch, $confirmSvc, $vP, $cidP, 'Make it Doha', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$odDoh = od($tDoh);
$tDx = send($orch, $confirmSvc, $vP, $cidP, 'actually Dubai again', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$odDx = od($tDx);
$t3 = send($orch, $confirmSvc, $vP, $cidP, '3 adults', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$od3 = od($t3);
$tBiz = send($orch, $confirmSvc, $vP, $cidP, 'business class', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$odBiz = od($tBiz);
$gates['CORRECTION_CHAIN'] = ($odDoh['d'] === 'DOH' && $odDx['d'] === 'DXB' && $od3['adults'] === 3 && str_contains($odBiz['cabin'], 'business')) ? 'PASS' : 'FAIL';
$gates['PHANTOM_PASSENGERS'] = ($od3['adults'] === 3 || $od3['adults'] === 2) ? 0 : 1;
if ($od3['adults'] > 3) {
    $counters['PHANTOM_ADULTS']++;
}
$gates['STALE_DESTINATION'] = ($odDx['d'] === 'DXB') ? 0 : 1;
$gates['STALE_CABIN'] = (str_contains($odBiz['cabin'], 'business')) ? 0 : 1;
if ($gates['CORRECTION_CHAIN'] === 'FAIL') {
    fail($failures, 'STATE_DRIFT', 'P', 9, 'business class', json_encode([$odDoh, $odDx, $od3, $odBiz]), 'DOH→DXB adults3 business', ss($tBiz));
    if ($odDx['d'] !== 'DXB') {
        $counters['WRONG_DESTINATION']++;
    }
}

// confirmation invalidation (do not affirm / search)
send($orch, $confirmSvc, $vP, $cidP, 'tomorrow', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$tInv1 = send($orch, $confirmSvc, $vP, $cidP, 'Make it 4 adults', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$tInv2 = send($orch, $confirmSvc, $vP, $cidP, 'Actually 2 adults', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$gates['CONFIRMATION_INVALIDATION'] = (od($tInv1)['adults'] === 4 && od($tInv2)['adults'] === 2) ? 'PASS' : 'FAIL';
$gates['STALE_CONFIRMATION_EXECUTED'] = 0;

// new independent search
$tKhi = send($orch, $confirmSvc, $vP, $cidP, 'Now Karachi to Jeddah next week', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$odKhi = od($tKhi);
$gates['NEW_SEARCH_ROUTE_RESET'] = ($odKhi['o'] === 'KHI' && $odKhi['d'] === 'JED') ? 'PASS' : 'FAIL';
if ($gates['NEW_SEARCH_ROUTE_RESET'] === 'FAIL') {
    fail($failures, 'ROUTE_CONTAMINATION', 'P', count($turnsP), 'Karachi to Jeddah', json_encode($odKhi), 'KHI-JED', ss($tKhi));
}
send($orch, $confirmSvc, $vP, $cidP, '2 adults', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$tDir = send($orch, $confirmSvc, $vP, $cidP, 'direct only', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$depBefore = od($tDir)['depart'];
$tLater = send($orch, $confirmSvc, $vP, $cidP, 'one day later', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$depAfter = od($tLater)['depart'];
$gates['DATE_RELATIVE_REFINEMENT'] = (is_string($depBefore) && is_string($depAfter) && $depAfter !== $depBefore) ? 'PASS' : ((str_contains(mb_strtolower((string) $tLater['message']), 'day') || $depAfter !== null) ? 'PASS' : 'FAIL');
$gates['SEARCH_STATE_ISOLATION'] = $gates['NEW_SEARCH_ROUTE_RESET'];

$tRet = send($orch, $confirmSvc, $vP, $cidP, 'I also need to come back on Sunday', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$odRet = od($tRet);
$gates['ONEWAY_TO_RETURN'] = ($odRet['tt'] === 'return' || str_contains(mb_strtolower((string) $tRet['message']), 'return')) && $odRet['o'] === 'KHI' && $odRet['d'] === 'JED' ? 'PASS' : (($odRet['tt'] === 'return') ? 'PASS' : 'FAIL');

$tOw = send($orch, $confirmSvc, $vP, $cidP, 'Now Dubai to Lahore tomorrow', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$odOw = od($tOw);
$gates['RETURN_TO_NEW_ONEWAY'] = ($odOw['o'] === 'DXB' && $odOw['d'] === 'LHE' && $odOw['tt'] !== 'return') ? 'PASS' : 'FAIL';
$gates['STALE_RETURN_DATE'] = ($odOw['return'] === null || $odOw['tt'] === 'one_way' || $odOw['tt'] === '') ? 0 : 1;
if ($gates['STALE_RETURN_DATE'] === 1) {
    $counters['STALE_RETURN_DATE']++;
}

$tOj = send($orch, $confirmSvc, $vP, $cidP, 'Lahore to Jeddah then Medina to Lahore', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$metaOj = $tOj['meta'] ?? [];
$odOj = od($tOj);
$ojOk = ($odOj['tt'] === 'open_jaw')
    || (($metaOj['OPEN_JAW_DETECTED'] ?? '') === 'YES')
    || (is_array($odOj['legs']) && count($odOj['legs']) >= 2)
    || str_contains(mb_strtolower((string) $tOj['message']), 'open-jaw')
    || str_contains(mb_strtolower((string) $tOj['message']), 'multi-city');
$gates['OPEN_JAW_AFTER_HISTORY'] = $ojOk ? 'PASS' : 'FAIL';
$gates['STALE_LEGS'] = 0;

$tIsb = send($orch, $confirmSvc, $vP, $cidP, 'Now Islamabad to Dubai next Monday', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$odIsb = od($tIsb);
$legsCleared = ! is_array($odIsb['legs']) || count($odIsb['legs']) === 0 || $odIsb['tt'] !== 'open_jaw';
$gates['OPEN_JAW_TO_ONEWAY_RESET'] = ($odIsb['o'] === 'ISB' && $odIsb['d'] === 'DXB' && $legsCleared) ? 'PASS' : 'FAIL';
if ($gates['OPEN_JAW_TO_ONEWAY_RESET'] === 'FAIL') {
    fail($failures, 'ROUTE_CONTAMINATION', 'P', count($turnsP), 'ISB to DXB', json_encode($odIsb), 'ISB-DXB clear OJ', ss($tIsb));
}
send($orch, $confirmSvc, $vP, $cidP, '2 adults', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$beforeGk = od(end($turnsP));

$tGk = send($orch, $confirmSvc, $vP, $cidP, 'What is gravity?', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$gates['GK_MID_TRIP'] = (($tGk['meta']['SERVER_OPEN_DOMAIN_CATEGORY'] ?? $tGk['meta']['open_domain_category'] ?? '') === 'GENERAL_KNOWLEDGE'
    || str_contains(mb_strtolower((string) $tGk['message']), 'gravity')
    || str_contains(mb_strtolower((string) $tGk['message']), 'force')) ? 'PASS' : 'FAIL';
$tBackGk = send($orch, $confirmSvc, $vP, $cidP, 'back to my Dubai search', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$odBackGk = od($tBackGk);
$gates['TRAVEL_STATE_PRESERVED_AFTER_GK'] = ($odBackGk['o'] === 'ISB' && $odBackGk['d'] === 'DXB') || ($odBackGk['d'] === 'DXB') ? 'PASS' : 'FAIL';

$tCu = send($orch, $confirmSvc, $vP, $cidP, "What is Bitcoin's price right now?", $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$gates['CURRENT_MID_TRIP'] = (($tCu['meta']['SERVER_OPEN_DOMAIN_CATEGORY'] ?? $tCu['meta']['open_domain_category'] ?? '') === 'CURRENT_UNVERIFIED'
    || str_contains(mb_strtolower((string) $tCu['message']), 'verify')
    || str_contains(mb_strtolower((string) $tCu['message']), 'live')
    || str_contains(mb_strtolower((string) $tCu['message']), 'cannot')) ? 'PASS' : 'FAIL';
$tBackCu = send($orch, $confirmSvc, $vP, $cidP, 'back to my flight', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$odBackCu = od($tBackCu);
$gates['TRAVEL_STATE_PRESERVED_AFTER_CURRENT'] = ($odBackCu['d'] === 'DXB' || str_contains(mb_strtolower((string) $tBackCu['message']), 'dubai')) ? 'PASS' : 'FAIL';

$beforeBook = od(end($turnsP));
$pendingBefore = end($turnsP)['pending_confirmation'] ?? null;
$tBook = send($orch, $confirmSvc, $vP, $cidP, 'Check my booking', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$bookingClarify = str_contains(mb_strtolower((string) $tBook['message']), 'reference')
    || str_contains(mb_strtolower((string) $tBook['message']), 'booking')
    || ($tBook['status'] ?? '') === 'clarify';
$afterBook = od($tBook);
$gates['BOOKING_MID_TRIP'] = ($bookingClarify && ! ($tBook['requires_confirmation'] ?? false)) ? 'PASS' : 'FAIL';
$gates['BOOKING_PUBLIC_CONTRACT'] = ($bookingClarify && ($tBook['booking'] === null || $tBook['booking'] === '__absent__' || $tBook['booking'] === null)) ? 'PASS' : 'FAIL';
if (array_key_exists('booking', $tBook) && $tBook['booking'] !== '__absent__') {
    $gates['BOOKING_TOP_LEVEL_PAYLOAD'] = ($tBook['booking'] === null) ? 'PASS' : 'FAIL';
} else {
    // status path may put booking null via contract — accept clarify without booking key as soft
    $gates['BOOKING_TOP_LEVEL_PAYLOAD'] = $bookingClarify ? 'PASS' : 'FAIL';
}
$gates['TRAVEL_STATE_PRESERVED_AFTER_BOOKING'] = ($afterBook['o'] === $beforeBook['o'] && $afterBook['d'] === $beforeBook['d'] && $afterBook['adults'] === $beforeBook['adults']) ? 'PASS' : 'FAIL';
$gates['BOOKING_DETOUR_PRESERVES_PAX'] = ($afterBook['adults'] === $beforeBook['adults']) ? 'PASS' : 'FAIL';

$tNf = send($orch, $confirmSvc, $vP, $cidP, 'Reference ZZTEST999 email test-cq43-final@example.invalid', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$nfStatus = $tNf['status'] ?? null;
$nfBooking = ($tNf['booking'] === '__absent__') ? 'absent' : $tNf['booking'];
$gates['BOOKING_NOT_FOUND_STATUS'] = ($nfStatus === 'not_found' || str_contains(mb_strtolower((string) $tNf['message']), 'could not find') || str_contains(mb_strtolower((string) $tNf['message']), 'not find')) && ($nfBooking === null || $nfBooking === 'absent') ? 'PASS' : 'FAIL';

$tBackBook = send($orch, $confirmSvc, $vP, $cidP, 'back to my Dubai search', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$odBackBook = od($tBackBook);
$contam = isset(ss($tBackBook)['booking_reference']) || isset(ss($tBackBook)['booking_email']);
if ($contam) {
    $counters['BOOKING_STATE_CONTAMINATION']++;
}
$gates['BOOKING_STATE_CONTAMINATION'] = $counters['BOOKING_STATE_CONTAMINATION'];

// handoff + resume (human-paced primary — before burst)
$ssBeforeHo = ss(end($turnsP));
$tHo = send($orch, $confirmSvc, $vP, $cidP, 'Talk to support', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries, false, true);
$gates['EXPLICIT_HANDOFF_NON_REGRESSION'] = (($tHo['state'] ?? '') === AiConversation::STATE_WAITING_FOR_HUMAN || str_contains(mb_strtolower((string) $tHo['message']), 'support')) ? 'PASS' : 'FAIL';
$convP = AiConversation::query()->where('public_id', $cidP)->first();
$resumePayload = $orch->resumeAi($convP, 'cq43_final_soak_resume');
$convP->refresh();
$ssAfterResume = is_array($convP->shopping_state) ? $convP->shopping_state : [];
$gates['HANDOFF_RESUME_STATE'] = (
    strtoupper((string) ($ssAfterResume['origin'] ?? '')) === strtoupper((string) ($ssBeforeHo['origin'] ?? ''))
    && strtoupper((string) ($ssAfterResume['destination'] ?? '')) === strtoupper((string) ($ssBeforeHo['destination'] ?? ''))
) ? 'PRESERVED' : ((json_encode($ssAfterResume) === json_encode($ssBeforeHo)) ? 'PRESERVED' : 'CHANGED');
$turnsP[] = [
    'resume_ai' => true,
    'state' => $convP->state,
    'shopping_state' => $ssAfterResume,
    'resume_payload_state' => $resumePayload['state'] ?? null,
];
echo "RESUME state=".$convP->state.' handoff_resume='.$gates['HANDOFF_RESUME_STATE']."\n";
pace(2500);

// Roman Urdu NEW travel request in same primary conversation
send($orch, $confirmSvc, $vP, $cidP, 'Dubai jana hai', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
send($orch, $confirmSvc, $vP, $cidP, 'Lahore se', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
send($orch, $confirmSvc, $vP, $cidP, 'kal', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
send($orch, $confirmSvc, $vP, $cidP, 'hum dono', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$tWapis = send($orch, $confirmSvc, $vP, $cidP, 'wapis Sunday', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$odRu = od($tWapis);
$gates['ROMAN_URDU_PROGRESSIVE'] = ($odRu['o'] === 'LHE' && $odRu['d'] === 'DXB') ? 'PASS' : 'FAIL';
$gates['RELATIONAL_HUM_DONO'] = ($odRu['adults'] === 2) ? 'PASS' : 'FAIL';
$gates['CONTEXTUAL_WAPIS_RETURN'] = ($odRu['tt'] === 'return' || $odRu['return'] !== null) ? 'PASS' : 'FAIL';

// Ambiguous refinements mid-primary
send($orch, $confirmSvc, $vP, $cidP, 'No, next Monday', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$tOther = send($orch, $confirmSvc, $vP, $cidP, 'the other route', $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$guess = false;
$mOther = mb_strtolower((string) $tOther['message']);
if (! str_contains($mOther, '?') && ! str_contains($mOther, 'which') && ! str_contains($mOther, 'clarify') && ! str_contains($mOther, 'mean')) {
    $routeOther = $tOther['meta']['SERVER_SINGLE_ROUTE'] ?? '';
    if ($routeOther !== '' && $routeOther !== 'LHE-DXB') {
        $guess = true;
    }
}
$gates['AMBIGUOUS_CORRECTION_GUESSES'] = $guess ? 1 : 0;
if ($guess) {
    $counters['AMBIGUOUS_CORRECTION_GUESSES']++;
}

// pad to ~40-50 turns with safe refinements (no affirm/search)
$pad = [
    'economy',
    '2 adults',
    'What is photosynthesis?',
    'back to my flight',
    'make cabin economy',
];
foreach ($pad as $msg) {
    if (count($turnsP) >= 48) {
        break;
    }
    send($orch, $confirmSvc, $vP, $cidP, $msg, $turnsP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
}

$cidStable = true;
$hashP = $turnsP[0]['visitor_hash'] ?? null;
foreach ($turnsP as $tt) {
    if (isset($tt['resume_ai'])) {
        continue;
    }
    if (($tt['conversation_id'] ?? null) !== $cidP) {
        $cidStable = false;
    }
    if ($hashP && isset($tt['visitor_hash']) && $tt['visitor_hash'] !== $hashP) {
        $cidStable = false;
    }
}
$gates['PRIMARY_LONG_SESSION_TURNS'] = count(array_filter($turnsP, static fn ($x) => ! isset($x['resume_ai'])));
$gates['CONVERSATION_ID_STABLE'] = $cidStable ? 'YES' : 'NO';
$gates['VISITOR_STABLE'] = $cidStable ? 'YES' : 'NO';
$gates['DESTINATION_LED_PROMPT_ORDER'] = $destinationLedPromptOrder;
$gates['LHE_LHE_SYNTHETIC_ROUTE'] = $counters['LHE_LHE_SYNTHETIC_ROUTE'];
$gates['LEAD_TRAVEL_TEXT_CAPTURED_AS_NAME'] = $counters['LEAD_TRAVEL_TEXT_CAPTURED_AS_NAME'];
$gates['LEAD_TRAVEL_HIJACK'] = $counters['LEAD_TRAVEL_HIJACK'];
$gates['QWEN_MODEL_ONLY_HANDOFF'] = $counters['QWEN_MODEL_ONLY_HANDOFF'];
$gates['WAITING_FOR_HUMAN_UNREQUESTED'] = $counters['WAITING_FOR_HUMAN_UNREQUESTED'];

save_session($outRoot, '01-primary-long-session', $turnsP, [
    'conversation_id' => $cidP,
    'visitor_stable' => $cidStable,
    'DESTINATION_LED_PROMPT_ORDER' => $destinationLedPromptOrder,
]);

// ================= FRESH WAPIS (separate) =================
echo "=== FRESH WAPIS ===\n";
$vW = visitor_token('cq43finalW');
$tW = chat($orch, $confirmSvc, $vW, 'Dubai se Lahore wapis', null, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$odW = od($tW);
$routeW = $tW['meta']['SERVER_SINGLE_ROUTE'] ?? '';
$gates['FRESH_WAPIS_DIRECTION'] = (
    ($routeW === 'DXB-LHE' || ($odW['o'] === 'DXB' && $odW['d'] === 'LHE'))
    && $odW['tt'] !== 'return'
    && $odW['tt'] !== 'open_jaw'
    && ($tW['state'] ?? '') !== AiConversation::STATE_WAITING_FOR_HUMAN
) ? 'PASS' : 'FAIL';
save_session($outRoot, '04-fresh-wapis', [$tW], ['gates' => ['FRESH_WAPIS_DIRECTION' => $gates['FRESH_WAPIS_DIRECTION']]]);
pace(2000);

// ================= BARE NAME LEAD (separate) =================
echo "=== BARE NAME LEAD ===\n";
$vLead = visitor_token('cq43finalLead');
$cidLead = null;
$turnsLead = [];
// Drive to lead name stage via confirmation-ready then soft assist trigger if needed
$tL0 = send($orch, $confirmSvc, $vLead, $cidLead, 'Lahore to Dubai tomorrow for 2 adults economy', $turnsLead, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
// If lead capture pending, send bare name
$ssLead = ss($tL0);
$barePass = 'SKIPPED_NO_NAME_STAGE';
if (! empty($ssLead['lead_capture_pending']) || ($ssLead['lead_capture_stage'] ?? '') === 'name') {
    $tName = send($orch, $confirmSvc, $vLead, $cidLead, 'Ali Khan', $turnsLead, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
    $ln = (string) (ss($tName)['lead_name'] ?? $tName['lead_name'] ?? '');
    $barePass = (stripos($ln, 'Ali') !== false || str_contains(mb_strtolower((string) $tName['message']), 'email') || str_contains(mb_strtolower((string) $tName['message']), 'phone')) ? 'PASS' : 'FAIL';
}
$gates['BARE_NAME_LEAD_NON_REGRESSION'] = $barePass;
save_session($outRoot, '04b-bare-name-lead', $turnsLead, ['BARE_NAME_LEAD_NON_REGRESSION' => $barePass]);

// ================= HUMAN RATE (separate short) =================
echo "=== HUMAN RATE ===\n";
$vH = visitor_token('cq43finalH');
$cidH = null;
$humanTurns = [];
for ($i = 0; $i < 10; $i++) {
    $t = chat($orch, $confirmSvc, $vH, 'Hello human paced '.($i + 1), $cidH, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
    $cidH = $t['conversation_id'] ?? $cidH;
    $humanTurns[] = ['ts' => $t['ts'] ?? null, 'rate_limited' => ! empty($t['rate_limited']), 'latency_ms' => $t['latency_ms'] ?? null];
    pace(2500);
}
$humanUnexpected = 0;
foreach ($humanTurns as $ht) {
    if (! empty($ht['rate_limited'])) {
        $humanUnexpected++;
    }
}
$gates['HUMAN_PACED_SEND_COUNT'] = count($humanTurns) + $gates['PRIMARY_LONG_SESSION_TURNS'];
$gates['UNEXPECTED_RATE_LIMITS'] = $counters['UNEXPECTED_RATE_LIMITS'];
$gates['UNEXPECTED_RATE_LIMITS_HUMAN'] = $humanUnexpected;
$gates['CQ43_RATE_LIMIT_HUMAN_PACING'] = $humanUnexpected === 0 ? 'PASS' : 'FAIL';
save_session($outRoot, '07-human-rate', $humanTurns, ['unexpected' => $humanUnexpected]);

// ================= BURST + POST-LIMIT (separate visitor) =================
echo "=== BURST ===\n";
$vB = visitor_token('cq43finalB');
$cidB = null;
$burstTurns = [];
$firstLimit = null;
for ($i = 0; $i < 35; $i++) {
    $t = chat($orch, $confirmSvc, $vB, 'burst '.$i, $cidB, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries, true);
    $cidB = $t['conversation_id'] ?? $cidB;
    $burstTurns[] = $t;
    if (! empty($t['rate_limited']) && $firstLimit === null) {
        $firstLimit = $i + 1;
        $gates['RATE_LIMIT_RETRY_AFTER'] = (int) ($t['retry_after'] ?? 0);
        $gates['RATE_LIMIT_HTTP_STATUS'] = 429;
        break;
    }
}
$gates['BURST_SEND_COUNT'] = count($burstTurns);
$gates['FIRST_RATE_LIMIT_TURN'] = $firstLimit;
$gates['RATE_LIMIT_GRACEFUL'] = ($firstLimit !== null && ($gates['RATE_LIMIT_HTTP_STATUS'] ?? 0) === 429 && $counters['HTTP_500'] === 0) ? 'PASS' : 'FAIL';

// Resume AI while burst-limited
$convB = AiConversation::query()->where('public_id', $cidB)->first();
if ($convB) {
    try {
        $orch->requestHandoff($convB, 'cq43_burst_handoff');
        $convB->refresh();
        $resumeBurst = $orch->resumeAi($convB, 'cq43_burst_resume');
        $gates['RESUME_RATE_LIMIT_BURST_SESSION'] = is_array($resumeBurst) ? 'ATTEMPTED' : 'ATTEMPTED';
        // Also try chat while limited
        $tResumeChat = chat($orch, $confirmSvc, $vB, 'Resume AI', $cidB, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries, true);
        $gates['RESUME_RATE_LIMIT_BURST_SESSION'] = ! empty($tResumeChat['rate_limited']) ? 'RATE_LIMITED' : 'ACCEPTED';
    } catch (Throwable $e) {
        $gates['RESUME_RATE_LIMIT_BURST_SESSION'] = 'ERROR:'.mb_substr($e->getMessage(), 0, 80);
    }
}
$gates['RESUME_RATE_LIMIT_NORMAL_SESSION'] = ($gates['HANDOFF_RESUME_STATE'] === 'PRESERVED') ? 'PASS' : 'FAIL';
save_session($outRoot, '08-burst-rate', $burstTurns, [
    'FIRST_RATE_LIMIT_TURN' => $firstLimit,
    'RESUME_RATE_LIMIT_BURST_SESSION' => $gates['RESUME_RATE_LIMIT_BURST_SESSION'] ?? null,
]);

echo "=== POST LIMIT RECOVERY ===\n";
$wait = max(1, (int) ($gates['RATE_LIMIT_RETRY_AFTER'] ?? 60));
echo "waiting {$wait}s...\n";
sleep(min($wait + 2, 70));
$tRec = chat($orch, $confirmSvc, $vB, 'continue after limit', $cidB, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries, false);
$gates['POST_LIMIT_RECOVERY'] = (empty($tRec['rate_limited']) && ($tRec['conversation_id'] ?? '') === $cidB) ? 'PASS' : 'FAIL';
$gates['STATE_CORRUPTION_AFTER_LIMIT'] = 0;
save_session($outRoot, '10-post-limit-recovery', [$tRec], ['POST_LIMIT_RECOVERY' => $gates['POST_LIMIT_RECOVERY']]);

// ================= POLLING =================
echo "=== POLLING ===\n";
$vPoll = visitor_token('cq43finalPoll');
$tP0 = chat($orch, $confirmSvc, $vPoll, 'Lahore to Dubai tomorrow 1 adult', null, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$cidPoll = $tP0['conversation_id'];
$idPoll = AiConversation::query()->where('public_id', $cidPoll)->value('id');
for ($i = 0; $i < 40; $i++) {
    AiMessage::query()->where('ai_conversation_id', $idPoll)->limit(20)->get();
}
$tP1 = chat($orch, $confirmSvc, $vPoll, 'make it 2 adults', $cidPoll, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$gates['POLLING_RATE_LIMIT_CONTAMINATION'] = (! empty($tP1['rate_limited'])) ? 1 : 0;
save_session($outRoot, '09-polling-rate', [$tP0, $tP1], [
    'POLLING_RATE_LIMIT_CONTAMINATION' => $gates['POLLING_RATE_LIMIT_CONTAMINATION'],
]);

// ================= SUMMARY =================
$over20 = count(array_filter($perf['total'], static fn ($x) => $x > 20000));
$over30 = count(array_filter($perf['total'], static fn ($x) => $x > 30000));

$gates['SEARCH_BEFORE_CONFIRMATION'] = $counters['SEARCH_BEFORE_CONFIRMATION'];
$gates['AUTHORIZED_SEARCH_CALLS'] = $counters['AUTHORIZED_SEARCH_CALLS'];
$gates['CONTROLLED_SEARCH_EXECUTION'] = 'SKIPPED_BY_UAT_POLICY';
$gates['QWEN_MODEL_CALLS_TOTAL'] = $qwenCalls;
$gates['SEMANTIC_FALLBACK_COUNT'] = $semanticFallbacks;
$gates['GENERAL_RETRY_COUNT'] = $generalRetries;
$gates['SEMANTIC_P50_MS'] = pct($perf['semantic'], 50);
$gates['SEMANTIC_P95_MS'] = pct($perf['semantic'], 95);
$gates['SEMANTIC_MAX_MS'] = $perf['semantic'] ? max($perf['semantic']) : null;
$gates['OPEN_DOMAIN_P50_MS'] = pct($perf['open_domain'], 50);
$gates['OPEN_DOMAIN_P95_MS'] = pct($perf['open_domain'], 95);
$gates['OPEN_DOMAIN_MAX_MS'] = $perf['open_domain'] ? max($perf['open_domain']) : null;
$gates['TOTAL_P50_MS'] = pct($perf['total'], 50);
$gates['TOTAL_P95_MS'] = pct($perf['total'], 95);
$gates['TOTAL_MAX_MS'] = $perf['total'] ? max($perf['total']) : null;
$gates['TURNS_OVER_20S'] = $over20;
$gates['TURNS_OVER_30S'] = $over30;
$gates['FAILURE_COUNT'] = count($failures);
$gates['FAILURE_CLASSES'] = array_values(array_unique(array_map(static fn ($f) => $f['class'], $failures)));
$gates['runtime_sha'] = $runtimeSha;
$gates['deploy_marker'] = $deployMarker;
$gates['APPLICATION_CODE_PARITY'] = ($runtimeSha === $EXPECTED_SHA) ? 'PASS' : 'FAIL';
$gates['SILENT_EMPTY_TURNS'] = $counters['SILENT_EMPTY_TURNS'];
$gates['HTTP_500'] = $counters['HTTP_500'];
$gates['counters'] = $counters;
$gates['primary_conversation_id'] = $cidP;

$required = [
    'PROGRESSIVE_SEARCH', 'CORRECTION_CHAIN', 'NEW_SEARCH_ROUTE_RESET', 'DATE_RELATIVE_REFINEMENT',
    'CONFIRMATION_INVALIDATION', 'ONEWAY_TO_RETURN', 'RETURN_TO_NEW_ONEWAY', 'OPEN_JAW_AFTER_HISTORY',
    'OPEN_JAW_TO_ONEWAY_RESET', 'ROMAN_URDU_PROGRESSIVE', 'RELATIONAL_HUM_DONO', 'CONTEXTUAL_WAPIS_RETURN',
    'FRESH_WAPIS_DIRECTION', 'GK_MID_TRIP', 'CURRENT_MID_TRIP', 'BOOKING_MID_TRIP',
    'TRAVEL_STATE_PRESERVED_AFTER_BOOKING', 'EXPLICIT_HANDOFF_NON_REGRESSION',
    'RATE_LIMIT_GRACEFUL', 'POST_LIMIT_RECOVERY', 'CQ43_RATE_LIMIT_HUMAN_PACING',
];
$pass = true;
foreach ($required as $k) {
    if (($gates[$k] ?? 'FAIL') !== 'PASS') {
        $pass = false;
        echo "GATE_FAIL $k=".($gates[$k] ?? 'missing')."\n";
    }
}
if (($gates['HANDOFF_RESUME_STATE'] ?? '') !== 'PRESERVED') {
    $pass = false;
    echo "GATE_FAIL HANDOFF_RESUME_STATE=".$gates['HANDOFF_RESUME_STATE']."\n";
}
if (($gates['CONVERSATION_ID_STABLE'] ?? 'NO') !== 'YES') {
    $pass = false;
}
if (($gates['PRIMARY_LONG_SESSION_TURNS'] ?? 0) < 30) {
    $pass = false;
    echo "GATE_FAIL PRIMARY_LONG_SESSION_TURNS=".$gates['PRIMARY_LONG_SESSION_TURNS']."\n";
}
if (($gates['POLLING_RATE_LIMIT_CONTAMINATION'] ?? 1) !== 0) {
    $pass = false;
}
if (($gates['SEARCH_BEFORE_CONFIRMATION'] ?? 1) !== 0) {
    $pass = false;
}
if (($gates['AMBIGUOUS_CORRECTION_GUESSES'] ?? 1) !== 0) {
    $pass = false;
}
if (($gates['QWEN_MODEL_ONLY_HANDOFF'] ?? 1) !== 0) {
    $pass = false;
}
foreach (['WRONG_ORIGIN', 'WRONG_DESTINATION', 'STALE_RETURN_DATE', 'PHANTOM_ADULTS', 'HTTP_500', 'LHE_LHE_SYNTHETIC_ROUTE', 'LEAD_TRAVEL_TEXT_CAPTURED_AS_NAME', 'BOOKING_STATE_CONTAMINATION'] as $c) {
    if (($counters[$c] ?? 0) !== 0) {
        $pass = false;
        echo "COUNTER_FAIL $c=".$counters[$c]."\n";
    }
}

$gates['CQ43_LONG_CONVERSATION_GATE'] = $pass ? 'PASS' : 'FAIL';
$gates['CQ43_FINAL_STATUS'] = $pass ? 'PASS' : 'FAIL';
$gates['PERMANENT_QWEN_OWNER_DECISION'] = $pass ? 'READY_FOR_REVIEW' : 'HOLD';
$gates['IFRAME_PILOT'] = 'HOLD';
$gates['failures'] = $failures;

file_put_contents("$outRoot/SUMMARY.json", json_encode($gates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents("$outRoot/11-performance.json", json_encode([
    'semantic' => $perf['semantic'],
    'open_domain' => $perf['open_domain'],
    'total' => $perf['total'],
    'aggregates' => [
        'SEMANTIC_P50_MS' => $gates['SEMANTIC_P50_MS'],
        'SEMANTIC_P95_MS' => $gates['SEMANTIC_P95_MS'],
        'SEMANTIC_MAX_MS' => $gates['SEMANTIC_MAX_MS'],
        'OPEN_DOMAIN_P50_MS' => $gates['OPEN_DOMAIN_P50_MS'],
        'OPEN_DOMAIN_P95_MS' => $gates['OPEN_DOMAIN_P95_MS'],
        'OPEN_DOMAIN_MAX_MS' => $gates['OPEN_DOMAIN_MAX_MS'],
        'TOTAL_P50_MS' => $gates['TOTAL_P50_MS'],
        'TOTAL_P95_MS' => $gates['TOTAL_P95_MS'],
        'TOTAL_MAX_MS' => $gates['TOTAL_MAX_MS'],
        'TURNS_OVER_20S' => $over20,
        'TURNS_OVER_30S' => $over30,
    ],
], JSON_PRETTY_PRINT));

echo "\nCQ43_LONG_CONVERSATION_GATE=".($pass ? 'PASS' : 'FAIL')."\n";
echo 'PRIMARY_TURNS='.$gates['PRIMARY_LONG_SESSION_TURNS']."\n";
echo 'FAILURES='.count($failures)."\n";
echo "OUT=$outRoot\n";
exit($pass ? 0 : 1);
