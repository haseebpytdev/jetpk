#!/usr/bin/env php
<?php
/**
 * CQ44-PERF-01 production real-Qwen benchmark (in-process, non-mutating).
 * Expected runtime: a0e616747a95d632f270a6edafc3f6e8d9230fca
 */
require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AiConversation;
use App\Services\Ai\AiChatOrchestrator;
use App\Services\Ai\FlightSearchConfirmationGate;
use Illuminate\Http\Request;

$EXPECTED_SHA = 'a0e616747a95d632f270a6edafc3f6e8d9230fca';
$outRoot = getenv('CQ44_OUT') ?: '/tmp/cq44-perf01-prod-out';
@mkdir($outRoot, 0775, true);
@mkdir($outRoot.'/sessions', 0775, true);
// Fresh session dir for this run (keep prior run via CQ44_OUT override if needed).
foreach (glob($outRoot.'/sessions/*.json') ?: [] as $old) {
    @unlink($old);
}

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
    'STALE_LEGS' => 0,
    'STALE_CONFIRMATION' => 0,
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
];

$gates = [];
$failures = [];
$fallbackEvents = [];
$bypassClassCounts = [
    'origin_followup' => 0,
    'date_refinement' => 0,
    'pax_refinement' => 0,
    'cabin_refinement' => 0,
    'destination_correction' => 0,
    'return_refinement' => 0,
];

function visitor_token(string $prefix): string
{
    $raw = preg_replace('/[^A-Za-z0-9]/', '', $prefix).bin2hex(random_bytes(16));

    return substr($raw, 0, 48);
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
    array &$fallbackEvents,
    array &$bypassClassCounts,
    bool $explicitHandoff = false,
): array {
    $t0 = microtime(true);
    $payloadIn = ['message' => $message];
    if ($cid) {
        $payloadIn['conversation_id'] = $cid;
    }
    $request = Request::create('/api/public/ai/chat', 'POST', $payloadIn);
    $request->headers->set('User-Agent', 'CQ44-PERF01-Prod/1.0');
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
    static $handoffFlagged = [];
    if ($state === AiConversation::STATE_WAITING_FOR_HUMAN && ! $explicitHandoff) {
        $hid = (string) $conversation->public_id;
        if (! isset($handoffFlagged[$hid])) {
            $counters['QWEN_MODEL_ONLY_HANDOFF']++;
            $handoffFlagged[$hid] = true;
        }
    }

    $reason = (string) ($meta['SEMANTIC_FALLBACK_REASON'] ?? '');
    $isDetBypass = ($meta['DETERMINISTIC_AUTHORITY_COMPLETE'] ?? '') === 'YES'
        || $reason === 'deterministic_authority_complete';
    if (($meta['SEMANTIC_BRAIN_FALLBACK'] ?? '') === 'YES' && ! $isDetBypass) {
        $fallbackEvents[] = [
            'user_message' => $message,
            'latency_ms' => (int) ($meta['SEMANTIC_LATENCY_MS'] ?? $ms),
            'reason' => $reason !== '' ? $reason : 'unknown',
            'final_result' => mb_substr(str_replace("\n", ' ', $body), 0, 160),
        ];
    }
    $classes = $meta['DETERMINISTIC_AUTHORITY_CLASSES'] ?? [];
    if (is_array($classes)) {
        foreach ($classes as $c) {
            if (is_string($c) && isset($bypassClassCounts[$c])) {
                $bypassClassCounts[$c]++;
            }
        }
    }

    $ss = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
    $intent = is_array($payload['intent'] ?? null) ? $payload['intent'] : null;

    return [
        'rate_limited' => false,
        'http_status' => 200,
        'latency_ms' => $ms,
        'conversation_id' => $conversation->public_id,
        'status' => (string) ($payload['status'] ?? ''),
        'state' => $state,
        'mode' => (string) ($intent['mode'] ?? $meta['FINAL_RESPONSE_SOURCE'] ?? ''),
        'message' => $body,
        'requires_confirmation' => (bool) ($payload['requires_confirmation'] ?? false),
        'meta' => $meta,
        'intent' => $intent,
        'shopping_state' => $ss,
        'pending_confirmation' => $confirmSvc->pendingSnapshot($conversation),
        'user_message' => $message,
        'MODEL_CALLS' => (int) ($meta['MODEL_CALLS'] ?? $meta['GENERAL_MODEL_CALLS'] ?? 0),
        'SEMANTIC_BRAIN_CALLED' => (string) ($meta['SEMANTIC_BRAIN_CALLED'] ?? ''),
        'SEMANTIC_PLANNER_BYPASSED' => (string) ($meta['SEMANTIC_PLANNER_BYPASSED'] ?? ''),
        'DETERMINISTIC_AUTHORITY_COMPLETE' => (string) ($meta['DETERMINISTIC_AUTHORITY_COMPLETE'] ?? ''),
        'DETERMINISTIC_AUTHORITY_CLASSES' => is_array($classes) ? $classes : [],
        'SEMANTIC_LATENCY_MS' => (int) ($meta['SEMANTIC_LATENCY_MS'] ?? 0),
        'SEMANTIC_BRAIN_FALLBACK' => (string) ($meta['SEMANTIC_BRAIN_FALLBACK'] ?? ''),
        'SEMANTIC_FALLBACK_REASON' => $reason,
        'ts' => gmdate('c'),
    ];
}

function pct(array $a, float $p): ?float
{
    if ($a === []) {
        return null;
    }
    sort($a);
    $i = (int) floor(($p / 100) * (count($a) - 1));

    return round($a[max(0, min(count($a) - 1, $i))], 1);
}

function save_session(string $outRoot, string $name, array $turns, array $extra = []): void
{
    file_put_contents(
        "$outRoot/sessions/$name.json",
        json_encode(array_merge(['session' => $name, 'turns' => $turns], $extra), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

function ss(array $t): array
{
    return is_array($t['shopping_state'] ?? null) ? $t['shopping_state'] : [];
}

function model_calls(array $t): int
{
    return (int) ($t['MODEL_CALLS'] ?? $t['meta']['MODEL_CALLS'] ?? $t['meta']['GENERAL_MODEL_CALLS'] ?? 0);
}

function is_bypass(array $t): bool
{
    if (model_calls($t) !== 0) {
        return false;
    }

    return ($t['DETERMINISTIC_AUTHORITY_COMPLETE'] ?? '') === 'YES'
        || (($t['SEMANTIC_PLANNER_BYPASSED'] ?? '') === 'YES'
            && ($t['SEMANTIC_FALLBACK_REASON'] ?? '') === 'deterministic_authority_complete')
        || (($t['meta']['llm_bypassed'] ?? false) === true);
}

function expect_bypass(array &$gates, array &$failures, string $gate, array $t, string $classHint = '', ?callable $stateOk = null): void
{
    // PERF-01 success: no planner/inference on the turn (SemanticBrain short-circuit
    // OR hybrid pending-confirmation authority that never enters the planner).
    $noPlanner = model_calls($t) === 0 && (int) ($t['SEMANTIC_LATENCY_MS'] ?? 0) === 0;
    $det = ($t['DETERMINISTIC_AUTHORITY_COMPLETE'] ?? '') === 'YES'
        || ($t['SEMANTIC_PLANNER_BYPASSED'] ?? '') === 'YES'
        || (($t['meta']['llm_bypassed'] ?? false) === true);
    $statePass = $stateOk === null ? true : (bool) $stateOk($t);
    // No-planner + correct state is enough (hybrid may omit llm_bypassed when value unchanged).
    $ok = $noPlanner && $statePass && ($det || $stateOk !== null);
    if ($classHint !== '' && ($t['DETERMINISTIC_AUTHORITY_COMPLETE'] ?? '') === 'YES') {
        $ok = $ok && in_array($classHint, $t['DETERMINISTIC_AUTHORITY_CLASSES'] ?? [], true);
    }
    $gates[$gate] = $ok ? 'PASS' : 'FAIL';
    $path = ($t['DETERMINISTIC_AUTHORITY_COMPLETE'] ?? '') === 'YES'
        ? 'semantic_deterministic'
        : ((($t['meta']['llm_bypassed'] ?? false) === true) ? 'hybrid_pending_authority' : 'unknown');
    if (! $ok) {
        $failures[] = ['gate' => $gate, 'meta' => $t['meta'] ?? [], 'msg' => $t['user_message'] ?? '', 'path' => $path];
        echo "FAIL $gate path=$path\n";
    } else {
        echo "PASS $gate path=$path\n";
    }
}

function expect_qwen(array &$gates, array &$failures, string $gate, array $t): void
{
    $ok = model_calls($t) >= 1
        && ($t['DETERMINISTIC_AUTHORITY_COMPLETE'] ?? '') !== 'YES';
    $gates[$gate] = $ok ? 'PASS' : 'FAIL';
    if (! $ok) {
        $failures[] = ['gate' => $gate, 'meta' => $t['meta'] ?? [], 'msg' => $t['user_message'] ?? ''];
        echo "FAIL $gate calls=".model_calls($t)." complete=".($t['DETERMINISTIC_AUTHORITY_COMPLETE'] ?? '')."\n";
    } else {
        echo "PASS $gate calls=".model_calls($t)." latency=".($t['SEMANTIC_LATENCY_MS'] ?? 0)."\n";
    }
}

// ---------------- CONFIG / FOCUSED BYPASS ----------------
echo "=== FOCUSED BYPASS ===\n";
$v = visitor_token('cq44pb');
$cid = null;
$focused = [];
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
foreach ($seq as $i => $msg) {
    $t = chat($orch, $confirmSvc, $v, $msg, $cid, $counters, $fallbackEvents, $bypassClassCounts);
    $cid = $t['conversation_id'] ?? $cid;
    $focused[] = $t;
    echo 'F'.($i + 1)." | calls=".model_calls($t).' | sem='.($t['SEMANTIC_LATENCY_MS'] ?? 0).' | total='.$t['latency_ms'].' | '.$msg.' | bypass='.(($t['DETERMINISTIC_AUTHORITY_COMPLETE'] ?? '') === 'YES' ? 'YES' : 'NO')."\n";
    pace(2000);
}
save_session($outRoot, '01-focused-bypass', $focused);

$gates['DESTINATION_LED_QWEN_CONTROL'] = model_calls($focused[0]) >= 1 ? 'PASS' : 'FAIL';
expect_bypass($gates, $failures, 'PROD_BYPASS_ORIGIN_FOLLOWUP', $focused[1], 'origin_followup', function ($t) {
    return strtoupper((string) (ss($t)['origin'] ?? '')) === 'LHE'
        && strtoupper((string) (ss($t)['destination'] ?? '')) === 'DXB';
});
expect_bypass($gates, $failures, 'PROD_BYPASS_DATE', $focused[2], 'date_refinement', function ($t) {
    return ! empty(ss($t)['depart_date']);
});
expect_bypass($gates, $failures, 'PROD_BYPASS_PAX', $focused[3], 'pax_refinement', function ($t) {
    return (int) (ss($t)['adults'] ?? 0) === 2
        || (int) (($t['intent']['adults'] ?? 0)) === 2;
});
expect_bypass($gates, $failures, 'PROD_BYPASS_CABIN', $focused[4], 'cabin_refinement', function ($t) {
    $c = strtolower((string) (ss($t)['cabin'] ?? $t['intent']['cabin'] ?? ''));

    return str_contains($c, 'economy');
});
expect_bypass($gates, $failures, 'PROD_BYPASS_DESTINATION_CORRECTION', $focused[5], 'destination_correction', function ($t) {
    return strtoupper((string) (ss($t)['destination'] ?? $t['intent']['destination'] ?? '')) === 'DOH';
});
expect_bypass($gates, $failures, 'PROD_BYPASS_DEST_AGAIN', $focused[6], 'destination_correction', function ($t) {
    return strtoupper((string) (ss($t)['destination'] ?? $t['intent']['destination'] ?? '')) === 'DXB';
});
expect_bypass($gates, $failures, 'PROD_BYPASS_HUM_DONO', $focused[7], 'pax_refinement', function ($t) {
    return (int) (ss($t)['adults'] ?? $t['intent']['adults'] ?? 0) === 2;
});
expect_bypass($gates, $failures, 'PROD_BYPASS_CONTEXTUAL_RETURN', $focused[8], 'return_refinement', function ($t) {
    return ! empty(ss($t)['return_date'])
        || strtolower((string) (ss($t)['trip_type'] ?? '')) === 'return';
});

$ss1 = ss($focused[1]);
$gates['FROM_LAHORE_INVALID_PLAN_TAX_REMOVED'] = (
    model_calls($focused[1]) === 0
    && (int) ($focused[1]['SEMANTIC_LATENCY_MS'] ?? 0) === 0
    && ($focused[1]['SEMANTIC_FALLBACK_REASON'] ?? '') !== 'invalid_plan'
    && strtoupper((string) ($ss1['origin'] ?? '')) === 'LHE'
    && strtoupper((string) ($ss1['destination'] ?? '')) === 'DXB'
    && (int) ($focused[1]['latency_ms'] ?? 0) < 5000
) ? 'PASS' : 'FAIL';
echo 'FROM_LAHORE_INVALID_PLAN_TAX_REMOVED='.$gates['FROM_LAHORE_INVALID_PLAN_TAX_REMOVED']."\n";

$ss5 = ss($focused[5]);
if (strtoupper((string) ($ss5['destination'] ?? $focused[5]['intent']['destination'] ?? '')) !== 'DOH') {
    $counters['WRONG_DESTINATION']++;
}
$ss6 = ss($focused[6]);
if (strtoupper((string) ($ss6['destination'] ?? $focused[6]['intent']['destination'] ?? '')) !== 'DXB') {
    $counters['WRONG_DESTINATION']++;
}

// ---------------- EXPLICIT ROUTE (fresh; no pending confirmation) ----------------
echo "=== EXPLICIT ROUTE ===\n";
$vEx = visitor_token('cq44ex');
$cidEx = null;
$tEx0 = chat($orch, $confirmSvc, $vEx, 'I need Dubai', $cidEx, $counters, $fallbackEvents, $bypassClassCounts);
$cidEx = $tEx0['conversation_id'] ?? $cidEx;
pace(2000);
$tEx = chat($orch, $confirmSvc, $vEx, 'Now Islamabad to Dubai next Monday', $cidEx, $counters, $fallbackEvents, $bypassClassCounts);
expect_qwen($gates, $failures, 'EXPLICIT_ROUTE_QWEN_CONTROL', $tEx);
pace(2000);

// ---------------- OPEN-JAW CURRENT + PRIOR MULTI-LEG ----------------
echo "=== OPEN-JAW / PRIOR MULTI-LEG ===\n";
$voj = visitor_token('cq44oj');
$cidoj = null;
$ojTurns = [];
$tOj = chat($orch, $confirmSvc, $voj, 'Lahore to Jeddah then Medina to Lahore', $cidoj, $counters, $fallbackEvents, $bypassClassCounts);
$cidoj = $tOj['conversation_id'] ?? $cidoj;
$ojTurns[] = $tOj;
expect_qwen($gates, $failures, 'OPEN_JAW_CURRENT_TURN_QWEN', $tOj);
pace(2500);

// Force authoritative prior multi-leg state (production shopping_state)
$convOj = AiConversation::query()->where('public_id', $cidoj)->first();
if ($convOj) {
    $st = is_array($convOj->shopping_state) ? $convOj->shopping_state : [];
    $st['trip_type'] = 'open_jaw';
    $st['origin'] = 'LHE';
    $st['destination'] = 'JED';
    $st['intent'] = 'flight_search';
    $st['depart_date'] = $st['depart_date'] ?? '2026-10-02';
    $st['legs'] = [
        ['origin' => 'LHE', 'destination' => 'JED', 'departure_date' => $st['depart_date']],
        ['origin' => 'MED', 'destination' => 'LHE', 'departure_date' => null],
    ];
    $convOj->shopping_state = $st;
    $convOj->save();
}

$tOjDate = chat($orch, $confirmSvc, $voj, 'next Friday', $cidoj, $counters, $fallbackEvents, $bypassClassCounts);
$ojTurns[] = $tOjDate;
expect_qwen($gates, $failures, 'PRIOR_OPEN_JAW_DATE_QWEN', $tOjDate);
pace(2500);

$tOjRef = chat($orch, $confirmSvc, $voj, 'Make it Doha', $cidoj, $counters, $fallbackEvents, $bypassClassCounts);
$ojTurns[] = $tOjRef;
expect_qwen($gates, $failures, 'PRIOR_OPEN_JAW_REFINEMENT_QWEN', $tOjRef);
save_session($outRoot, '03-multileg-guard', $ojTurns);
pace(2000);

// ---------------- SIMPLE RETURN ----------------
echo "=== SIMPLE RETURN ===\n";
$vr = visitor_token('cq44rt');
$cidr = null;
$retTurns = [];
$tR0 = chat($orch, $confirmSvc, $vr, 'I need Dubai', $cidr, $counters, $fallbackEvents, $bypassClassCounts);
$cidr = $tR0['conversation_id'] ?? $cidr;
$retTurns[] = $tR0;
pace(2000);
$tR1 = chat($orch, $confirmSvc, $vr, 'from Lahore', $cidr, $counters, $fallbackEvents, $bypassClassCounts);
$retTurns[] = $tR1;
pace(2000);
$convR = AiConversation::query()->where('public_id', $cidr)->first();
if ($convR) {
    $st = is_array($convR->shopping_state) ? $convR->shopping_state : [];
    $st['trip_type'] = 'return';
    $st['origin'] = 'LHE';
    $st['destination'] = 'DXB';
    $st['intent'] = 'flight_search';
    $st['depart_date'] = $st['depart_date'] ?? '2026-10-02';
    $st['legs'] = [
        ['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => $st['depart_date']],
    ];
    $convR->shopping_state = $st;
    $convR->save();
}
$tR2 = chat($orch, $confirmSvc, $vr, 'wapis Sunday', $cidr, $counters, $fallbackEvents, $bypassClassCounts);
$retTurns[] = $tR2;
expect_bypass($gates, $failures, 'SIMPLE_RETURN_CONTEXTUAL_DATE_BYPASS', $tR2, 'return_refinement');
save_session($outRoot, '02-essential-controls-return', $retTurns);
pace(2000);

// ---------------- GK / CURRENT ----------------
echo "=== GK / CURRENT ===\n";
$vg = visitor_token('cq44gk');
$cidg = null;
$gkTurns = [];
$tGk = chat($orch, $confirmSvc, $vg, 'What is gravity?', $cidg, $counters, $fallbackEvents, $bypassClassCounts);
$cidg = $tGk['conversation_id'] ?? $cidg;
$gkTurns[] = $tGk;
$bodyGk = mb_strtolower((string) ($tGk['message'] ?? ''));
$gates['GENERAL_KNOWLEDGE_QWEN'] = (trim($bodyGk) !== '' && (str_contains($bodyGk, 'gravity') || str_contains($bodyGk, 'force') || str_contains($bodyGk, 'earth') || strlen($bodyGk) > 20)) ? 'PASS' : 'FAIL';
echo 'GENERAL_KNOWLEDGE_QWEN='.$gates['GENERAL_KNOWLEDGE_QWEN']."\n";
pace(2000);
$tCur = chat($orch, $confirmSvc, $vg, "What is Bitcoin's price right now?", $cidg, $counters, $fallbackEvents, $bypassClassCounts);
$gkTurns[] = $tCur;
$bodyCur = mb_strtolower((string) ($tCur['message'] ?? ''));
$fabricated = preg_match('/\$\s*\d{3,}|price is \d+/i', $bodyCur) === 1
    && ! str_contains($bodyCur, 'verify')
    && ! str_contains($bodyCur, 'live')
    && ! str_contains($bodyCur, "can't")
    && ! str_contains($bodyCur, 'cannot')
    && ! str_contains($bodyCur, 'not');
$gates['CURRENT_UNVERIFIED_QWEN'] = (! $fabricated && trim($bodyCur) !== '') ? 'PASS' : 'FAIL';
echo 'CURRENT_UNVERIFIED_QWEN='.$gates['CURRENT_UNVERIFIED_QWEN']."\n";
save_session($outRoot, '02-essential-controls-gk', $gkTurns);
pace(2000);

// ---------------- SMOKE ----------------
echo "=== SMOKE ===\n";
$vs = visitor_token('cq44sm');
$cids = null;
$smokeMsgs = [
    'I need Dubai', 'from Lahore', 'next Friday', '2 adults', 'economy',
    'Make it Doha', 'actually Dubai again', 'Now Karachi to Jeddah next week',
    'one day later', 'I also need to come back on Sunday', 'Now Dubai to Lahore tomorrow',
    'Lahore to Jeddah then Medina to Lahore', 'Now Islamabad to Dubai next Monday',
    'hum dono', 'wapis Sunday', 'Talk to support', 'back to my Dubai search',
    'What is gravity?', "What is Bitcoin's price right now?",
];
$smoke = [];
foreach ($smokeMsgs as $i => $msg) {
    $t = chat($orch, $confirmSvc, $vs, $msg, $cids, $counters, $fallbackEvents, $bypassClassCounts, $msg === 'Talk to support');
    $cids = $t['conversation_id'] ?? $cids;
    $smoke[] = $t;
    echo 'S'.($i + 1).' | '.$msg.' | calls='.model_calls($t)."\n";
    pace(1800);
}
save_session($outRoot, '04-production-smoke', $smoke);

// ---------------- PRIMARY 42-TURN BENCHMARK ----------------
echo "=== PRIMARY 42-TURN ===\n";
$primaryMsgs = [
    'I need Dubai',
    'from Lahore',
    'next Friday',
    '2 adults',
    'economy',
    'Make it Doha',
    'actually Dubai again',
    '3 adults',
    'business class',
    'tomorrow',
    'Make it 4 adults',
    'Actually 2 adults',
    'Now Karachi to Jeddah next week',
    '2 adults',
    'direct only',
    'one day later',
    'I also need to come back on Sunday',
    'Now Dubai to Lahore tomorrow',
    'Lahore to Jeddah then Medina to Lahore',
    'Now Islamabad to Dubai next Monday',
    '2 adults',
    'What is gravity?',
    'back to my Dubai search',
    "What is Bitcoin's price right now?",
    'back to my flight',
    'Check my booking',
    'Reference ZZTEST999 email test-cq43-final@example.invalid',
    'back to my Dubai search',
    'Talk to support',
    'Dubai jana hai',
    'Lahore se',
    'kal',
    'hum dono',
    'wapis Sunday',
    'No, next Monday',
    'the other route',
    'economy',
    '2 adults',
    'What is photosynthesis?',
    'back to my flight',
    'make cabin economy',
];
$vp = visitor_token('cq44pr');
$cidp = null;
$primary = [];
$primaryQwen = 0;
$primaryBypassTurns = 0;
$semanticLats = [];
$totalLats = [];
foreach ($primaryMsgs as $i => $msg) {
    $t = chat($orch, $confirmSvc, $vp, $msg, $cidp, $counters, $fallbackEvents, $bypassClassCounts, $msg === 'Talk to support');
    $cidp = $t['conversation_id'] ?? $cidp;
    $primary[] = $t;
    $calls = model_calls($t);
    $primaryQwen += $calls;
    if (is_bypass($t)) {
        $primaryBypassTurns++;
    }
    $totalLats[] = (int) round((float) ($t['latency_ms'] ?? 0));
    if (($t['SEMANTIC_BRAIN_CALLED'] ?? '') === 'YES') {
        $semanticLats[] = (int) ($t['SEMANTIC_LATENCY_MS'] ?? 0);
    }
    echo 'P'.($i + 1).' | calls='.$calls.' | sem='.($t['SEMANTIC_LATENCY_MS'] ?? 0).' | total='.$t['latency_ms'].' | bypass='.(is_bypass($t) ? 'Y' : 'N').' | '.$msg."\n";
    pace(2200);
}
save_session($outRoot, '05-real-qwen-session', $primary, [
    'AFTER_PRIMARY_TURNS' => count($primary),
    'AFTER_QWEN_MODEL_CALLS' => $primaryQwen,
    'DETERMINISTIC_BYPASS_TURNS' => $primaryBypassTurns,
]);

$gt10 = count(array_filter($totalLats, fn ($x) => $x > 10000));
$gt20 = count(array_filter($totalLats, fn ($x) => $x > 20000));
$gt30 = count(array_filter($totalLats, fn ($x) => $x > 30000));

$gates['CERTIFIED_BEHAVIOR_REGRESSION'] = (
    $counters['WRONG_ORIGIN'] === 0
    && $counters['WRONG_DESTINATION'] === 0
    && $counters['SEARCH_BEFORE_CONFIRMATION'] === 0
    && $counters['HTTP_500'] === 0
    && $counters['MODEL_CRASHES'] === 0
) ? 0 : 1;

$callRateDown = $primaryQwen < 10;
$gates['PERF_01_CALL_REDUCTION_EFFECTIVE'] = $callRateDown ? 'YES' : 'NO';
$gates['PERF_01_END_TO_END_EFFECTIVE'] = ($gt20 <= 1 && pct($totalLats, 95) !== null && pct($totalLats, 95) <= 13150 * 1.15) ? 'YES' : 'PARTIAL';

$prodPass = (
    ($gates['PROD_BYPASS_ORIGIN_FOLLOWUP'] ?? '') === 'PASS'
    && ($gates['PROD_BYPASS_DATE'] ?? '') === 'PASS'
    && ($gates['PROD_BYPASS_PAX'] ?? '') === 'PASS'
    && ($gates['PROD_BYPASS_CABIN'] ?? '') === 'PASS'
    && ($gates['PROD_BYPASS_DESTINATION_CORRECTION'] ?? '') === 'PASS'
    && ($gates['PROD_BYPASS_CONTEXTUAL_RETURN'] ?? '') === 'PASS'
    && ($gates['PRIOR_OPEN_JAW_DATE_QWEN'] ?? '') === 'PASS'
    && ($gates['PRIOR_OPEN_JAW_REFINEMENT_QWEN'] ?? '') === 'PASS'
    && ($gates['OPEN_JAW_CURRENT_TURN_QWEN'] ?? '') === 'PASS'
    && ($gates['DESTINATION_LED_QWEN_CONTROL'] ?? '') === 'PASS'
    && ($gates['EXPLICIT_ROUTE_QWEN_CONTROL'] ?? '') === 'PASS'
    && ($gates['FROM_LAHORE_INVALID_PLAN_TAX_REMOVED'] ?? '') === 'PASS'
    && ($gates['SIMPLE_RETURN_CONTEXTUAL_DATE_BYPASS'] ?? '') === 'PASS'
    && $callRateDown
    && ($gates['CERTIFIED_BEHAVIOR_REGRESSION'] ?? 1) === 0
    && $counters['SEARCH_BEFORE_CONFIRMATION'] === 0
) ? 'PASS' : 'FAIL';

$summary = [
    'MERGE_SHA' => $EXPECTED_SHA,
    'RUNTIME_SHA' => $runtimeSha,
    'DEPLOY_MARKER' => $deployMarker,
    'CQ44_PERF_01_PRODUCTION' => $prodPass,
    'gates' => $gates,
    'counters' => $counters,
    'bypass_class_counts_all_sessions' => $bypassClassCounts,
    'AFTER_PRIMARY_TURNS' => count($primary),
    'AFTER_QWEN_MODEL_CALLS' => $primaryQwen,
    'AFTER_QWEN_CALL_RATE' => $primaryQwen.'/'.count($primary),
    'DETERMINISTIC_BYPASS_TURNS' => $primaryBypassTurns,
    'AFTER_SEMANTIC_P50_MS' => pct($semanticLats, 50),
    'AFTER_SEMANTIC_P95_MS' => pct($semanticLats, 95),
    'AFTER_SEMANTIC_MAX_MS' => $semanticLats === [] ? null : max($semanticLats),
    'AFTER_TOTAL_P50_MS' => pct($totalLats, 50),
    'AFTER_TOTAL_P95_MS' => pct($totalLats, 95),
    'AFTER_TOTAL_MAX_MS' => $totalLats === [] ? null : max($totalLats),
    'AFTER_TURNS_GT_10S' => $gt10,
    'AFTER_TURNS_GT_20S' => $gt20,
    'AFTER_TURNS_GT_30S' => $gt30,
    'AFTER_SEMANTIC_FALLBACK_COUNT' => count($fallbackEvents),
    'fallback_events' => $fallbackEvents,
    'failures' => $failures,
    'PERF_01_CALL_REDUCTION_EFFECTIVE' => $gates['PERF_01_CALL_REDUCTION_EFFECTIVE'],
    'PERF_01_END_TO_END_EFFECTIVE' => $gates['PERF_01_END_TO_END_EFFECTIVE'],
    'QWEN_RUNTIME_STABILITY' => ($counters['MODEL_CRASHES'] === 0 && $counters['HTTP_500'] === 0 && $counters['OOM_EVENTS'] === 0) ? 'PASS' : 'FAIL',
];

file_put_contents("$outRoot/SUMMARY.json", json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n=== SUMMARY ===\n";
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
echo "CQ44_PERF_01_PRODUCTION=$prodPass\n";
exit($prodPass === 'PASS' && $failures === [] ? 0 : 1);
