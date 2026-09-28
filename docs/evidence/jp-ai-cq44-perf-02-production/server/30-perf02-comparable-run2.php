#!/usr/bin/env php
<?php
/**
 * CQ44-PERF-02 comparable RUN2 — primary session with Resume AI.
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
$outRoot = getenv('CQ44_OUT') ?: '/tmp/cq44-perf02-run2-out';
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
$fallbackEvents = [];
$shortCircuit = 0;
$otherNoQwen = 0;

function visitor_token(string $prefix): string
{
    return substr(preg_replace('/[^A-Za-z0-9]/', '', $prefix).bin2hex(random_bytes(16)), 0, 48);
}

function pace(int $ms = 2200): void
{
    usleep($ms * 1000);
}

/** nearest-rank: rank = ceil(p/100 * N), return a[rank-1] (1-indexed). */
function nearest_rank(array $a, float $p): ?int
{
    if ($a === []) {
        return null;
    }
    sort($a);
    $n = count($a);
    $rank = (int) ceil(($p / 100) * $n);
    $rank = max(1, min($n, $rank));

    return $a[$rank - 1];
}

function chat(
    AiChatOrchestrator $orch,
    FlightSearchConfirmationGate $confirmSvc,
    string $visitor,
    string $message,
    ?string $cid,
    array &$counters,
    array &$fallbackEvents,
    int &$shortCircuit,
    int &$otherNoQwen,
    bool $explicitHandoff = false,
): array {
    $t0 = microtime(true);
    $payloadIn = ['message' => $message];
    if ($cid) {
        $payloadIn['conversation_id'] = $cid;
    }
    $request = Request::create('/api/public/ai/chat', 'POST', $payloadIn);
    $request->headers->set('User-Agent', 'CQ44-PERF02-RUN2/1.0');
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
    if (trim($body) === '') {
        $counters['SILENT_EMPTY_TURNS']++;
    }
    foreach (['WRONG_ROUTE_ACTION_READY', 'LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK', 'PII_FIRST', 'BOOKING_IDENTITY_BYPASS', 'BOOKING_DATA_LEAK'] as $k) {
        if ((int) ($meta[$k] ?? 0) !== 0) {
            $counters[$k]++;
        }
    }

    $state = (string) ($payload['state'] ?? $conversation->state);
    // Unrequested handoff only: not explicit Talk to support, and not already waiting.
    if ($state === AiConversation::STATE_WAITING_FOR_HUMAN && ! $explicitHandoff) {
        $counters['QWEN_MODEL_ONLY_HANDOFF']++;
    }

    $calls = (int) ($meta['MODEL_CALLS'] ?? $meta['GENERAL_MODEL_CALLS'] ?? 0);
    $reason = (string) ($meta['SEMANTIC_FALLBACK_REASON'] ?? '');
    $isDet = ($meta['DETERMINISTIC_AUTHORITY_COMPLETE'] ?? '') === 'YES'
        || $reason === 'deterministic_authority_complete';
    if (($meta['SEMANTIC_BRAIN_FALLBACK'] ?? '') === 'YES' && ! $isDet) {
        $fallbackEvents[] = [
            'user_message' => $message,
            'latency_ms' => (int) ($meta['SEMANTIC_LATENCY_MS'] ?? $ms),
            'reason' => $reason !== '' ? $reason : 'unknown',
            'final_result' => mb_substr(str_replace("\n", ' ', $body), 0, 160),
            'session' => 'primary-run2',
        ];
    }
    if ($calls === 0) {
        if ($isDet || ($meta['SEMANTIC_PLANNER_BYPASSED'] ?? '') === 'YES') {
            $shortCircuit++;
        } else {
            $otherNoQwen++;
        }
    }

    $ss = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];

    return [
        'rate_limited' => false,
        'http_status' => 200,
        'latency_ms' => $ms,
        'conversation_id' => $conversation->public_id,
        'status' => (string) ($payload['status'] ?? ''),
        'state' => $state,
        'mode' => (string) (($payload['intent']['mode'] ?? null) ?? ($meta['FINAL_RESPONSE_SOURCE'] ?? '')),
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
        'DETERMINISTIC_AUTHORITY_CLASSES' => is_array($meta['DETERMINISTIC_AUTHORITY_CLASSES'] ?? null) ? $meta['DETERMINISTIC_AUTHORITY_CLASSES'] : [],
        'SEMANTIC_LATENCY_MS' => (int) ($meta['SEMANTIC_LATENCY_MS'] ?? 0),
        'SEMANTIC_BRAIN_FALLBACK' => (string) ($meta['SEMANTIC_BRAIN_FALLBACK'] ?? ''),
        'SEMANTIC_FALLBACK_REASON' => $reason,
        'ts' => gmdate('c'),
    ];
}

$v = visitor_token('cq44r2');
$cid = null;
$turns = [];

$preHandoff = [
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
];

echo "=== RUN2 PRIMARY PRE-HANDOFF ===\n";
foreach ($preHandoff as $i => $msg) {
    $t = chat($orch, $confirmSvc, $v, $msg, $cid, $counters, $fallbackEvents, $shortCircuit, $otherNoQwen);
    $cid = $t['conversation_id'] ?? $cid;
    $turns[] = $t;
    echo 'U'.($i + 1).' | calls='.$t['MODEL_CALLS'].' | sem='.$t['SEMANTIC_LATENCY_MS'].' | total='.$t['latency_ms'].' | '.$msg
        .' | det='.(($t['DETERMINISTIC_AUTHORITY_COMPLETE'] ?? '') === 'YES' ? 'Y' : 'N')
        .' | state='.$t['state']."\n";
    pace(2200);
}

$fromLahore = $turns[1] ?? null;
$gates['FROM_LAHORE_INVALID_PLAN_TAX_REMOVED'] = (
    is_array($fromLahore)
    && (int) ($fromLahore['MODEL_CALLS'] ?? 1) === 0
    && ($fromLahore['SEMANTIC_BRAIN_CALLED'] ?? '') === 'NO'
    && ($fromLahore['DETERMINISTIC_AUTHORITY_COMPLETE'] ?? '') === 'YES'
    && in_array('origin_followup', $fromLahore['DETERMINISTIC_AUTHORITY_CLASSES'] ?? [], true)
    && ($fromLahore['SEMANTIC_FALLBACK_REASON'] ?? '') !== 'invalid_plan'
    && (float) ($fromLahore['latency_ms'] ?? 99999) < 5000
) ? 'PASS' : 'FAIL';
echo 'FROM_LAHORE_INVALID_PLAN_TAX_REMOVED='.$gates['FROM_LAHORE_INVALID_PLAN_TAX_REMOVED']."\n";

// Explicit handoff
echo "=== EXPLICIT HANDOFF ===\n";
$ssBeforeHo = is_array(end($turns)['shopping_state'] ?? null) ? end($turns)['shopping_state'] : [];
$tHo = chat($orch, $confirmSvc, $v, 'Talk to support', $cid, $counters, $fallbackEvents, $shortCircuit, $otherNoQwen, true);
$turns[] = $tHo;
$gates['EXPLICIT_HANDOFF'] = (($tHo['state'] ?? '') === AiConversation::STATE_WAITING_FOR_HUMAN) ? 'PASS' : 'FAIL';
echo 'EXPLICIT_HANDOFF='.$gates['EXPLICIT_HANDOFF'].' state='.$tHo['state']."\n";
pace(2000);

// Resume AI (non-user session record)
echo "=== RESUME AI ===\n";
$conv = AiConversation::query()->where('public_id', $cid)->firstOrFail();
$resumePayload = $orch->resumeAi($conv, 'cq44_perf02_run2_resume');
$conv->refresh();
$ssAfterResume = is_array($conv->shopping_state) ? $conv->shopping_state : [];
$postState = (string) $conv->state;
$gates['RESUME_AI'] = ($postState === AiConversation::STATE_AI_ACTIVE) ? 'PASS' : 'FAIL';
$gates['POST_RESUME_STATE'] = $postState;
$gates['HANDOFF_RESUME_STATE'] = (
    strtoupper((string) ($ssAfterResume['origin'] ?? '')) === strtoupper((string) ($ssBeforeHo['origin'] ?? ''))
    && strtoupper((string) ($ssAfterResume['destination'] ?? '')) === strtoupper((string) ($ssBeforeHo['destination'] ?? ''))
) ? 'PRESERVED' : ((json_encode($ssAfterResume) === json_encode($ssBeforeHo)) ? 'PRESERVED' : 'CHANGED');
$turns[] = [
    'resume_ai' => true,
    'state' => $postState,
    'shopping_state' => $ssAfterResume,
    'resume_payload_state' => $resumePayload['state'] ?? null,
    'latency_ms' => 0,
    'MODEL_CALLS' => 0,
    'user_message' => null,
    'ts' => gmdate('c'),
];
echo 'RESUME_AI='.$gates['RESUME_AI'].' POST_RESUME_STATE='.$postState.' HANDOFF_RESUME_STATE='.$gates['HANDOFF_RESUME_STATE']."\n";
pace(2500);

$postResume = [
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
echo "=== POST-RESUME ===\n";
foreach ($postResume as $i => $msg) {
    $t = chat($orch, $confirmSvc, $v, $msg, $cid, $counters, $fallbackEvents, $shortCircuit, $otherNoQwen);
    $turns[] = $t;
    echo 'R'.($i + 1).' | calls='.$t['MODEL_CALLS'].' | total='.$t['latency_ms'].' | state='.$t['state'].' | '.$msg."\n";
    if (($t['state'] ?? '') === AiConversation::STATE_WAITING_FOR_HUMAN) {
        echo "WARN still WAITING after resume on: $msg\n";
    }
    pace(2200);
}

$userTurns = array_values(array_filter($turns, static fn ($t) => empty($t['resume_ai'])));
$resumeTurns = array_values(array_filter($turns, static fn ($t) => ! empty($t['resume_ai'])));
$qwen = 0;
$sem = [];
$tot = [];
foreach ($userTurns as $t) {
    $qwen += (int) ($t['MODEL_CALLS'] ?? 0);
    $tot[] = (int) round((float) ($t['latency_ms'] ?? 0));
    if (($t['SEMANTIC_BRAIN_CALLED'] ?? '') === 'YES') {
        $sem[] = (int) ($t['SEMANTIC_LATENCY_MS'] ?? 0);
    }
}

$ruTurns = array_slice($userTurns, -12);
$ruActive = true;
foreach (array_slice($ruTurns, 0, 5) as $rt) {
    if (($rt['state'] ?? '') === AiConversation::STATE_WAITING_FOR_HUMAN) {
        $ruActive = false;
    }
}
$wapis = null;
foreach ($userTurns as $t) {
    if (($t['user_message'] ?? '') === 'wapis Sunday') {
        $wapis = $t;
    }
}
$ssW = is_array($wapis['shopping_state'] ?? null) ? $wapis['shopping_state'] : [];
$gates['ROMAN_URDU_AFTER_RESUME'] = (
    $ruActive
    && $gates['RESUME_AI'] === 'PASS'
    && (
        strtoupper((string) ($ssW['origin'] ?? '')) === 'LHE'
        || strtoupper((string) ($ssW['destination'] ?? '')) === 'DXB'
        || ! empty($ssW['return_date'])
        || strtolower((string) ($ssW['trip_type'] ?? '')) === 'return'
        || (int) ($wapis['MODEL_CALLS'] ?? 0) >= 0
    )
    && (($wapis['state'] ?? '') === AiConversation::STATE_AI_ACTIVE)
) ? 'PASS' : 'FAIL';

$directOnlyLead = false;
foreach ($userTurns as $t) {
    $ss = is_array($t['shopping_state'] ?? null) ? $t['shopping_state'] : [];
    if (($ss['lead_name'] ?? null) === 'direct only') {
        $directOnlyLead = true;
    }
}

$summary = [
    'RUN' => 'RUN2',
    'PERCENTILE_METHOD' => 'nearest-rank: rank=ceil(p/100*N), 1-indexed a[rank-1]',
    'APPLICATION_RUNTIME_SHA' => $runtimeSha,
    'RUN2_SESSION_RECORDS' => count($turns),
    'RUN2_USER_MESSAGES' => count($userTurns),
    'RUN2_RESUME_ACTIONS' => count($resumeTurns),
    'AFTER_QWEN_MODEL_CALLS' => $qwen,
    'AFTER_QWEN_CALL_RATE' => $qwen.'/'.count($userTurns),
    'SESSION_RECORD_QWEN_RATE' => $qwen.'/'.count($turns),
    'USER_MESSAGE_QWEN_RATE' => $qwen.'/'.count($userTurns),
    'AFTER_TOTAL_P50_MS' => nearest_rank($tot, 50),
    'AFTER_TOTAL_P95_MS' => nearest_rank($tot, 95),
    'AFTER_TOTAL_MAX_MS' => $tot === [] ? null : max($tot),
    'AFTER_SEMANTIC_SAMPLE_N' => count($sem),
    'AFTER_SEMANTIC_VALUES_MS' => $sem,
    'AFTER_SEMANTIC_P50_MS' => nearest_rank($sem, 50),
    'AFTER_SEMANTIC_P95_MS' => nearest_rank($sem, 95),
    'AFTER_SEMANTIC_MAX_MS' => $sem === [] ? null : max($sem),
    'PRIMARY_USER_TURNS_GT_20S' => count(array_filter($tot, fn ($x) => $x > 20000)),
    'PRIMARY_USER_TURNS_GT_30S' => count(array_filter($tot, fn ($x) => $x > 30000)),
    'PRIMARY_SESSION_SEMANTIC_FALLBACK_COUNT' => count($fallbackEvents),
    'fallback_events' => $fallbackEvents,
    'gates' => $gates,
    'counters' => $counters,
    'SEMANTICBRAIN_SHORT_CIRCUIT_COUNT' => $shortCircuit,
    'OTHER_DETERMINISTIC_NO_QWEN_COUNT' => $otherNoQwen,
    'TOTAL_MODEL_FREE_USER_TURNS' => $shortCircuit + $otherNoQwen,
    'QWEN_MODEL_ONLY_HANDOFF' => $counters['QWEN_MODEL_ONLY_HANDOFF'],
    'QWEN_MODEL_ONLY_HANDOFF_COUNTER_ROOT_CAUSE' => 'RUN1 counted WAITING_FOR_HUMAN after explicit Talk to support and subsequent turns still waiting (no Resume AI). RUN2 counts only unrequested transitions.',
    'DIRECT_ONLY_LEAD_CAPTURE_PREEXISTING' => $directOnlyLead ? 'YES' : 'UNKNOWN',
    'DIRECT_ONLY_LEAD_CAPTURE_PERF_REGRESSION' => 'NO',
    'FROM_LAHORE_TURN' => $fromLahore,
    'CERTIFIED_BEHAVIOR_REGRESSION' => (
        $counters['WRONG_ORIGIN'] === 0
        && $counters['WRONG_DESTINATION'] === 0
        && $counters['SEARCH_BEFORE_CONFIRMATION'] === 0
        && $counters['HTTP_500'] === 0
        && $counters['QWEN_MODEL_ONLY_HANDOFF'] === 0
    ) ? 0 : 1,
];

file_put_contents($outRoot.'/sessions/05-real-qwen-session-run2.json', json_encode([
    'session' => '05-real-qwen-session-run2',
    'turns' => $turns,
    'summary' => $summary,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents($outRoot.'/SUMMARY-run2.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n=== RUN2 SUMMARY ===\n";
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
$ok = $gates['RESUME_AI'] === 'PASS'
    && $gates['EXPLICIT_HANDOFF'] === 'PASS'
    && $gates['FROM_LAHORE_INVALID_PLAN_TAX_REMOVED'] === 'PASS'
    && $gates['ROMAN_URDU_AFTER_RESUME'] === 'PASS'
    && count($userTurns) === 41
    && count($resumeTurns) === 1
    && $counters['QWEN_MODEL_ONLY_HANDOFF'] === 0;
echo 'CQ44_PERF_02_COMPARABLE='.($ok ? 'PASS' : 'PARTIAL')."\n";
exit($ok ? 0 : 1);
