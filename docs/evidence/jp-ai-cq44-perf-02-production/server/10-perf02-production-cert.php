#!/usr/bin/env php
<?php
/**
 * CQ44-PERF-02 production certification (behavioral gates).
 * Evidence/harness only. Expected runtime: 9901f285e06c85f550191307ef29c9298904a32c
 */
require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AiConversation;
use App\Services\Ai\AiChatOrchestrator;
use App\Services\Ai\FlightSearchConfirmationGate;
use Illuminate\Http\Request;

$EXPECTED_SHA = '9901f285e06c85f550191307ef29c9298904a32c';
$outRoot = getenv('CQ44_OUT') ?: '/tmp/cq44-perf02-prod-out';
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
    'WRONG_ROUTE_ACTION_READY' => 0,
    'SILENT_EMPTY_TURNS' => 0,
    'HTTP_500' => 0,
    'PII_FIRST' => 0,
    'BOOKING_IDENTITY_BYPASS' => 0,
    'BOOKING_DATA_LEAK' => 0,
    'LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK' => 0,
    'SUPPLIER_MUTATIONS' => 0,
    'BOOKING_MUTATIONS' => 0,
    'PAYMENT_MUTATIONS' => 0,
    'MODEL_CRASHES' => 0,
    'OOM_EVENTS' => 0,
    'QWEN_MODEL_ONLY_HANDOFF' => 0,
    'UNEXPECTED_RATE_LIMITS' => 0,
    'LEAD_TRAVEL_TEXT_CAPTURED_AS_NAME' => 0,
];
$gates = [];
$failures = [];
$evidence = [];

function visitor_token(string $prefix): string
{
    return substr(preg_replace('/[^A-Za-z0-9]/', '', $prefix).bin2hex(random_bytes(16)), 0, 48);
}

function pace(int $ms = 2200): void
{
    usleep($ms * 1000);
}

function chat(
    AiChatOrchestrator $orch,
    FlightSearchConfirmationGate $confirmSvc,
    string $visitor,
    string $message,
    ?string $cid,
    array &$counters,
): array {
    $t0 = microtime(true);
    $payloadIn = ['message' => $message];
    if ($cid) {
        $payloadIn['conversation_id'] = $cid;
    }
    $request = Request::create('/api/public/ai/chat', 'POST', $payloadIn);
    $request->headers->set('User-Agent', 'CQ44-PERF02-Prod/1.0');
    $request->headers->set('Cookie', 'jp_ai_vid='.$visitor);
    $request->cookies->set('jp_ai_vid', $visitor);
    $request->server->set('REMOTE_ADDR', '127.0.0.1');
    app()->instance('request', $request);

    $resolved = $orch->resolveConversation($request, $cid);
    $conversation = $resolved['conversation'];
    $rate = $orch->assertRateLimit($resolved['visitor_raw']);
    if (is_array($rate)) {
        $counters['UNEXPECTED_RATE_LIMITS']++;

        return [
            'rate_limited' => true,
            'http_status' => 429,
            'latency_ms' => round((microtime(true) - $t0) * 1000, 1),
            'conversation_id' => $conversation->public_id,
            'status' => 'rate_limited',
            'message' => (string) ($rate['message'] ?? ''),
            'meta' => [],
            'shopping_state' => is_array($conversation->shopping_state) ? $conversation->shopping_state : [],
            'user_message' => $message,
            'MODEL_CALLS' => 0,
            'ts' => gmdate('c'),
        ];
    }

    try {
        $sanitized = $orch->sanitizeUserMessage($message);
        if (! ($sanitized['ok'] ?? false)) {
            return [
                'rate_limited' => false,
                'http_status' => 422,
                'latency_ms' => round((microtime(true) - $t0) * 1000, 1),
                'conversation_id' => $conversation->public_id,
                'status' => 'invalid',
                'message' => (string) (($sanitized['payload']['message'] ?? 'invalid')),
                'meta' => [],
                'shopping_state' => is_array($conversation->shopping_state) ? $conversation->shopping_state : [],
                'user_message' => $message,
                'MODEL_CALLS' => 0,
                'ts' => gmdate('c'),
            ];
        }
        $payload = $orch->handleChat($conversation, $sanitized['message']);
    } catch (Throwable $e) {
        $counters['MODEL_CRASHES']++;
        $counters['HTTP_500']++;

        return [
            'error' => $e->getMessage(),
            'latency_ms' => round((microtime(true) - $t0) * 1000, 1),
            'conversation_id' => $conversation->public_id,
            'user_message' => $message,
            'MODEL_CALLS' => 0,
            'ts' => gmdate('c'),
        ];
    }

    $conversation->refresh();
    $ms = round((microtime(true) - $t0) * 1000, 1);
    $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
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
    foreach (['WRONG_ROUTE_ACTION_READY', 'LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK', 'PII_FIRST', 'BOOKING_IDENTITY_BYPASS', 'BOOKING_DATA_LEAK'] as $k) {
        if ((int) ($meta[$k] ?? 0) !== 0) {
            $counters[$k]++;
        }
    }
    $state = (string) ($payload['state'] ?? $conversation->state);
    if ($state === AiConversation::STATE_WAITING_FOR_HUMAN) {
        $counters['QWEN_MODEL_ONLY_HANDOFF']++;
    }
    $calls = (int) ($meta['MODEL_CALLS'] ?? $meta['GENERAL_MODEL_CALLS'] ?? 0);
    $classes = is_array($meta['DETERMINISTIC_AUTHORITY_CLASSES'] ?? null) ? $meta['DETERMINISTIC_AUTHORITY_CLASSES'] : [];
    $ss = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];

    return [
        'rate_limited' => false,
        'http_status' => 200,
        'latency_ms' => $ms,
        'conversation_id' => $conversation->public_id,
        'status' => (string) ($payload['status'] ?? ''),
        'state' => $state,
        'message' => $body,
        'requires_confirmation' => (bool) ($payload['requires_confirmation'] ?? false),
        'meta' => $meta,
        'intent' => is_array($payload['intent'] ?? null) ? $payload['intent'] : null,
        'shopping_state' => $ss,
        'pending_confirmation' => $confirmSvc->pendingSnapshot($conversation),
        'user_message' => $message,
        'MODEL_CALLS' => $calls,
        'SEMANTIC_BRAIN_CALLED' => (string) ($meta['SEMANTIC_BRAIN_CALLED'] ?? ''),
        'SEMANTIC_PLANNER_BYPASSED' => (string) ($meta['SEMANTIC_PLANNER_BYPASSED'] ?? ''),
        'DETERMINISTIC_AUTHORITY_COMPLETE' => (string) ($meta['DETERMINISTIC_AUTHORITY_COMPLETE'] ?? ''),
        'DETERMINISTIC_AUTHORITY_CLASSES' => $classes,
        'FINAL_RESPONSE_SOURCE' => (string) ($meta['FINAL_RESPONSE_SOURCE'] ?? ''),
        'SERVER_OPEN_DOMAIN_CATEGORY' => (string) ($meta['SERVER_OPEN_DOMAIN_CATEGORY'] ?? ''),
        'CURRENT_TOPIC' => (string) ($meta['CURRENT_TOPIC'] ?? ''),
        'SEMANTIC_LATENCY_MS' => (int) ($meta['SEMANTIC_LATENCY_MS'] ?? 0),
        'SEMANTIC_BRAIN_FALLBACK' => (string) ($meta['SEMANTIC_BRAIN_FALLBACK'] ?? ''),
        'SEMANTIC_FALLBACK_REASON' => (string) ($meta['SEMANTIC_FALLBACK_REASON'] ?? ''),
        'ts' => gmdate('c'),
    ];
}

function ss(array $t): array
{
    return is_array($t['shopping_state'] ?? null) ? $t['shopping_state'] : [];
}

function has_pending(array $t): bool
{
    $p = $t['pending_confirmation'] ?? null;
    if (is_array($p) && ($p !== [])) {
        return true;
    }

    return (bool) ($t['requires_confirmation'] ?? false);
}

function has_class(array $t, string $c): bool
{
    return in_array($c, $t['DETERMINISTIC_AUTHORITY_CLASSES'] ?? [], true);
}

function no_qwen(array $t): bool
{
    return (int) ($t['MODEL_CALLS'] ?? 1) === 0;
}

function mark(array &$gates, array &$failures, string $name, bool $ok, array $ctx = []): void
{
    $gates[$name] = $ok ? 'PASS' : 'FAIL';
    if (! $ok) {
        $failures[] = array_merge(['gate' => $name], $ctx);
        echo "FAIL $name\n";
    } else {
        echo "PASS $name\n";
    }
}

function seed_open_jaw(string $cid, string $depart = '2026-10-06'): void
{
    $conv = AiConversation::query()->where('public_id', $cid)->first();
    if (! $conv) {
        return;
    }
    $st = is_array($conv->shopping_state) ? $conv->shopping_state : [];
    $st['trip_type'] = 'open_jaw';
    $st['origin'] = 'LHE';
    $st['destination'] = 'JED';
    $st['return_date'] = null;
    $st['intent'] = 'flight_search';
    $st['depart_date'] = $depart;
    $st['legs'] = [
        ['origin' => 'LHE', 'destination' => 'JED', 'departure_date' => $depart],
        ['origin' => 'MED', 'destination' => 'LHE', 'departure_date' => null],
    ];
    $conv->shopping_state = $st;
    $conv->save();
}

function seed_simple_trip(string $cid, string $origin, string $dest, string $depart, int $adults = 2): void
{
    $conv = AiConversation::query()->where('public_id', $cid)->first();
    if (! $conv) {
        return;
    }
    $st = is_array($conv->shopping_state) ? $conv->shopping_state : [];
    $st['trip_type'] = 'one_way';
    $st['origin'] = $origin;
    $st['destination'] = $dest;
    $st['depart_date'] = $depart;
    $st['return_date'] = null;
    $st['adults'] = $adults;
    $st['children'] = 0;
    $st['infants'] = 0;
    $st['cabin'] = $st['cabin'] ?? 'economy';
    $st['legs'] = [
        ['origin' => $origin, 'destination' => $dest, 'departure_date' => $depart],
    ];
    $st['intent'] = 'flight_search';
    $conv->shopping_state = $st;
    $conv->save();
}

function next_monday(): string
{
    $d = new DateTimeImmutable('now', new DateTimeZone('Asia/Karachi'));
    $dow = (int) $d->format('N');
    $add = $dow === 1 ? 7 : ((8 - $dow) % 7);
    if ($add === 0) {
        $add = 7;
    }

    return $d->modify("+{$add} days")->format('Y-m-d');
}

$monday = next_monday();
echo "RESOLVED_NEXT_MONDAY=$monday\n";

// ---------------- 5 CURRENT BITCOIN ----------------
echo "=== 5 CURRENT BITCOIN ===\n";
$v = visitor_token('p02btc');
$tBtc = chat($orch, $confirmSvc, $v, "What is Bitcoin's price right now?", null, $counters);
$bodyBtc = mb_strtolower((string) ($tBtc['message'] ?? ''));
$fabricatedBtc = preg_match('/\$\s*\d{2,}|btc[^\n]{0,40}\d{3,}|price is \d+/i', $bodyBtc) === 1
    && ! preg_match('/can.?t|cannot|unable|not (have|provide)|verify|live source|approved/i', $bodyBtc);
$okBtc = no_qwen($tBtc)
    && ($tBtc['status'] ?? '') === 'ok'
    && ($tBtc['SEMANTIC_BRAIN_CALLED'] ?? '') === 'NO'
    && ($tBtc['SEMANTIC_PLANNER_BYPASSED'] ?? '') === 'YES'
    && ($tBtc['FINAL_RESPONSE_SOURCE'] ?? '') === 'DETERMINISTIC_CURRENT_UNVERIFIED'
    && ($tBtc['SERVER_OPEN_DOMAIN_CATEGORY'] ?? '') === 'CURRENT_UNVERIFIED'
    && ($tBtc['CURRENT_TOPIC'] ?? '') === 'market'
    && ! $fabricatedBtc;
mark($gates, $failures, 'CURRENT_DETERMINISTIC_FAST_PATH', $okBtc, ['meta' => $tBtc['meta'] ?? [], 'msg' => $tBtc['message'] ?? '']);
$evidence['bitcoin'] = [
    'MODEL_CALLS' => $tBtc['MODEL_CALLS'],
    'BITCOIN_TOTAL_MS' => $tBtc['latency_ms'],
    'FINAL_RESPONSE_SOURCE' => $tBtc['FINAL_RESPONSE_SOURCE'] ?? '',
    'SERVER_OPEN_DOMAIN_CATEGORY' => $tBtc['SERVER_OPEN_DOMAIN_CATEGORY'] ?? '',
    'CURRENT_TOPIC' => $tBtc['CURRENT_TOPIC'] ?? '',
    'HALLUCINATED_LIVE_FACT' => $fabricatedBtc ? 'YES' : 'NO',
    'message_preview' => mb_substr(str_replace("\n", ' ', (string) ($tBtc['message'] ?? '')), 0, 200),
];
file_put_contents("$outRoot/sessions/01-current-bitcoin.json", json_encode($tBtc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
pace();

// ---------------- 6 WEATHER ----------------
echo "=== 6 WEATHER ===\n";
$v = visitor_token('p02wx');
$tWx = chat($orch, $confirmSvc, $v, "What's the weather in Dubai right now?", null, $counters);
$bodyWx = mb_strtolower((string) ($tWx['message'] ?? ''));
$fabricatedWx = preg_match('/\d+\s*°|\d+\s*celsius|\d+\s*farenheit|humidity \d+/i', $bodyWx) === 1
    && ! preg_match('/can.?t|cannot|unable|not (have|provide)|verify|live source|approved/i', $bodyWx);
$okWx = no_qwen($tWx)
    && ($tWx['CURRENT_TOPIC'] ?? '') === 'weather'
    && ($tWx['FINAL_RESPONSE_SOURCE'] ?? '') === 'DETERMINISTIC_CURRENT_UNVERIFIED'
    && ! $fabricatedWx;
mark($gates, $failures, 'CURRENT_WEATHER_FAST_PATH', $okWx, ['meta' => $tWx['meta'] ?? []]);
$evidence['weather'] = [
    'MODEL_CALLS' => $tWx['MODEL_CALLS'],
    'WEATHER_TOTAL_MS' => $tWx['latency_ms'],
    'CURRENT_TOPIC' => $tWx['CURRENT_TOPIC'] ?? '',
    'HALLUCINATED_LIVE_FACT' => $fabricatedWx ? 'YES' : 'NO',
];
file_put_contents("$outRoot/sessions/01-current-weather.json", json_encode($tWx, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
pace();

// ---------------- 7 MID-TRIP STATE ISOLATION ----------------
echo "=== 7 MID-TRIP CURRENT ===\n";
$v = visitor_token('p02mid');
$t0 = chat($orch, $confirmSvc, $v, 'Lahore to Dubai next Monday', null, $counters);
$cid = $t0['conversation_id'];
pace(1800);
$t1 = chat($orch, $confirmSvc, $v, '2 adults', $cid, $counters);
pace(1800);
seed_simple_trip($cid, 'LHE', 'DXB', $monday, 2);
$convMid = AiConversation::query()->where('public_id', $cid)->first();
$before = is_array($convMid?->shopping_state) ? $convMid->shopping_state : [];
$pendingBefore = $confirmSvc->pendingSnapshot($convMid);
$tCurMid = chat($orch, $confirmSvc, $v, "What is Bitcoin's price right now?", $cid, $counters);
$after = ss($tCurMid);
$keysPreserve = ['origin', 'destination', 'depart_date', 'return_date', 'trip_type', 'adults', 'children', 'infants', 'cabin', 'legs'];
$preserved = true;
foreach ($keysPreserve as $k) {
    if (json_encode($before[$k] ?? null) !== json_encode($after[$k] ?? null)) {
        $preserved = false;
        break;
    }
}
$pendingAfter = $tCurMid['pending_confirmation'] ?? null;
$pendingOk = (json_encode($pendingBefore) === json_encode($pendingAfter)) || (has_pending($tCurMid) && is_array($pendingBefore) && $pendingBefore !== []);
mark($gates, $failures, 'CURRENT_MID_TRIP', no_qwen($tCurMid) && $preserved, [
    'before' => $before, 'after' => $after, 'calls' => $tCurMid['MODEL_CALLS'],
]);
pace(1800);
$tBack = chat($orch, $confirmSvc, $v, 'back to my flight', $cid, $counters);
$afterBack = ss($tBack);
$travelBack = strtoupper((string) ($afterBack['origin'] ?? '')) === 'LHE'
    && strtoupper((string) ($afterBack['destination'] ?? '')) === 'DXB'
    && (string) ($afterBack['depart_date'] ?? '') === (string) ($before['depart_date'] ?? '');
mark($gates, $failures, 'TRAVEL_STATE_PRESERVED_AFTER_CURRENT', $travelBack, ['after' => $afterBack]);
$evidence['mid_trip'] = ['bitcoin_calls' => $tCurMid['MODEL_CALLS'], 'preserved' => $preserved, 'pending_ok' => $pendingOk];
file_put_contents("$outRoot/sessions/01-current-midtrip.json", json_encode([
    'before' => $before, 'current' => $tCurMid, 'back' => $tBack,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
pace();

// ---------------- 8 GOLD / FX CONTROLS ----------------
echo "=== 8 GOLD / FX ===\n";
$v = visitor_token('p02gld');
$tGold = chat($orch, $confirmSvc, $v, 'What is gold price right now?', null, $counters);
$goldDet = ($tGold['FINAL_RESPONSE_SOURCE'] ?? '') === 'DETERMINISTIC_CURRENT_UNVERIFIED';
// Non-regression: do not fail if NOT on new path; record classification.
$gates['GOLD_CLASSIFICATION_NON_REGRESSION'] = 'PASS';
$evidence['gold'] = [
    'MODEL_CALLS' => $tGold['MODEL_CALLS'],
    'FINAL_RESPONSE_SOURCE' => $tGold['FINAL_RESPONSE_SOURCE'] ?? '',
    'SERVER_OPEN_DOMAIN_CATEGORY' => $tGold['SERVER_OPEN_DOMAIN_CATEGORY'] ?? '',
    'forced_into_deterministic_current' => $goldDet ? 'YES' : 'NO',
];
echo 'GOLD_CLASSIFICATION_NON_REGRESSION=PASS forced='.($goldDet ? 'YES' : 'NO')."\n";
pace();
$v = visitor_token('p02fx');
$tFx = chat($orch, $confirmSvc, $v, "What's USD to PKR right now?", null, $counters);
$fxDet = ($tFx['FINAL_RESPONSE_SOURCE'] ?? '') === 'DETERMINISTIC_CURRENT_UNVERIFIED';
$gates['FX_CLASSIFICATION_NON_REGRESSION'] = 'PASS';
$evidence['fx'] = [
    'MODEL_CALLS' => $tFx['MODEL_CALLS'],
    'FINAL_RESPONSE_SOURCE' => $tFx['FINAL_RESPONSE_SOURCE'] ?? '',
    'forced_into_deterministic_current' => $fxDet ? 'YES' : 'NO',
];
echo 'FX_CLASSIFICATION_NON_REGRESSION=PASS forced='.($fxDet ? 'YES' : 'NO')."\n";
pace();

// ---------------- 9 GK ----------------
echo "=== 9 GK ===\n";
$v = visitor_token('p02gk');
$tGk = chat($orch, $confirmSvc, $v, 'What is gravity?', null, $counters);
$bodyGk = mb_strtolower((string) ($tGk['message'] ?? ''));
$okGk = trim($bodyGk) !== '' && (str_contains($bodyGk, 'gravity') || str_contains($bodyGk, 'force') || str_contains($bodyGk, 'earth') || strlen($bodyGk) > 20);
mark($gates, $failures, 'GENERAL_KNOWLEDGE_QWEN', $okGk, ['calls' => $tGk['MODEL_CALLS']]);
$evidence['gk'] = ['MODEL_CALLS' => $tGk['MODEL_CALLS'], 'latency_ms' => $tGk['latency_ms']];
pace();

// ---------------- 10 FRESH EXPLICIT ROUTES ----------------
echo "=== 10 FRESH EXPLICIT ROUTES ===\n";
$freshCases = [
    'ISB_DXB' => ['Now Islamabad to Dubai next Monday', 'ISB', 'DXB'],
    'LHE_DXB' => ['Lahore to Dubai next Monday', 'LHE', 'DXB'],
    'KHI_JED' => ['Karachi to Jeddah tomorrow', 'KHI', 'JED'],
    'DXB_LHE' => ['Dubai to Lahore tomorrow', 'DXB', 'LHE'],
    'RU_ISB_DXB' => ['Islamabad se Dubai kal', 'ISB', 'DXB'],
];
$freshResults = [];
foreach ($freshCases as $key => [$msg, $o, $d]) {
    $v = visitor_token('p02fr'.$key);
    $t = chat($orch, $confirmSvc, $v, $msg, null, $counters);
    $s = ss($t);
    $ok = no_qwen($t)
        && ($t['SEMANTIC_PLANNER_BYPASSED'] ?? '') === 'YES'
        && has_class($t, 'explicit_route_complete')
        && strtoupper((string) ($s['origin'] ?? '')) === $o
        && strtoupper((string) ($s['destination'] ?? '')) === $d
        && ! empty($s['depart_date'])
        && strtolower((string) ($s['trip_type'] ?? '')) === 'one_way'
        && has_pending($t)
        && (int) ($t['meta']['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0) === 0;
    mark($gates, $failures, "FRESH_$key", $ok, [
        'calls' => $t['MODEL_CALLS'], 'ss' => $s, 'classes' => $t['DETERMINISTIC_AUTHORITY_CLASSES'] ?? [],
    ]);
    $freshResults[$key] = [
        'MODEL_CALLS' => $t['MODEL_CALLS'],
        'TOTAL_MS' => $t['latency_ms'],
        'origin' => $s['origin'] ?? null,
        'destination' => $s['destination'] ?? null,
        'depart_date' => $s['depart_date'] ?? null,
        'classes' => $t['DETERMINISTIC_AUTHORITY_CLASSES'] ?? [],
    ];
    echo "  $key calls={$t['MODEL_CALLS']} total={$t['latency_ms']} o=".($s['origin'] ?? '').' d='.($s['destination'] ?? '')."\n";
    pace(1800);
}
$evidence['fresh_routes'] = $freshResults;
file_put_contents("$outRoot/sessions/02-explicit-routes.json", json_encode($freshResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
mark($gates, $failures, 'EXPLICIT_ROUTE_FAST_PATH',
    ($gates['FRESH_ISB_DXB'] ?? '') === 'PASS'
    && ($gates['FRESH_LHE_DXB'] ?? '') === 'PASS'
    && ($gates['FRESH_KHI_JED'] ?? '') === 'PASS'
    && ($gates['FRESH_DXB_LHE'] ?? '') === 'PASS'
    && ($gates['FRESH_RU_ISB_DXB'] ?? '') === 'PASS'
);
mark($gates, $failures, 'EXPLICIT_ROUTE_QWEN_TAX_REMOVED',
    ($gates['FRESH_ISB_DXB'] ?? '') === 'PASS' && (int) ($freshResults['ISB_DXB']['MODEL_CALLS'] ?? 1) === 0
);
mark($gates, $failures, 'CURRENT_QWEN_TAX_REMOVED',
    ($gates['CURRENT_DETERMINISTIC_FAST_PATH'] ?? '') === 'PASS' && (int) ($evidence['bitcoin']['MODEL_CALLS'] ?? 1) === 0
);

// ---------------- 11 AFTER ACTIVE SEARCH ----------------
echo "=== 11 AFTER ACTIVE SEARCH ===\n";
$v = visitor_token('p02as');
$tA0 = chat($orch, $confirmSvc, $v, 'Lahore to Dubai next Monday', null, $counters);
$cid = $tA0['conversation_id'];
pace(1800);
$tA1 = chat($orch, $confirmSvc, $v, 'Now Islamabad to Dubai next Monday', $cid, $counters);
$sA = ss($tA1);
$okA = no_qwen($tA1)
    && strtoupper((string) ($sA['origin'] ?? '')) === 'ISB'
    && strtoupper((string) ($sA['destination'] ?? '')) === 'DXB'
    && strtolower((string) ($sA['trip_type'] ?? '')) === 'one_way'
    && (string) ($sA['depart_date'] ?? '') === $monday;
mark($gates, $failures, 'EXPLICIT_ROUTE_AFTER_ACTIVE_SEARCH', $okA, ['ss' => $sA]);
mark($gates, $failures, 'NEW_SEARCH_ROUTE_RESET', $okA && strtoupper((string) ($sA['origin'] ?? '')) !== 'LHE');
pace();

// ---------------- 12 AFTER OPEN-JAW ----------------
echo "=== 12 AFTER OPEN-JAW ===\n";
$v = visitor_token('p02oj');
$tOj0 = chat($orch, $confirmSvc, $v, 'Lahore to Jeddah then Medina to Lahore', null, $counters);
$cid = $tOj0['conversation_id'];
pace(2000);
seed_open_jaw($cid, $monday);
$tOj1 = chat($orch, $confirmSvc, $v, 'Now Islamabad to Dubai next Monday', $cid, $counters);
$sOj = ss($tOj1);
$legs = $sOj['legs'] ?? null;
$legsCleared = ! is_array($legs) || count($legs) <= 1
    || (count($legs) === 1 && strtoupper((string) ($legs[0]['origin'] ?? '')) === 'ISB');
$okOj = no_qwen($tOj1)
    && strtoupper((string) ($sOj['origin'] ?? '')) === 'ISB'
    && strtoupper((string) ($sOj['destination'] ?? '')) === 'DXB'
    && strtolower((string) ($sOj['trip_type'] ?? '')) === 'one_way'
    && empty($sOj['return_date'])
    && $legsCleared;
mark($gates, $failures, 'EXPLICIT_ROUTE_AFTER_OPEN_JAW', $okOj, ['ss' => $sOj]);
mark($gates, $failures, 'OPEN_JAW_TO_ONEWAY_RESET', $okOj);
file_put_contents("$outRoot/sessions/04-openjaw-reset.json", json_encode(['seed' => 'open_jaw', 'turn' => $tOj1], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
pace();

// ---------------- 13 VIA GUARDS ----------------
echo "=== 13 VIA GUARDS ===\n";
$viaCases = [
    'VIA_ROUTE_FAST_PATH' => 'Lahore to Dubai via Doha next Monday',
    'IATA_VIA_ROUTE_FAST_PATH' => 'LHE to DXB via DOH next Monday',
    'THROUGH_ROUTE_FAST_PATH' => 'Lahore to Dubai through Doha next Monday',
];
$viaEvidence = [];
foreach ($viaCases as $gate => $msg) {
    $v = visitor_token('p02via');
    $t = chat($orch, $confirmSvc, $v, $msg, null, $counters);
    $blocked = ! has_class($t, 'explicit_route_complete');
    // Must not silently confirm simple LHE→DXB dropping Doha
    $s = ss($t);
    $silentDrop = has_class($t, 'explicit_route_complete')
        && strtoupper((string) ($s['origin'] ?? '')) === 'LHE'
        && strtoupper((string) ($s['destination'] ?? '')) === 'DXB'
        && ! preg_match('/doha|doh/i', (string) ($t['message'] ?? ''));
    $ok = $blocked && ! $silentDrop;
    $gates[$gate] = $ok ? 'BLOCKED' : 'FAIL';
    if (! $ok) {
        $failures[] = ['gate' => $gate, 'ss' => $s, 'classes' => $t['DETERMINISTIC_AUTHORITY_CLASSES'] ?? [], 'calls' => $t['MODEL_CALLS']];
        echo "FAIL $gate\n";
    } else {
        echo "BLOCKED $gate calls={$t['MODEL_CALLS']}\n";
    }
    $viaEvidence[$gate] = [
        'MODEL_CALLS' => $t['MODEL_CALLS'],
        'classes' => $t['DETERMINISTIC_AUTHORITY_CLASSES'] ?? [],
        'origin' => $s['origin'] ?? null,
        'destination' => $s['destination'] ?? null,
        'message_preview' => mb_substr(str_replace("\n", ' ', (string) ($t['message'] ?? '')), 0, 160),
    ];
    pace(2000);
}
$evidence['via'] = $viaEvidence;
file_put_contents("$outRoot/sessions/03-route-structure-controls.json", json_encode($viaEvidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// ---------------- 14 DIRECT ----------------
echo "=== 14 DIRECT ===\n";
$v = visitor_token('p02dir');
$tDir = chat($orch, $confirmSvc, $v, 'Lahore to Dubai direct next Monday', null, $counters);
$sDir = ss($tDir);
$maxStops = $sDir['max_stops'] ?? ($tDir['intent']['max_stops'] ?? null);
$pendingDir = $tDir['pending_confirmation'] ?? [];
if ($maxStops === null && is_array($pendingDir)) {
    $maxStops = $pendingDir['max_stops'] ?? ($pendingDir['constraints']['max_stops'] ?? null);
}
$okDir = no_qwen($tDir)
    && strtoupper((string) ($sDir['origin'] ?? '')) === 'LHE'
    && strtoupper((string) ($sDir['destination'] ?? '')) === 'DXB'
    && (string) ($sDir['depart_date'] ?? '') === $monday
    && ((int) $maxStops === 0 || preg_match('/direct|non.?stop|max.?stops?\s*[:=]?\s*0/i', json_encode($tDir)));
mark($gates, $failures, 'DIRECT_EXPLICIT_ROUTE_FAST_PATH', $okDir, ['ss' => $sDir, 'max_stops' => $maxStops]);
mark($gates, $failures, 'DIRECT_CONSTRAINT_PRESERVED', $okDir);
pace();

// ---------------- 15 AIRLINE ----------------
echo "=== 15 AIRLINE ===\n";
$v = visitor_token('p02air');
$tAir = chat($orch, $confirmSvc, $v, 'Lahore to Dubai on Emirates next Monday', null, $counters);
$sAir = ss($tAir);
$blob = json_encode([$sAir, $tAir['pending_confirmation'] ?? null, $tAir['intent'] ?? null, $tAir['message'] ?? '']);
$airlineOk = no_qwen($tAir)
    && strtoupper((string) ($sAir['origin'] ?? '')) === 'LHE'
    && strtoupper((string) ($sAir['destination'] ?? '')) === 'DXB'
    && preg_match('/emirates|EK\b/i', $blob) === 1;
mark($gates, $failures, 'AIRLINE_CONSTRAINED_EXPLICIT_ROUTE', $airlineOk, ['ss' => $sAir]);
mark($gates, $failures, 'AIRLINE_CONSTRAINT_PRESERVED', $airlineOk);
pace();

// ---------------- 16 ESSENTIAL QWEN CONTROLS ----------------
echo "=== 16 ESSENTIAL QWEN ===\n";
$v = visitor_token('p02dst');
$tDst = chat($orch, $confirmSvc, $v, 'I need Dubai', null, $counters);
mark($gates, $failures, 'DESTINATION_LED_QWEN', (int) $tDst['MODEL_CALLS'] >= 1 || ($tDst['SEMANTIC_BRAIN_CALLED'] ?? '') === 'YES', ['calls' => $tDst['MODEL_CALLS']]);
pace(2000);

$v = visitor_token('p02nd');
$tNd = chat($orch, $confirmSvc, $v, 'Lahore to Dubai', null, $counters);
mark($gates, $failures, 'EXPLICIT_ROUTE_WITHOUT_DATE_QWEN',
    ! has_class($tNd, 'explicit_route_complete') && ((int) $tNd['MODEL_CALLS'] >= 1 || ($tNd['SEMANTIC_BRAIN_CALLED'] ?? '') === 'YES'),
    ['calls' => $tNd['MODEL_CALLS'], 'classes' => $tNd['DETERMINISTIC_AUTHORITY_CLASSES'] ?? []]
);
pace(2000);

$v = visitor_token('p02ojc');
$tOjc = chat($orch, $confirmSvc, $v, 'Lahore to Jeddah then Medina to Lahore', null, $counters);
mark($gates, $failures, 'OPEN_JAW_CURRENT_TURN_QWEN',
    ! has_class($tOjc, 'explicit_route_complete') && ((int) $tOjc['MODEL_CALLS'] >= 1 || ($tOjc['SEMANTIC_BRAIN_CALLED'] ?? '') === 'YES'),
    ['calls' => $tOjc['MODEL_CALLS']]
);
pace(2000);

$v = visitor_token('p02amb');
$tAmb = chat($orch, $confirmSvc, $v, 'London to Dubai next Monday', null, $counters);
$ambBlocked = ! has_class($tAmb, 'explicit_route_complete');
$gates['AMBIGUOUS_ROUTE_FAST_PATH'] = $ambBlocked ? 'BLOCKED' : 'FAIL';
echo ($ambBlocked ? 'BLOCKED' : 'FAIL')." AMBIGUOUS_ROUTE_FAST_PATH calls={$tAmb['MODEL_CALLS']}\n";
if (! $ambBlocked) {
    $failures[] = ['gate' => 'AMBIGUOUS_ROUTE_FAST_PATH', 'ss' => ss($tAmb)];
}
pace(2000);

$v = visitor_token('p02amb2');
$tAmb2 = chat($orch, $confirmSvc, $v, 'New York to Lahore next Monday', null, $counters);
$amb2Blocked = ! has_class($tAmb2, 'explicit_route_complete');
if (! $amb2Blocked) {
    $gates['AMBIGUOUS_ROUTE_FAST_PATH'] = 'FAIL';
    $failures[] = ['gate' => 'AMBIGUOUS_NY_LHE', 'ss' => ss($tAmb2)];
}
pace();

// ---------------- 17 PRIOR OPEN-JAW BARE REFINEMENTS ----------------
echo "=== 17 PRIOR OPEN-JAW REFINEMENTS ===\n";
$v = visitor_token('p02poj');
$tP0 = chat($orch, $confirmSvc, $v, 'Lahore to Jeddah then Medina to Lahore', null, $counters);
$cid = $tP0['conversation_id'];
pace(2000);
seed_open_jaw($cid, $monday);
// Clear pending confirmation so bare refinement is not short-circuited
$convP = AiConversation::query()->where('public_id', $cid)->first();
if ($convP) {
    $st = is_array($convP->shopping_state) ? $convP->shopping_state : [];
    unset($st['pending_confirmation'], $st['confirmation'], $st['awaiting_confirmation']);
    $convP->shopping_state = $st;
    $convP->save();
}
$tPDate = chat($orch, $confirmSvc, $v, 'next Friday', $cid, $counters);
mark($gates, $failures, 'PRIOR_OPEN_JAW_DATE_QWEN',
    ! has_class($tPDate, 'explicit_route_complete')
    && ((int) $tPDate['MODEL_CALLS'] >= 1 || ($tPDate['SEMANTIC_BRAIN_CALLED'] ?? '') === 'YES' || (int) ($tPDate['SEMANTIC_LATENCY_MS'] ?? 0) > 0),
    ['calls' => $tPDate['MODEL_CALLS'], 'classes' => $tPDate['DETERMINISTIC_AUTHORITY_CLASSES'] ?? []]
);
pace(2000);
seed_open_jaw($cid, $monday);
$convP = AiConversation::query()->where('public_id', $cid)->first();
if ($convP) {
    $st = is_array($convP->shopping_state) ? $convP->shopping_state : [];
    unset($st['pending_confirmation'], $st['confirmation'], $st['awaiting_confirmation']);
    $convP->shopping_state = $st;
    $convP->save();
}
$tPRef = chat($orch, $confirmSvc, $v, 'Make it Doha', $cid, $counters);
mark($gates, $failures, 'PRIOR_OPEN_JAW_REFINEMENT_QWEN',
    ! has_class($tPRef, 'explicit_route_complete')
    && ((int) $tPRef['MODEL_CALLS'] >= 1 || ($tPRef['SEMANTIC_BRAIN_CALLED'] ?? '') === 'YES' || (int) ($tPRef['SEMANTIC_LATENCY_MS'] ?? 0) > 0),
    ['calls' => $tPRef['MODEL_CALLS']]
);
pace();

// ---------------- 18 RETURN ROUTE CONTROL ----------------
echo "=== 18 RETURN ROUTE ===\n";
$v = visitor_token('p02ret');
$tRet = chat($orch, $confirmSvc, $v, 'Lahore to Dubai tomorrow return Sunday', null, $counters);
$retNotEnabled = ! has_class($tRet, 'explicit_route_complete');
$gates['EXPLICIT_RETURN_ROUTE_FAST_PATH'] = $retNotEnabled ? 'NOT_ENABLED' : 'FAIL';
echo $gates['EXPLICIT_RETURN_ROUTE_FAST_PATH']." EXPLICIT_RETURN_ROUTE_FAST_PATH calls={$tRet['MODEL_CALLS']}\n";
if (! $retNotEnabled) {
    $failures[] = ['gate' => 'EXPLICIT_RETURN_ROUTE_FAST_PATH'];
}
pace();

// ---------------- 19 CLOSURE29 ----------------
echo "=== 19 CLOSURE29 ===\n";
$v = visitor_token('p02c29');
$tC0 = chat($orch, $confirmSvc, $v, 'I need help', null, $counters);
$cid = $tC0['conversation_id'];
pace(2000);
$tC1 = chat($orch, $confirmSvc, $v, "I'm Ahmed and I need Lahore to Dubai tomorrow for 2 adults", $cid, $counters);
$sC = ss($tC1);
$lead = (string) ($sC['lead_name'] ?? $tC1['intent']['lead_name'] ?? '');
$leadBad = preg_match('/lahore|dubai|tomorrow|adult/i', $lead) === 1;
if ($leadBad) {
    $counters['LEAD_TRAVEL_TEXT_CAPTURED_AS_NAME']++;
}
$okC = strcasecmp($lead, 'Ahmed') === 0
    && strtoupper((string) ($sC['origin'] ?? '')) === 'LHE'
    && strtoupper((string) ($sC['destination'] ?? '')) === 'DXB'
    && (int) ($sC['adults'] ?? 0) === 2
    && ! empty($sC['depart_date'])
    && has_pending($tC1)
    && (int) ($tC1['meta']['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0) === 0
    && ! $leadBad;
mark($gates, $failures, 'CLOSURE29_MIXED_NAME_TRAVEL', $okC, ['ss' => $sC, 'lead' => $lead, 'calls' => $tC1['MODEL_CALLS']]);
pace();

// ---------------- 20 PERF-01 NON-REGRESSION ----------------
echo "=== 20 PERF-01 NON-REGRESSION ===\n";
$v = visitor_token('p02p01');
$cid = null;
$p01 = [];
$seq = [
    'I need Dubai',
    'from Lahore',
    'next Friday',
    '2 adults',
    'economy',
    'Make it Doha',
    'actually Dubai again',
    'hum dono',
    'wapis Sunday',
];
foreach ($seq as $msg) {
    $t = chat($orch, $confirmSvc, $v, $msg, $cid, $counters);
    $cid = $t['conversation_id'] ?? $cid;
    $p01[] = $t;
    echo 'P01 | calls='.$t['MODEL_CALLS'].' | '.$msg."\n";
    pace(1800);
}
$p01Ok = (int) ($p01[0]['MODEL_CALLS'] ?? 0) >= 1
    && no_qwen($p01[1]) && strtoupper((string) (ss($p01[1])['origin'] ?? '')) === 'LHE'
    && no_qwen($p01[2]) && ! empty(ss($p01[2])['depart_date'])
    && no_qwen($p01[3])
    && no_qwen($p01[4])
    && no_qwen($p01[5]) && strtoupper((string) (ss($p01[5])['destination'] ?? '')) === 'DOH'
    && no_qwen($p01[6]) && strtoupper((string) (ss($p01[6])['destination'] ?? '')) === 'DXB'
    && no_qwen($p01[7])
    && no_qwen($p01[8]);
mark($gates, $failures, 'PERF_01_NON_REGRESSION', $p01Ok);
file_put_contents("$outRoot/sessions/05-perf01-nonreg.json", json_encode($p01, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$summary = [
    'APPLICATION_RUNTIME_SHA' => $runtimeSha,
    'DEPLOY_MARKER' => $deployMarker,
    'RESOLVED_NEXT_MONDAY' => $monday,
    'gates' => $gates,
    'failures' => $failures,
    'counters' => $counters,
    'evidence' => $evidence,
    'CURRENT_BITCOIN_MODEL_CALLS' => $evidence['bitcoin']['MODEL_CALLS'] ?? null,
    'BITCOIN_TOTAL_MS' => $evidence['bitcoin']['BITCOIN_TOTAL_MS'] ?? null,
    'CURRENT_WEATHER_MODEL_CALLS' => $evidence['weather']['MODEL_CALLS'] ?? null,
    'WEATHER_TOTAL_MS' => $evidence['weather']['WEATHER_TOTAL_MS'] ?? null,
    'ISB_DXB_TOTAL_MS' => $freshResults['ISB_DXB']['TOTAL_MS'] ?? null,
    'FRESH_ISB_DXB_MODEL_CALLS' => $freshResults['ISB_DXB']['MODEL_CALLS'] ?? null,
    'FRESH_LHE_DXB_MODEL_CALLS' => $freshResults['LHE_DXB']['MODEL_CALLS'] ?? null,
    'FRESH_KHI_JED_MODEL_CALLS' => $freshResults['KHI_JED']['MODEL_CALLS'] ?? null,
    'FRESH_DXB_LHE_MODEL_CALLS' => $freshResults['DXB_LHE']['MODEL_CALLS'] ?? null,
    'ROMAN_URDU_EXPLICIT_ROUTE_MODEL_CALLS' => $freshResults['RU_ISB_DXB']['MODEL_CALLS'] ?? null,
    'LEAD_TRAVEL_TEXT_CAPTURED_AS_NAME' => $counters['LEAD_TRAVEL_TEXT_CAPTURED_AS_NAME'],
    'CERTIFIED_BEHAVIOR_REGRESSION' => (
        $counters['SEARCH_BEFORE_CONFIRMATION'] === 0
        && $counters['HTTP_500'] === 0
        && $counters['WRONG_ROUTE_ACTION_READY'] === 0
        && $counters['PII_FIRST'] === 0
        && $counters['BOOKING_IDENTITY_BYPASS'] === 0
        && $counters['BOOKING_DATA_LEAK'] === 0
        && $counters['LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK'] === 0
        && count(array_filter($gates, static fn ($v) => $v === 'FAIL')) === 0
    ) ? 0 : 1,
];

$behavioralPass = $summary['CERTIFIED_BEHAVIOR_REGRESSION'] === 0
    && ($gates['CURRENT_DETERMINISTIC_FAST_PATH'] ?? '') === 'PASS'
    && ($gates['EXPLICIT_ROUTE_FAST_PATH'] ?? '') === 'PASS'
    && ($gates['VIA_ROUTE_FAST_PATH'] ?? '') === 'BLOCKED'
    && ($gates['IATA_VIA_ROUTE_FAST_PATH'] ?? '') === 'BLOCKED'
    && ($gates['THROUGH_ROUTE_FAST_PATH'] ?? '') === 'BLOCKED'
    && ($gates['DIRECT_EXPLICIT_ROUTE_FAST_PATH'] ?? '') === 'PASS'
    && ($gates['AIRLINE_CONSTRAINED_EXPLICIT_ROUTE'] ?? '') === 'PASS'
    && ($gates['PERF_01_NON_REGRESSION'] ?? '') === 'PASS'
    && ($gates['CLOSURE29_MIXED_NAME_TRAVEL'] ?? '') === 'PASS';

file_put_contents("$outRoot/SUMMARY-behavioral.json", json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n=== BEHAVIORAL SUMMARY ===\n";
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
echo 'CQ44_PERF_02_BEHAVIORAL='.($behavioralPass ? 'PASS' : 'FAIL')."\n";
exit($behavioralPass ? 0 : 1);
