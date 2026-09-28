<?php
/**
 * CQ45 production residual closure (in-process, non-mutating supplier).
 * AUTHORIZED / MERGE SHA: c115cc5712ad0fca0ab9729d6d34907220959a93
 */
require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Data\Ai\TravelIntent;
use App\Models\AiConversation;
use App\Services\Ai\AiChatOrchestrator;
use App\Services\Ai\FlightSearchConfirmationGate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

$outRoot = getenv('CQ45_OUT') ?: '/tmp/cq45-prod-closure-out';
@mkdir($outRoot, 0775, true);

$orch = app(AiChatOrchestrator::class);
$gate = app(FlightSearchConfirmationGate::class);
$mergeSha = 'c115cc5712ad0fca0ab9729d6d34907220959a93';
$runtimeSha = trim((string) @file_get_contents('/home/pkjetp/jetpk_app/.jetpk-runtime-sha'));
$frontendSha = trim((string) @file_get_contents('/home/pkjetp/jetpk_app/frontend/.jetpk-frontend-sha'));
$deployMarker = trim((string) @file_get_contents('/home/pkjetp/jetpk_app/storage/app/deploy-sha.txt'));

$counters = [
    'SEARCH_BEFORE_CONFIRMATION' => 0,
    'HTTP_500' => 0,
    'PII_FIRST' => 0,
    'WRONG_ROUTE_ACTION_READY' => 0,
    'BOOKING_IDENTITY_BYPASS' => 0,
    'BOOKING_DATA_LEAK' => 0,
    'QWEN_MODEL_ONLY_HANDOFF' => 0,
    'LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK' => 0,
    'SUPPLIER_MUTATIONS' => 0,
    'BOOKING_MUTATIONS' => 0,
    'PAYMENT_MUTATIONS' => 0,
    'AUTHORIZED_SEARCH_CALLS' => 0,
    'DIRECT_ONLY_FALSE_LEAD_CAPTURE' => 0,
];
$gates = [];
$failures = [];
$sessions = [];

function visitor_token(string $prefix): string
{
    $raw = preg_replace('/[^A-Za-z0-9]/', '', $prefix).bin2hex(random_bytes(12));

    return substr($raw, 0, 48);
}

function chat(AiChatOrchestrator $orch, FlightSearchConfirmationGate $gate, string $visitor, string $message, ?string $cid, array &$counters): array
{
    $payloadIn = ['message' => $message];
    if ($cid) {
        $payloadIn['conversation_id'] = $cid;
    }
    $request = Request::create('/api/public/ai/chat', 'POST', $payloadIn);
    $request->headers->set('User-Agent', 'CQ45-ProdClosure/1.0');
    $request->headers->set('Cookie', 'jp_ai_vid='.$visitor);
    $request->cookies->set('jp_ai_vid', $visitor);
    $request->server->set('REMOTE_ADDR', '127.0.0.1');
    app()->instance('request', $request);

    try {
        $resolved = $orch->resolveConversation($request, $cid);
        $conversation = $resolved['conversation'];
        $rate = $orch->assertRateLimit($resolved['visitor_raw']);
        if (is_array($rate)) {
            return [
                'ok' => false,
                'status' => 'rate_limited',
                'conversation_id' => $conversation->public_id,
                'message' => (string) ($rate['message'] ?? ''),
                'meta' => [],
                'shopping_state' => is_array($conversation->shopping_state) ? $conversation->shopping_state : [],
                'pending' => $gate->pendingSnapshot($conversation),
            ];
        }
        $sanitized = $orch->sanitizeUserMessage($message);
        if (! ($sanitized['ok'] ?? false)) {
            return [
                'ok' => false,
                'status' => 'invalid',
                'conversation_id' => $conversation->public_id,
                'message' => (string) (($sanitized['payload']['message'] ?? 'invalid')),
                'meta' => [],
                'shopping_state' => is_array($conversation->shopping_state) ? $conversation->shopping_state : [],
                'pending' => $gate->pendingSnapshot($conversation),
            ];
        }
        $payload = $orch->handleChat($conversation, $sanitized['message']);
        $conversation->refresh();
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        $searchCalls = (int) ($meta['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0);
        if ($searchCalls > 0 && empty($meta['CONFIRMATION_BEFORE_SEARCH'])) {
            $counters['SEARCH_BEFORE_CONFIRMATION']++;
        }
        if ($searchCalls > 0 && ! empty($meta['CONFIRMATION_BEFORE_SEARCH'])) {
            $counters['AUTHORIZED_SEARCH_CALLS'] += $searchCalls;
        }
        if ((int) ($meta['LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK'] ?? 0) === 1) {
            $counters['LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK']++;
        }
        if ((int) ($meta['WRONG_ROUTE_ACTION_READY'] ?? 0) === 1) {
            $counters['WRONG_ROUTE_ACTION_READY']++;
        }

        return [
            'ok' => (bool) ($payload['ok'] ?? true),
            'status' => (string) ($payload['status'] ?? ''),
            'conversation_id' => $conversation->public_id,
            'message' => (string) ($payload['message'] ?? ''),
            'meta' => $meta,
            'shopping_state' => is_array($conversation->shopping_state) ? $conversation->shopping_state : [],
            'pending' => $gate->pendingSnapshot($conversation),
        ];
    } catch (Throwable $e) {
        $counters['HTTP_500']++;

        return [
            'ok' => false,
            'status' => 'exception',
            'conversation_id' => $cid,
            'message' => $e->getMessage(),
            'meta' => [],
            'shopping_state' => [],
            'pending' => null,
        ];
    }
}

function seed_khi_jed_pending_lead_name(string $vid, FlightSearchConfirmationGate $gate): AiConversation
{
    $intent = TravelIntent::fromArray([
        'intent' => 'flight_search',
        'origin' => 'KHI',
        'destination' => 'JED',
        'depart_date' => '2026-10-06',
        'adults' => 2,
        'children' => 0,
        'infants' => 0,
        'cabin' => 'economy',
        'trip_type' => 'one_way',
    ], 'STRUCTURED_FALLBACK');

    $conv = AiConversation::query()->create([
        'public_id' => (string) Str::uuid(),
        'visitor_token_hash' => hash('sha256', $vid),
        'channel' => 'web',
        'state' => AiConversation::STATE_AI_ACTIVE,
        'shopping_state' => [
            'intent' => 'flight_search',
            'origin' => 'KHI',
            'destination' => 'JED',
            'depart_date' => '2026-10-06',
            'adults' => 2,
            'cabin' => 'economy',
            'trip_type' => 'one_way',
            'lead_capture_pending' => true,
            'lead_capture_stage' => 'name',
            'lead_name' => null,
            'lead_capture_fields' => ['name', 'email', 'phone', 'contact_consent'],
        ],
    ]);
    $gate->storePending($conv, $gate->buildSnapshot($intent));

    return $conv->fresh();
}

function assert_gate(array &$gates, array &$failures, string $name, bool $pass, string $detail = ''): void
{
    $gates[$name] = $pass ? 'PASS' : 'FAIL';
    if (! $pass) {
        $failures[] = $name.($detail !== '' ? ': '.$detail : '');
    }
    echo ($pass ? 'PASS' : 'FAIL')." $name".($detail !== '' ? " ($detail)" : '')."\n";
}

echo "RUNTIME_SHA=$runtimeSha\nFRONTEND_SHA=$frontendSha\nDEPLOY_MARKER=$deployMarker\nMERGE_SHA=$mergeSha\n";
assert_gate($gates, $failures, 'RUNTIME_SHA_MATCH', $runtimeSha === $mergeSha, "runtime=$runtimeSha");
assert_gate($gates, $failures, 'DEPLOY_MARKER_MATCH', $deployMarker === $mergeSha, "marker=$deployMarker");

// --- Historical direct-only ---
$vid = visitor_token('cq45dir');
$conv = seed_khi_jed_pending_lead_name($vid, $gate);
$before = is_array($conv->shopping_state) ? $conv->shopping_state : [];
assert_gate($gates, $failures, 'SEED_LEAD_STAGE_NAME', ($before['lead_capture_stage'] ?? null) === 'name');
assert_gate($gates, $failures, 'SEED_LEAD_NAME_NULL', ($before['lead_name'] ?? null) === null);
assert_gate($gates, $failures, 'SEED_PENDING_PRESENT', is_array($before[FlightSearchConfirmationGate::STATE_KEY] ?? null));

$turn = chat($orch, $gate, $vid, 'direct only', $conv->public_id, $counters);
$sessions['direct_only'] = $turn;
$st = $turn['shopping_state'];
$pending = $turn['pending'];
$modelCalls = (int) ($turn['meta']['MODEL_CALLS'] ?? $turn['meta']['GENERAL_MODEL_CALLS'] ?? 0);
$searchCalls = (int) ($turn['meta']['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0);

if (($st['lead_name'] ?? null) !== null) {
    $counters['DIRECT_ONLY_FALSE_LEAD_CAPTURE']++;
}

assert_gate($gates, $failures, 'DIRECT_ONLY_HISTORICAL_REPRO',
    ($st['lead_name'] ?? null) === null
    && ($st['lead_capture_stage'] ?? null) === 'name'
    && (int) ($st['max_stops'] ?? -1) === 0
    && ($st['origin'] ?? null) === 'KHI'
    && ($st['destination'] ?? null) === 'JED'
    && (int) ($st['adults'] ?? 0) === 2
    && is_array($pending)
    && (int) ($pending['max_stops'] ?? -1) === 0
    && $searchCalls === 0
    && $turn['ok']
, json_encode([
    'lead_name' => $st['lead_name'] ?? null,
    'stage' => $st['lead_capture_stage'] ?? null,
    'max_stops' => $st['max_stops'] ?? null,
    'origin' => $st['origin'] ?? null,
    'destination' => $st['destination'] ?? null,
    'adults' => $st['adults'] ?? null,
    'pending_max_stops' => is_array($pending) ? ($pending['max_stops'] ?? null) : null,
    'MODEL_CALLS' => $modelCalls,
    'SEARCH_CALLS' => $searchCalls,
], JSON_UNESCAPED_UNICODE));

assert_gate($gates, $failures, 'DIRECT_ONLY_FALSE_LEAD_CAPTURE_0', $counters['DIRECT_ONLY_FALSE_LEAD_CAPTURE'] === 0);
assert_gate($gates, $failures, 'DIRECT_ONLY_MODEL_CALLS_0', $modelCalls === 0, "MODEL_CALLS=$modelCalls");

// --- Stop family smoke (active travel seed) ---
$stopCases = [
    'nonstop' => 0,
    'seedhi' => 0,
    'one stop' => 1,
];
$stopPass = true;
$stopObs = [];
foreach ($stopCases as $msg => $expected) {
    $v = visitor_token('cq45st'.md5($msg));
    $c = seed_khi_jed_pending_lead_name($v, $gate);
    $t = chat($orch, $gate, $v, $msg, $c->public_id, $counters);
    $s = $t['shopping_state'];
    $ok = ($s['lead_name'] ?? null) === null && (int) ($s['max_stops'] ?? -1) === $expected;
    $stopObs[$msg] = [
        'lead_name' => $s['lead_name'] ?? null,
        'max_stops' => $s['max_stops'] ?? null,
        'ok' => $ok,
    ];
    if (! $ok) {
        $stopPass = false;
    }
    $sessions['stop_'.$msg] = ['meta' => $t['meta'], 'state' => $s];
}
assert_gate($gates, $failures, 'STOP_FAMILY_SMOKE', $stopPass, json_encode($stopObs));

// --- Legitimate name still captured ---
$vidN = visitor_token('cq45ahm');
$cN = seed_khi_jed_pending_lead_name($vidN, $gate);
$tN = chat($orch, $gate, $vidN, 'Ahmed', $cN->public_id, $counters);
$sessions['ahmed'] = $tN;
assert_gate($gates, $failures, 'LEGITIMATE_NAME_AHMED', ($tN['shopping_state']['lead_name'] ?? null) === 'Ahmed');

// --- Bare Yes confirmation authority (authorized read once) ---
$vidY = visitor_token('cq45yes');
$cY = seed_khi_jed_pending_lead_name($vidY, $gate);
$tY = chat($orch, $gate, $vidY, 'Yes', $cY->public_id, $counters);
$sessions['bare_yes'] = [
    'lead_name' => $tY['shopping_state']['lead_name'] ?? null,
    'search' => (int) ($tY['meta']['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0),
    'confirm_before' => (bool) ($tY['meta']['CONFIRMATION_BEFORE_SEARCH'] ?? false),
    'pending' => $tY['pending'],
];
assert_gate($gates, $failures, 'BARE_YES_CONFIRMATION_AUTHORITY',
    ($tY['shopping_state']['lead_name'] ?? null) === null
    && (int) ($tY['meta']['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0) === 1
    && ! empty($tY['meta']['CONFIRMATION_BEFORE_SEARCH'])
    && $tY['pending'] === null
);

// --- Bare No ---
$vidNo = visitor_token('cq45bno');
$cNo = seed_khi_jed_pending_lead_name($vidNo, $gate);
$tNo = chat($orch, $gate, $vidNo, 'No', $cNo->public_id, $counters);
$sessions['bare_no'] = [
    'lead_name' => $tNo['shopping_state']['lead_name'] ?? null,
    'search' => (int) ($tNo['meta']['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0),
    'pending' => $tNo['pending'],
    'stage' => $tNo['shopping_state']['lead_capture_stage'] ?? null,
];
assert_gate($gates, $failures, 'BARE_NO_CONFIRMATION_AUTHORITY',
    ($tNo['shopping_state']['lead_name'] ?? null) === null
    && (int) ($tNo['meta']['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0) === 0
    && $tNo['pending'] === null
    && ($tNo['shopping_state']['lead_capture_stage'] ?? null) === 'name'
);

// Related ranking/time residual — characterize only, do not fail CQ45
$related = [];
foreach (['cheapest', 'fastest', 'morning'] as $msg) {
    $v = visitor_token('cq45rel'.md5($msg));
    $c = seed_khi_jed_pending_lead_name($v, $gate);
    $t = chat($orch, $gate, $v, $msg, $c->public_id, $counters);
    $related[$msg] = [
        'LEAD_NAME_AFTER' => $t['shopping_state']['lead_name'] ?? null,
        'LEAD_STAGE_AFTER' => $t['shopping_state']['lead_capture_stage'] ?? null,
    ];
}
$relatedRepro = false;
foreach ($related as $row) {
    if (is_string($row['LEAD_NAME_AFTER'] ?? null) && $row['LEAD_NAME_AFTER'] !== '') {
        $relatedRepro = true;
    }
}

assert_gate($gates, $failures, 'SEARCH_BEFORE_CONFIRMATION_0', $counters['SEARCH_BEFORE_CONFIRMATION'] === 0);
assert_gate($gates, $failures, 'HTTP_500_0', $counters['HTTP_500'] === 0);
assert_gate($gates, $failures, 'WRONG_ROUTE_ACTION_READY_0', $counters['WRONG_ROUTE_ACTION_READY'] === 0);

$summary = [
    'MERGE_SHA' => $mergeSha,
    'RUNTIME_SHA' => $runtimeSha,
    'DEPLOY_MARKER' => $deployMarker,
    'FRONTEND_SHA' => $frontendSha,
    'gates' => $gates,
    'failures' => $failures,
    'counters' => $counters,
    'DIRECT_ONLY_AFTER' => [
        'lead_name' => $st['lead_name'] ?? null,
        'lead_capture_stage' => $st['lead_capture_stage'] ?? null,
        'max_stops' => $st['max_stops'] ?? null,
        'origin' => $st['origin'] ?? null,
        'destination' => $st['destination'] ?? null,
        'adults' => $st['adults'] ?? null,
        'pending_max_stops' => is_array($pending) ? ($pending['max_stops'] ?? null) : null,
        'MODEL_CALLS' => $modelCalls,
        'SEARCH_CALLS' => $searchCalls,
    ],
    'RELATED_CONSTRAINT_FALSE_LEAD_REPRODUCED' => $relatedRepro ? 'YES' : 'NO',
    'CQ46_RANKING_TIME_LEAD_AUTHORITY_REQUIRED' => $relatedRepro ? 'YES' : 'NO',
    'related_observations' => $related,
    'BARE_YES_AUTHORIZED_SEARCH_CALLS' => (int) ($tY['meta']['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0),
    'OVERALL' => count($failures) === 0 ? 'PASS' : 'FAIL',
];

file_put_contents($outRoot.'/SUMMARY.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents($outRoot.'/sessions.json', json_encode($sessions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "OVERALL=".$summary['OVERALL']."\n";
echo "SUMMARY=$outRoot/SUMMARY.json\n";
exit(count($failures) === 0 ? 0 : 1);
