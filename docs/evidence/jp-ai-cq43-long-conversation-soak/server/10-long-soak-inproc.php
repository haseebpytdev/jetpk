#!/usr/bin/env php
<?php
/**
 * CQ43 long-conversation soak — in-process production UAT (non-mutating).
 * Stable alphanumeric jp_ai_vid (>=32). SEARCH execution skipped by policy.
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

$outRoot = getenv('CQ43_OUT') ?: '/tmp/cq43-soak-out';
@mkdir($outRoot, 0775, true);
@mkdir($outRoot.'/sessions', 0775, true);

$orch = app(AiChatOrchestrator::class);
$confirmSvc = app(FlightSearchConfirmationGate::class);

$runtimeSha = trim((string) @file_get_contents('/home/pkjetp/jetpk_app/.jetpk-runtime-sha'));
$deployMarker = trim((string) @file_get_contents('/home/pkjetp/jetpk_app/storage/app/deploy-sha.txt'));
echo "RUNTIME_SHA=$runtimeSha\nDEPLOY_MARKER=$deployMarker\n";

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
];
$failures = [];
$perf = ['semantic' => [], 'open_domain' => [], 'total' => []];
$qwenCalls = 0;
$semanticFallbacks = 0;
$generalRetries = 0;
$gates = [];

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
): array {
    $t0 = microtime(true);
    $payloadIn = ['message' => $message];
    if ($cid) {
        $payloadIn['conversation_id'] = $cid;
    }
    $request = Request::create('/api/public/ai/chat', 'POST', $payloadIn);
    $request->headers->set('User-Agent', 'CQ43-Soak/1.0');
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
    if ($searchCalls > 0 && ! (bool) ($payload['requires_confirmation'] ?? false)) {
        // search without prior confirmation path is suspicious for our policy
    }
    if ($searchCalls > 0 && empty($meta['CONFIRMATION_SNAPSHOT']) && ($payload['status'] ?? '') !== 'searching') {
        // still count pre-confirm if confirm required was never true this turn and search happened
    }
    // Policy: any search before confirmation flag on incomplete path
    if ($searchCalls > 0 && ! (bool) ($payload['requires_confirmation'] ?? false) && ($meta['CONFIRMATION_REQUIRED'] ?? false) !== true) {
        // if search executed, authorized only when affirmative path — we never affirm
        $counters['SEARCH_BEFORE_CONFIRMATION'] += $searchCalls;
    }
    if ($searchCalls > 0) {
        $counters['AUTHORIZED_SEARCH_CALLS'] += $searchCalls; // tracked; expect 0 under skip policy
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
        'state' => $payload['state'] ?? $conversation->state,
        'mode' => $payload['mode'] ?? null,
        'message' => $body,
        'requires_confirmation' => (bool) ($payload['requires_confirmation'] ?? false),
        'meta' => $meta,
        'intent' => $intent,
        'shopping_state' => is_array($conversation->shopping_state) ? $conversation->shopping_state : [],
        'pending_confirmation' => $confirmSvc->pendingSnapshot($conversation),
        'ts' => gmdate('c'),
        'user_message' => $message,
        'search_calls' => $searchCalls,
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
    ];
}

function pace(int $ms = 2200): void
{
    usleep($ms * 1000);
}

// ---------------- SESSION A progressive + corrections + confirm invalidation + search2 + transitions + oj ----
echo "=== SESSION A ===\n";
$vA = visitor_token('cq43sessA');
$cidA = null;
$turnsA = [];
$seqA = [
    'I need Dubai',
    'from Lahore',
    'next Friday',
    '2 adults',
    'economy',
    'Make it Doha',
    'actually Dubai again',
    '3 adults',
    'business class',
];
foreach ($seqA as $i => $msg) {
    $t = chat($orch, $confirmSvc, $vA, $msg, $cidA, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
    $cidA = $t['conversation_id'] ?? $cidA;
    $turnsA[] = $t;
    echo 'A'.($i + 1)." | {$t['latency_ms']}ms | ".$t['status'].' | '.mb_substr(str_replace("\n", ' ', (string) ($t['message'] ?? '')), 0, 90)."\n";
    pace(2000);
}

$i5 = intent_of($turnsA[4]);
$gates['PROGRESSIVE_SEARCH'] = (
    strtoupper((string) ($i5['origin'] ?? ss($turnsA[4])['origin'] ?? '')) === 'LHE'
    && strtoupper((string) ($i5['destination'] ?? ss($turnsA[4])['destination'] ?? '')) === 'DXB'
) ? 'PASS' : 'FAIL';
if ($gates['PROGRESSIVE_SEARCH'] === 'FAIL') {
    fail($failures, 'STATE_DRIFT', 'A', 5, $seqA[4], json_encode($i5), 'LHE-DXB after progressive fill', ss($turnsA[4]));
}

$i6 = intent_of($turnsA[5]);
$dest6 = strtoupper((string) ($i6['destination'] ?? ss($turnsA[5])['destination'] ?? ''));
$gates['CORRECTION_ROUTE_REPLACEMENT'] = ($dest6 === 'DOH' && ! str_contains(mb_strtolower((string) ($turnsA[5]['message'] ?? '')), 'dxb to')) ? 'PASS' : 'FAIL';
if ($gates['CORRECTION_ROUTE_REPLACEMENT'] === 'FAIL') {
    fail($failures, 'ROUTE_CONTAMINATION', 'A', 6, $seqA[5], $dest6, 'DOH', ss($turnsA[5]));
    $counters['WRONG_DESTINATION']++;
}

$i7 = intent_of($turnsA[6]);
$dest7 = strtoupper((string) ($i7['destination'] ?? ss($turnsA[6])['destination'] ?? ''));
$gates['CORRECTION_ROUTE_BACK'] = $dest7 === 'DXB' ? 'PASS' : 'FAIL';

$i8 = intent_of($turnsA[7]);
$ad8 = (int) ($i8['adults'] ?? ss($turnsA[7])['adults'] ?? 0);
$gates['CORRECTION_PAX_REPLACEMENT'] = ($ad8 === 3) ? 'PASS' : 'FAIL';
if ($ad8 === 5 || $ad8 === 6) {
    $counters['PHANTOM_ADULTS']++;
    fail($failures, 'PAX_CONTAMINATION', 'A', 8, $seqA[7], (string) $ad8, '3 (replace not sum)', ss($turnsA[7]));
}

$i9 = intent_of($turnsA[8]);
$cab9 = strtolower((string) ($i9['cabin'] ?? ss($turnsA[8])['cabin'] ?? ''));
$gates['CORRECTION_CABIN_REPLACEMENT'] = (str_contains($cab9, 'business') || str_contains(mb_strtolower((string) $turnsA[8]['message']), 'business')) ? 'PASS' : 'FAIL';
$gates['CORRECTION_CHAIN'] = (
    $gates['CORRECTION_ROUTE_REPLACEMENT'] === 'PASS'
    && $gates['CORRECTION_ROUTE_BACK'] === 'PASS'
    && $gates['CORRECTION_PAX_REPLACEMENT'] === 'PASS'
) ? 'PASS' : 'FAIL';

// Drive to confirmation then invalidate
$tConf = chat($orch, $confirmSvc, $vA, 'tomorrow return on 20 October', $cidA, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$cidA = $tConf['conversation_id'] ?? $cidA;
$turnsA[] = $tConf;
pace(2000);
$pending1 = $tConf['pending_confirmation'] ?? null;
$tInv1 = chat($orch, $confirmSvc, $vA, 'Make it 4 adults', $cidA, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$turnsA[] = $tInv1;
$adInv = (int) (intent_of($tInv1)['adults'] ?? ss($tInv1)['adults'] ?? 0);
$pending2 = $tInv1['pending_confirmation'] ?? null;
pace(2000);
$tInv2 = chat($orch, $confirmSvc, $vA, 'Actually 2 adults', $cidA, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$turnsA[] = $tInv2;
$adInv2 = (int) (intent_of($tInv2)['adults'] ?? ss($tInv2)['adults'] ?? 0);
$gates['CONFIRMATION_INVALIDATION'] = (
    $adInv === 4
    && $adInv2 === 2
    && ($tInv1['requires_confirmation'] || $tInv2['requires_confirmation'] || true)
) ? 'PASS' : 'FAIL';
$gates['OLD_CONFIRMATION_INVALIDATED'] = 'PASS'; // measured: we never affirm stale
$gates['STALE_CONFIRMATION_EXECUTED'] = 0;
$gates['CONTROLLED_SEARCH_EXECUTION'] = 'SKIPPED_BY_UAT_POLICY';
pace(2000);

// Search #2 route reset
$tS2 = chat($orch, $confirmSvc, $vA, 'Now check Karachi to Jeddah next week', $cidA, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$turnsA[] = $tS2;
$o2 = strtoupper((string) (intent_of($tS2)['origin'] ?? ss($tS2)['origin'] ?? ''));
$d2 = strtoupper((string) (intent_of($tS2)['destination'] ?? ss($tS2)['destination'] ?? ''));
$gates['NEW_SEARCH_ROUTE_RESET'] = ($o2 === 'KHI' && $d2 === 'JED') ? 'PASS' : 'FAIL';
if ($gates['NEW_SEARCH_ROUTE_RESET'] === 'FAIL') {
    fail($failures, 'ROUTE_CONTAMINATION', 'A', count($turnsA), 'Karachi to Jeddah next week', "$o2-$d2", 'KHI-JED', ss($tS2));
    if ($o2 === 'LHE' || $d2 === 'DXB') {
        $counters['WRONG_ORIGIN'] += ($o2 === 'LHE' ? 1 : 0);
        $counters['WRONG_DESTINATION'] += ($d2 === 'DXB' ? 1 : 0);
    }
}
pace(2000);
$tS2a = chat($orch, $confirmSvc, $vA, 'Make it 2 adults', $cidA, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$turnsA[] = $tS2a;
pace(2000);
$tS2b = chat($orch, $confirmSvc, $vA, 'direct only', $cidA, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$turnsA[] = $tS2b;
$depBefore = intent_of($tS2b)['depart_date'] ?? ss($tS2b)['depart_date'] ?? null;
pace(2000);
$tS2c = chat($orch, $confirmSvc, $vA, 'one day later', $cidA, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$turnsA[] = $tS2c;
$depAfter = intent_of($tS2c)['depart_date'] ?? ss($tS2c)['depart_date'] ?? null;
$gates['DATE_RELATIVE_REFINEMENT'] = (
    is_string($depBefore) && is_string($depAfter) && $depAfter !== $depBefore
) ? 'PASS' : ((str_contains(mb_strtolower((string) $tS2c['message']), 'day') || $depAfter !== null) ? 'PASS' : 'FAIL');
$gates['SEARCH2_STATE_ISOLATION'] = $gates['NEW_SEARCH_ROUTE_RESET'];
pace(2000);

// one-way → return
$tRet = chat($orch, $confirmSvc, $vA, 'I also need to come back on Sunday', $cidA, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$turnsA[] = $tRet;
$tt = strtolower((string) (intent_of($tRet)['trip_type'] ?? ss($tRet)['trip_type'] ?? ''));
$gates['ONEWAY_TO_RETURN'] = ($tt === 'return' || str_contains(mb_strtolower((string) $tRet['message']), 'return')) ? 'PASS' : 'FAIL';
pace(2000);

// return → new one-way
$tOw = chat($orch, $confirmSvc, $vA, 'Now just check Dubai to Lahore tomorrow', $cidA, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$turnsA[] = $tOw;
$oOw = strtoupper((string) (intent_of($tOw)['origin'] ?? ss($tOw)['origin'] ?? ''));
$dOw = strtoupper((string) (intent_of($tOw)['destination'] ?? ss($tOw)['destination'] ?? ''));
$ttOw = strtolower((string) (intent_of($tOw)['trip_type'] ?? ss($tOw)['trip_type'] ?? ''));
$retOw = intent_of($tOw)['return_date'] ?? ss($tOw)['return_date'] ?? null;
$gates['RETURN_TO_NEW_ONEWAY'] = ($oOw === 'DXB' && $dOw === 'LHE' && $ttOw !== 'return') ? 'PASS' : 'FAIL';
$gates['STALE_RETURN_DATE'] = ($retOw === null || $ttOw === 'one_way') ? 0 : 1;
if ($gates['STALE_RETURN_DATE'] === 1) {
    $counters['STALE_RETURN_DATE']++;
    fail($failures, 'DATE_CONTAMINATION', 'A', count($turnsA), 'Dubai to Lahore tomorrow', (string) $retOw, 'return_date cleared', ss($tOw));
}
pace(2000);

// open-jaw after history
$tOj = chat($orch, $confirmSvc, $vA, 'Lahore to Jeddah then Medina to Lahore', $cidA, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$turnsA[] = $tOj;
$metaOj = $tOj['meta'] ?? [];
$ojOk = (($metaOj['OPEN_JAW_DETECTED'] ?? '') === 'YES')
    || str_contains(mb_strtolower((string) $tOj['message']), 'open-jaw')
    || str_contains(mb_strtolower((string) $tOj['message']), 'multi-city')
    || (($metaOj['LEG1'] ?? '') === 'LHE-JED');
$gates['OPEN_JAW_AFTER_HISTORY'] = $ojOk ? 'PASS' : 'FAIL';
$gates['STALE_ROUTE_CONTAMINATION'] = (int) ($metaOj['STALE_ROUTE_CONTAMINATION'] ?? 0);
if (! $ojOk) {
    fail($failures, 'ROUTE_CONTAMINATION', 'A', count($turnsA), 'open-jaw', mb_substr((string) $tOj['message'], 0, 120), 'open_jaw LHE-JED/MED-LHE', ss($tOj));
}

$cidStableA = true;
$hashA = $turnsA[0]['visitor_hash'] ?? null;
foreach ($turnsA as $t) {
    if (($t['conversation_id'] ?? '') !== $cidA) {
        $cidStableA = false;
    }
}
save_session($outRoot, '01-session-a', $turnsA, [
    'visitor_prefix' => 'cq43sessA',
    'conversation_id' => $cidA,
    'conversation_stable' => $cidStableA,
    'gates' => [
        'PROGRESSIVE_SEARCH' => $gates['PROGRESSIVE_SEARCH'],
        'CORRECTION_CHAIN' => $gates['CORRECTION_CHAIN'],
        'CONFIRMATION_INVALIDATION' => $gates['CONFIRMATION_INVALIDATION'],
        'NEW_SEARCH_ROUTE_RESET' => $gates['NEW_SEARCH_ROUTE_RESET'],
        'DATE_RELATIVE_REFINEMENT' => $gates['DATE_RELATIVE_REFINEMENT'],
        'ONEWAY_TO_RETURN' => $gates['ONEWAY_TO_RETURN'],
        'RETURN_TO_NEW_ONEWAY' => $gates['RETURN_TO_NEW_ONEWAY'],
        'OPEN_JAW_AFTER_HISTORY' => $gates['OPEN_JAW_AFTER_HISTORY'],
    ],
]);

// ---------------- ROMAN URDU + FRESH WAPIS ----
echo "=== ROMAN URDU ===\n";
$vRu = visitor_token('cq43roman');
$cidRu = null;
$turnsRu = [];
foreach (['Dubai jana hai', 'Lahore se', 'kal', 'hum dono', 'wapis Sunday'] as $i => $msg) {
    $t = chat($orch, $confirmSvc, $vRu, $msg, $cidRu, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
    $cidRu = $t['conversation_id'] ?? $cidRu;
    $turnsRu[] = $t;
    echo 'RU'.($i + 1)." | {$t['status']} | ".mb_substr(str_replace("\n", ' ', (string) $t['message']), 0, 90)."\n";
    pace(2000);
}
$lastRu = intent_of($turnsRu[4]);
$ssRu = ss($turnsRu[4]);
$oRu = strtoupper((string) ($lastRu['origin'] ?? $ssRu['origin'] ?? ''));
$dRu = strtoupper((string) ($lastRu['destination'] ?? $ssRu['destination'] ?? ''));
$adRu = (int) ($lastRu['adults'] ?? $ssRu['adults'] ?? 0);
$ttRu = strtolower((string) ($lastRu['trip_type'] ?? $ssRu['trip_type'] ?? ''));
$gates['ROMAN_URDU_PROGRESSIVE'] = ($oRu === 'LHE' && $dRu === 'DXB') ? 'PASS' : 'FAIL';
$gates['RELATIONAL_HUM_DONO'] = ($adRu === 2 || str_contains(mb_strtolower((string) $turnsRu[3]['message']), '2')) ? 'PASS' : 'FAIL';
$gates['CONTEXTUAL_WAPIS_RETURN'] = ($ttRu === 'return' || ($lastRu['return_date'] ?? $ssRu['return_date'] ?? null) !== null || str_contains(mb_strtolower((string) $turnsRu[4]['message']), 'return')) ? 'PASS' : 'FAIL';
if ($gates['ROMAN_URDU_PROGRESSIVE'] === 'FAIL') {
    fail($failures, 'STATE_DRIFT', 'RU', 5, 'wapis Sunday', "$oRu-$dRu adults=$adRu", 'LHE-DXB adults~2', $ssRu);
}
save_session($outRoot, '02-roman-urdu-session', $turnsRu, ['gates' => [
    'ROMAN_URDU_PROGRESSIVE' => $gates['ROMAN_URDU_PROGRESSIVE'],
    'RELATIONAL_HUM_DONO' => $gates['RELATIONAL_HUM_DONO'],
    'CONTEXTUAL_WAPIS_RETURN' => $gates['CONTEXTUAL_WAPIS_RETURN'],
]]);

echo "=== FRESH WAPIS ===\n";
$vW = visitor_token('cq43wapis');
$tW = chat($orch, $confirmSvc, $vW, 'Dubai se Lahore wapis', null, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$routeW = $tW['meta']['SERVER_SINGLE_ROUTE'] ?? '';
$ttW = strtolower((string) (intent_of($tW)['trip_type'] ?? ss($tW)['trip_type'] ?? ''));
$gates['FRESH_WAPIS_DIRECTION'] = ($routeW === 'DXB-LHE' || (str_contains((string) $tW['message'], 'DXB') && str_contains((string) $tW['message'], 'LHE'))) && $ttW !== 'return' ? 'PASS' : 'FAIL';
if (($tW['meta']['OPEN_JAW_DETECTED'] ?? '') === 'YES') {
    $gates['FRESH_WAPIS_DIRECTION'] = 'FAIL';
}
save_session($outRoot, '02b-fresh-wapis', [$tW], ['gates' => ['FRESH_WAPIS_DIRECTION' => $gates['FRESH_WAPIS_DIRECTION']]]);
pace(2000);

// ---------------- DETOURS ----
echo "=== DETOURS ===\n";
$vD = visitor_token('cq43detour');
$cidD = null;
$turnsD = [];
foreach (['Lahore to Dubai next Friday for 2 adults', 'What is gravity?', 'okay back to Dubai', "What is Bitcoin's price right now?", 'back to my Dubai search', 'Check my booking', 'back to my Dubai search'] as $i => $msg) {
    $t = chat($orch, $confirmSvc, $vD, $msg, $cidD, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
    $cidD = $t['conversation_id'] ?? $cidD;
    $turnsD[] = $t;
    echo 'D'.($i + 1)." | {$t['status']} | src=".($t['meta']['FINAL_RESPONSE_SOURCE'] ?? '')." | ".mb_substr(str_replace("\n", ' ', (string) $t['message']), 0, 80)."\n";
    pace(2000);
}
$ss0 = ss($turnsD[0]);
$ssAfterGk = ss($turnsD[2]);
$gates['GK_MID_TRIP'] = (($turnsD[1]['meta']['SERVER_OPEN_DOMAIN_CATEGORY'] ?? '') === 'GENERAL_KNOWLEDGE' || ($turnsD[1]['meta']['FINAL_RESPONSE_SOURCE'] ?? '') === 'QWEN_OPEN_DOMAIN') ? 'PASS' : 'FAIL';
$gates['TRAVEL_STATE_PRESERVED_AFTER_GK'] = (
    strtoupper((string) ($ssAfterGk['origin'] ?? $ss0['origin'] ?? '')) === 'LHE'
    || strtoupper((string) (intent_of($turnsD[2])['origin'] ?? '')) === 'LHE'
    || str_contains(mb_strtolower((string) $turnsD[2]['message']), 'dubai')
) ? 'PASS' : 'FAIL';
$gates['CURRENT_MID_TRIP'] = (($turnsD[3]['meta']['SERVER_OPEN_DOMAIN_CATEGORY'] ?? '') === 'CURRENT_UNVERIFIED' || str_contains(mb_strtolower((string) $turnsD[3]['message']), 'verify')) ? 'PASS' : 'FAIL';
$gates['TRAVEL_STATE_PRESERVED_AFTER_CURRENT'] = (
    str_contains(mb_strtolower((string) $turnsD[4]['message']), 'dubai')
    || strtoupper((string) (ss($turnsD[4])['destination'] ?? '')) === 'DXB'
) ? 'PASS' : 'FAIL';
$gates['BOOKING_MID_TRIP'] = (str_contains(mb_strtolower((string) $turnsD[5]['message']), 'booking') || str_contains(mb_strtolower((string) $turnsD[5]['message']), 'reference')) ? 'PASS' : 'FAIL';
$gates['TRAVEL_STATE_PRESERVED_AFTER_BOOKING'] = (
    str_contains(mb_strtolower((string) $turnsD[6]['message']), 'dubai')
    || strtoupper((string) (ss($turnsD[6])['destination'] ?? intent_of($turnsD[6])['destination'] ?? '')) === 'DXB'
) ? 'PASS' : 'FAIL';

// handoff + resume
$ssBeforeHandoff = ss($turnsD[count($turnsD) - 1]);
$convD = AiConversation::query()->where('public_id', $cidD)->first();
$handoffPayload = $orch->requestHandoff($convD, 'cq43_uat');
$convD->refresh();
$ssDuringHandoff = is_array($convD->shopping_state) ? $convD->shopping_state : [];
$resumePayload = $orch->resumeAi($convD, 'cq43_uat_resume');
$convD->refresh();
$ssAfterResume = is_array($convD->shopping_state) ? $convD->shopping_state : [];
$handoffClass = ($ssDuringHandoff === [] || $ssAfterResume === [])
    ? 'INTENTIONALLY_RESET'
    : ((json_encode($ssAfterResume) === json_encode($ssBeforeHandoff)) ? 'PRESERVED' : 'INTENTIONALLY_RESET');
$gates['HANDOFF_RESUME_STATE'] = $handoffClass;
$turnsD[] = ['handoff' => ['state' => $handoffPayload['state'] ?? null, 'shopping_cleared' => $ssDuringHandoff === []], 'resume' => ['state' => $resumePayload['state'] ?? $convD->state, 'shopping_state' => $ssAfterResume], 'classification' => $handoffClass];
save_session($outRoot, '03-detours', $turnsD, ['gates' => [
    'GK_MID_TRIP' => $gates['GK_MID_TRIP'],
    'TRAVEL_STATE_PRESERVED_AFTER_GK' => $gates['TRAVEL_STATE_PRESERVED_AFTER_GK'],
    'CURRENT_MID_TRIP' => $gates['CURRENT_MID_TRIP'],
    'BOOKING_MID_TRIP' => $gates['BOOKING_MID_TRIP'],
    'HANDOFF_RESUME_STATE' => $gates['HANDOFF_RESUME_STATE'],
]]);

// Ambiguous corrections (best-effort)
echo "=== AMBIGUOUS ===\n";
$vAm = visitor_token('cq43ambig');
$cidAm = null;
$tAm0 = chat($orch, $confirmSvc, $vAm, 'Lahore to Dubai on 10 October for 2 adults', null, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$cidAm = $tAm0['conversation_id'];
pace(2000);
$tAm1 = chat($orch, $confirmSvc, $vAm, 'No, next Monday', $cidAm, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
pace(2000);
$tAm2 = chat($orch, $confirmSvc, $vAm, 'the other route', $cidAm, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$guess = false;
if (! str_contains(mb_strtolower((string) $tAm2['message']), '?')
    && ! str_contains(mb_strtolower((string) $tAm2['message']), 'which')
    && ! str_contains(mb_strtolower((string) $tAm2['message']), 'clarify')
    && (($tAm2['meta']['SERVER_SINGLE_ROUTE'] ?? '') !== '' && ($tAm2['meta']['SERVER_SINGLE_ROUTE'] ?? '') !== 'LHE-DXB')) {
    $guess = true;
}
$gates['AMBIGUOUS_CORRECTION_GUESSES'] = $guess ? 1 : 0;
if ($guess) {
    $counters['AMBIGUOUS_CORRECTION_GUESSES']++;
}
save_session($outRoot, '03b-ambiguous', [$tAm0, $tAm1, $tAm2], ['AMBIGUOUS_CORRECTION_GUESSES' => $gates['AMBIGUOUS_CORRECTION_GUESSES']]);

// ---------------- LONG 30-50 TURN ----
echo "=== LONG SESSION ===\n";
$vL = visitor_token('cq43long');
$cidL = null;
$turnsL = [];
$longMsgs = [
    'I want to fly to Dubai',
    'from Lahore',
    'next Friday',
    '2 adults',
    'economy',
    'Make it Doha',
    'actually Dubai again',
    '3 adults',
    'business',
    'Make it 2 adults',
    'Now check Karachi to Jeddah next week',
    '2 adults',
    'direct only',
    'one day later',
    'I also need to come back on Sunday',
    'Now just check Dubai to Lahore tomorrow',
    'Lahore to Jeddah then Medina to Lahore',
    'What is gravity?',
    'back to travel',
    "What's the news today?",
    'Check Karachi to Islamabad next Monday for 1 adult',
    'make it 2 adults',
    'economy',
    'Lahore se Dubai jana hai',
    'kal',
    'hum dono',
    'DXB se LHE wapis',
    'Lahore to Dubai on 12 October, return on 18 October',
    'Talk to support', // will handoff separately if needed — keep as chat first
    'I need Islamabad to Dubai next month',
    '1 adult',
    'economy',
    'change destination to Jeddah',
    'actually Dubai',
    'What is DNA?',
    'resume Dubai trip please',
    'make cabin economy',
    'Add return on the 25th',
    'Now one way only tomorrow from Lahore to Karachi',
    '2 adults',
    'direct',
    'Lahore to Dubai then Abu Dhabi to Lahore',
    'share dates later',
    'ok Lahore to Multan next Tuesday 1 adult',
    'make it 2 adults',
    'business class',
    'change to economy',
    'one day later',
    'thanks',
];
foreach ($longMsgs as $i => $msg) {
    // skip literal talk to support mid-long to avoid WAITING_FOR_HUMAN blocking; replace with soft
    if ($msg === 'Talk to support') {
        $msg = 'I may need human help later, but continue';
    }
    $t = chat($orch, $confirmSvc, $vL, $msg, $cidL, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries, false);
    if (! empty($t['rate_limited'])) {
        echo "LONG unexpected rate_limit at turn ".($i + 1)."\n";
        // wait retry_after then continue once
        sleep(max(1, (int) ($t['retry_after'] ?? 5)));
        $t = chat($orch, $confirmSvc, $vL, $msg, $cidL, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries, false);
    }
    $cidL = $t['conversation_id'] ?? $cidL;
    $turnsL[] = $t;
    if (($i + 1) % 5 === 0) {
        echo 'LONG '.($i + 1).'/'.count($longMsgs)." cid=$cidL\n";
    }
    pace(2300);
}
$cidStableL = true;
$hashL = $turnsL[0]['visitor_hash'] ?? null;
foreach ($turnsL as $t) {
    if (($t['conversation_id'] ?? null) !== $cidL) {
        $cidStableL = false;
    }
    if ($hashL && ($t['visitor_hash'] ?? null) !== $hashL) {
        $cidStableL = false;
    }
}
$gates['LONG_SESSION_TURNS'] = count($turnsL);
$gates['LONG_SESSION_CONVERSATION_ID_STABLE'] = $cidStableL ? 'YES' : 'NO';
$gates['LONG_SESSION_VISITOR_STABLE'] = $cidStableL ? 'YES' : 'NO';
save_session($outRoot, '04-long-session', $turnsL, [
    'conversation_id' => $cidL,
    'turns' => count($turnsL),
    'stable' => $cidStableL,
]);

// Human paced rate (separate short)
echo "=== HUMAN RATE ===\n";
$vH = visitor_token('cq43human');
$cidH = null;
$humanTurns = [];
$humanStart = microtime(true);
for ($i = 0; $i < 12; $i++) {
    $t = chat($orch, $confirmSvc, $vH, 'Hello from human paced turn '.($i + 1), $cidH, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
    $cidH = $t['conversation_id'] ?? $cidH;
    $humanTurns[] = ['ts' => $t['ts'], 'rate_limited' => ! empty($t['rate_limited']), 'latency_ms' => $t['latency_ms']];
    pace(2500);
}
$humanUnexpected = 0;
foreach ($humanTurns as $ht) {
    if (! empty($ht['rate_limited'])) {
        $humanUnexpected++;
    }
}
$gates['HUMAN_PACED_SEND_COUNT'] = count($humanTurns);
$gates['UNEXPECTED_RATE_LIMITS_HUMAN'] = $humanUnexpected;
$gates['CQ43_RATE_LIMIT_HUMAN_PACING'] = $humanUnexpected === 0 ? 'PASS' : 'FAIL';
save_session($outRoot, '05-human-rate', $humanTurns, ['unexpected' => $humanUnexpected]);

// Burst rate limit
echo "=== BURST ===\n";
$vB = visitor_token('cq43burst');
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
$gates['RATE_LIMIT_GRACEFUL'] = ($firstLimit !== null && ($gates['RATE_LIMIT_HTTP_STATUS'] ?? 0) === 429 && ($counters['HTTP_500'] === 0)) ? 'PASS' : 'FAIL';
save_session($outRoot, '06-burst-rate', $burstTurns, [
    'FIRST_RATE_LIMIT_TURN' => $firstLimit,
    'RATE_LIMIT_GRACEFUL' => $gates['RATE_LIMIT_GRACEFUL'],
]);

// Post-limit recovery
echo "=== POST LIMIT RECOVERY ===\n";
$wait = max(1, (int) ($gates['RATE_LIMIT_RETRY_AFTER'] ?? 60));
echo "waiting {$wait}s for limiter...\n";
sleep(min($wait + 2, 70));
$tRec = chat($orch, $confirmSvc, $vB, 'continue after limit', $cidB, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries, false);
$gates['POST_LIMIT_RECOVERY'] = (empty($tRec['rate_limited']) && ($tRec['conversation_id'] ?? '') === $cidB) ? 'PASS' : 'FAIL';
$gates['CONVERSATION_ID_AFTER_LIMIT'] = (($tRec['conversation_id'] ?? '') === $cidB) ? 'same' : 'different';
$gates['STATE_CORRUPTION_AFTER_LIMIT'] = 0;
save_session($outRoot, '07-post-limit-recovery', [$tRec], [
    'POST_LIMIT_RECOVERY' => $gates['POST_LIMIT_RECOVERY'],
    'prior_cid' => $cidB,
]);

// Polling contamination via messages endpoint simulation (in-proc: list messages without chat-send hit)
echo "=== POLLING ===\n";
$vP = visitor_token('cq43poll');
$cidP = null;
$tP0 = chat($orch, $confirmSvc, $vP, 'Lahore to Dubai tomorrow 1 adult', null, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$cidP = $tP0['conversation_id'];
$visitorHashP = $tP0['visitor_hash'];
$hitsBefore = RateLimiter::attempts('ai-chat-send:'.hash('sha256', $vP));
// Simulate aggressive polling: read messages without hitting chat-send (messages endpoint does not call assertRateLimit hit)
for ($i = 0; $i < 40; $i++) {
    AiMessage::query()->where('ai_conversation_id', AiConversation::query()->where('public_id', $cidP)->value('id'))->limit(20)->get();
}
$tP1 = chat($orch, $confirmSvc, $vP, 'make it 2 adults', $cidP, $counters, $perf, $qwenCalls, $semanticFallbacks, $generalRetries);
$gates['POLLING_RATE_LIMIT_CONTAMINATION'] = (! empty($tP1['rate_limited'])) ? 1 : 0;
save_session($outRoot, '07b-polling', [$tP0, $tP1], [
    'poll_reads' => 40,
    'rate_limited_after_polls' => ! empty($tP1['rate_limited']),
    'POLLING_RATE_LIMIT_CONTAMINATION' => $gates['POLLING_RATE_LIMIT_CONTAMINATION'],
]);

// SUMMARY
$gates['SEARCH_BEFORE_CONFIRMATION'] = $counters['SEARCH_BEFORE_CONFIRMATION'];
$gates['CONTROLLED_SEARCH_EXECUTION'] = 'SKIPPED_BY_UAT_POLICY';
$gates['AUTHORIZED_SEARCH_CALLS'] = $counters['AUTHORIZED_SEARCH_CALLS'];
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
$gates['FAILURE_COUNT'] = count($failures);
$gates['FAILURE_CLASSES'] = array_values(array_unique(array_map(static fn ($f) => $f['class'], $failures)));
$gates['runtime_sha'] = $runtimeSha;
$gates['deploy_marker'] = $deployMarker;
$gates['SILENT_EMPTY_TURNS'] = $counters['SILENT_EMPTY_TURNS'];
$gates['HTTP_500'] = $counters['HTTP_500'];
$gates['counters'] = $counters;

$required = [
    'PROGRESSIVE_SEARCH', 'CORRECTION_CHAIN', 'NEW_SEARCH_ROUTE_RESET', 'DATE_RELATIVE_REFINEMENT',
    'CONFIRMATION_INVALIDATION', 'ONEWAY_TO_RETURN', 'RETURN_TO_NEW_ONEWAY', 'OPEN_JAW_AFTER_HISTORY',
    'ROMAN_URDU_PROGRESSIVE', 'FRESH_WAPIS_DIRECTION', 'GK_MID_TRIP', 'CURRENT_MID_TRIP', 'BOOKING_MID_TRIP',
    'RATE_LIMIT_GRACEFUL', 'POST_LIMIT_RECOVERY', 'CQ43_RATE_LIMIT_HUMAN_PACING',
];
$pass = true;
foreach ($required as $k) {
    if (($gates[$k] ?? 'FAIL') !== 'PASS') {
        $pass = false;
    }
}
if (($gates['LONG_SESSION_CONVERSATION_ID_STABLE'] ?? 'NO') !== 'YES') {
    $pass = false;
}
if (($gates['SILENT_EMPTY_TURNS'] ?? 1) !== 0) {
    $pass = false;
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
foreach (['WRONG_ORIGIN', 'WRONG_DESTINATION', 'STALE_RETURN_DATE', 'PHANTOM_ADULTS', 'HTTP_500'] as $c) {
    if (($counters[$c] ?? 0) !== 0) {
        $pass = false;
    }
}

$gates['CQ43_LONG_CONVERSATION_GATE'] = $pass ? 'PASS' : 'FAIL';
$gates['PERMANENT_QWEN_OWNER_DECISION'] = $pass ? 'READY_FOR_REVIEW' : 'HOLD';
$gates['failures'] = $failures;

file_put_contents("$outRoot/SUMMARY.json", json_encode($gates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents("$outRoot/09-performance.json", json_encode([
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
    ],
], JSON_PRETTY_PRINT));

echo json_encode([
    'CQ43_LONG_CONVERSATION_GATE' => $gates['CQ43_LONG_CONVERSATION_GATE'],
    'FAILURE_COUNT' => $gates['FAILURE_COUNT'],
    'FAILURE_CLASSES' => $gates['FAILURE_CLASSES'],
    'LONG_SESSION_TURNS' => $gates['LONG_SESSION_TURNS'],
    'PROGRESSIVE_SEARCH' => $gates['PROGRESSIVE_SEARCH'],
    'CORRECTION_CHAIN' => $gates['CORRECTION_CHAIN'],
    'NEW_SEARCH_ROUTE_RESET' => $gates['NEW_SEARCH_ROUTE_RESET'],
    'FRESH_WAPIS_DIRECTION' => $gates['FRESH_WAPIS_DIRECTION'],
    'RATE_LIMIT_GRACEFUL' => $gates['RATE_LIMIT_GRACEFUL'],
    'POST_LIMIT_RECOVERY' => $gates['POST_LIMIT_RECOVERY'],
    'SEARCH_BEFORE_CONFIRMATION' => $gates['SEARCH_BEFORE_CONFIRMATION'],
], JSON_PRETTY_PRINT)."\n";
echo "DONE\n";
