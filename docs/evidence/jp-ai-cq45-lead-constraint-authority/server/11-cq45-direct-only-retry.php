<?php
/**
 * CQ45 focused retry: disable lab adapter; assert conversation continuity;
 * historical direct-only + Ahmed + Yes/No.
 */
require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Data\Ai\TravelIntent;
use App\Models\AiConversation;
use App\Services\Ai\AiChatOrchestrator;
use App\Services\Ai\FlightSearchConfirmationGate;
use App\Services\Ai\Hybrid\ServerTravelSignals;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

$outRoot = getenv('CQ45_OUT') ?: '/tmp/cq45-prod-closure-out';
@mkdir($outRoot, 0775, true);

// Ensure lab path cannot steal public Ask AI turns during closure UAT.
config([
    'ai_lab.enabled' => false,
    'ota.ai_assistant.hard_allow.lab_adapter' => false,
]);
\App\Models\AiAssistantSetting::query()->delete();
app(\App\Services\Ai\AiAssistantSettingsService::class)->get();

$mergeSha = 'c115cc5712ad0fca0ab9729d6d34907220959a93';
$runtimeSha = trim((string) @file_get_contents('/home/pkjetp/jetpk_app/.jetpk-runtime-sha'));
$deployMarker = trim((string) @file_get_contents('/home/pkjetp/jetpk_app/storage/app/deploy-sha.txt'));

$orch = app(AiChatOrchestrator::class);
$gate = app(FlightSearchConfirmationGate::class);
$signals = app(ServerTravelSignals::class);

$gates = [];
$failures = [];
$obs = [];

function assert_gate(array &$gates, array &$failures, string $name, bool $pass, string $detail = ''): void
{
    $gates[$name] = $pass ? 'PASS' : 'FAIL';
    if (! $pass) {
        $failures[] = $name.($detail !== '' ? ': '.$detail : '');
    }
    echo ($pass ? 'PASS' : 'FAIL')." $name".($detail !== '' ? " ($detail)" : '')."\n";
}

function visitor_token(string $prefix): string
{
    return substr(preg_replace('/[^A-Za-z0-9]/', '', $prefix).bin2hex(random_bytes(16)), 0, 48);
}

function chat(AiChatOrchestrator $orch, FlightSearchConfirmationGate $gate, string $visitor, string $message, string $cid): array
{
    $request = Request::create('/api/public/ai/chat', 'POST', [
        'message' => $message,
        'conversation_id' => $cid,
    ]);
    $request->headers->set('User-Agent', 'CQ45-Retry/1.0');
    $request->headers->set('Cookie', 'jp_ai_vid='.$visitor);
    $request->cookies->set('jp_ai_vid', $visitor);
    $request->server->set('REMOTE_ADDR', '127.0.0.1');
    app()->instance('request', $request);

    $resolved = $orch->resolveConversation($request, $cid);
    $conversation = $resolved['conversation'];
    $rate = $orch->assertRateLimit($resolved['visitor_raw']);
    if (is_array($rate)) {
        return ['ok' => false, 'status' => 'rate_limited', 'conversation_id' => $conversation->public_id, 'meta' => [], 'shopping_state' => [], 'pending' => null, 'seed_match' => false];
    }
    $sanitized = $orch->sanitizeUserMessage($message);
    $payload = $orch->handleChat($conversation, $sanitized['message']);
    $conversation->refresh();

    return [
        'ok' => (bool) ($payload['ok'] ?? true),
        'status' => (string) ($payload['status'] ?? ''),
        'conversation_id' => $conversation->public_id,
        'seed_match' => $conversation->public_id === $cid,
        'message' => (string) ($payload['message'] ?? ''),
        'meta' => is_array($payload['meta'] ?? null) ? $payload['meta'] : [],
        'shopping_state' => is_array($conversation->shopping_state) ? $conversation->shopping_state : [],
        'pending' => $gate->pendingSnapshot($conversation),
        'lab_adapter' => (bool) data_get($payload, 'meta.lab_adapter', false),
    ];
}

function seed(string $vid, FlightSearchConfirmationGate $gate): AiConversation
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

echo "RUNTIME_SHA=$runtimeSha DEPLOY_MARKER=$deployMarker MERGE_SHA=$mergeSha\n";
assert_gate($gates, $failures, 'RUNTIME_SHA_MATCH', $runtimeSha === $mergeSha);
assert_gate($gates, $failures, 'DEPLOY_MARKER_MATCH', $deployMarker === $mergeSha);

$prior = [
    'origin' => 'KHI',
    'destination' => 'JED',
    'intent' => 'flight_search',
    'depart_date' => '2026-10-06',
    'adults' => 2,
    'trip_type' => 'one_way',
];
$det = $signals->deterministicAuthorityComplete('direct only', $prior);
$prog = $signals->progressiveTravelAuthority('direct only', $prior);
$obs['signals_direct_only'] = ['det' => $det, 'prog' => [
    'stop_refinement' => $prog['stop_refinement'] ?? null,
    'active' => $prog['active'] ?? null,
    'travel_refinement' => $prog['travel_refinement'] ?? null,
]];
assert_gate($gates, $failures, 'SIGNALS_DIRECT_ONLY_COMPLETE', ($det['complete'] ?? false) === true, json_encode($det));
assert_gate($gates, $failures, 'SIGNALS_STOP_REFINEMENT', ! empty($prog['stop_refinement']));

// Historical direct only
$vid = visitor_token('cq45rdir');
$conv = seed($vid, $gate);
$seedId = $conv->public_id;
$turn = chat($orch, $gate, $vid, 'direct only', $seedId);
$obs['direct_only'] = $turn;
$st = $turn['shopping_state'];
$pending = $turn['pending'];
$modelCalls = (int) ($turn['meta']['MODEL_CALLS'] ?? $turn['meta']['GENERAL_MODEL_CALLS'] ?? 0);
$searchCalls = (int) ($turn['meta']['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0);
$detComplete = (string) ($turn['meta']['DETERMINISTIC_AUTHORITY_COMPLETE'] ?? '');

assert_gate($gates, $failures, 'DIRECT_ONLY_SEED_MATCH', $turn['seed_match'] === true, 'cid='.$turn['conversation_id'].' seed='.$seedId);
assert_gate($gates, $failures, 'DIRECT_ONLY_NO_LAB', $turn['lab_adapter'] === false);
assert_gate($gates, $failures, 'DIRECT_ONLY_FALSE_LEAD_CAPTURE_0', ($st['lead_name'] ?? null) === null);
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
, json_encode([
    'lead_name' => $st['lead_name'] ?? null,
    'stage' => $st['lead_capture_stage'] ?? null,
    'max_stops' => $st['max_stops'] ?? null,
    'origin' => $st['origin'] ?? null,
    'destination' => $st['destination'] ?? null,
    'adults' => $st['adults'] ?? null,
    'pending_max_stops' => is_array($pending) ? ($pending['max_stops'] ?? null) : null,
    'MODEL_CALLS' => $modelCalls,
    'DETERMINISTIC_AUTHORITY_COMPLETE' => $detComplete,
    'SEARCH_CALLS' => $searchCalls,
    'meta_keys' => array_keys($turn['meta']),
], JSON_UNESCAPED_UNICODE));
assert_gate($gates, $failures, 'DIRECT_ONLY_MODEL_CALLS_0', $modelCalls === 0, "MODEL_CALLS=$modelCalls det=$detComplete");

// Ahmed
$vidA = visitor_token('cq45rahm');
$cA = seed($vidA, $gate);
$tA = chat($orch, $gate, $vidA, 'Ahmed', $cA->public_id);
$obs['ahmed'] = ['seed_match' => $tA['seed_match'], 'lab' => $tA['lab_adapter'], 'lead' => $tA['shopping_state']['lead_name'] ?? null, 'stage' => $tA['shopping_state']['lead_capture_stage'] ?? null];
assert_gate($gates, $failures, 'LEGITIMATE_NAME_AHMED', ($tA['shopping_state']['lead_name'] ?? null) === 'Ahmed' && $tA['lab_adapter'] === false);

// Bare Yes
$vidY = visitor_token('cq45ryes');
$cY = seed($vidY, $gate);
$tY = chat($orch, $gate, $vidY, 'Yes', $cY->public_id);
$obs['bare_yes'] = [
    'seed_match' => $tY['seed_match'],
    'lab' => $tY['lab_adapter'],
    'lead' => $tY['shopping_state']['lead_name'] ?? null,
    'search' => (int) ($tY['meta']['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0),
    'confirm_before' => (bool) ($tY['meta']['CONFIRMATION_BEFORE_SEARCH'] ?? false),
    'pending' => $tY['pending'],
];
assert_gate($gates, $failures, 'BARE_YES_CONFIRMATION_AUTHORITY',
    ($tY['shopping_state']['lead_name'] ?? null) === null
    && (int) ($tY['meta']['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0) === 1
    && ! empty($tY['meta']['CONFIRMATION_BEFORE_SEARCH'])
);

// Bare No
$vidN = visitor_token('cq45rbno');
$cN = seed($vidN, $gate);
$tN = chat($orch, $gate, $vidN, 'No', $cN->public_id);
$obs['bare_no'] = [
    'seed_match' => $tN['seed_match'],
    'lead' => $tN['shopping_state']['lead_name'] ?? null,
    'search' => (int) ($tN['meta']['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0),
    'pending' => $tN['pending'],
    'stage' => $tN['shopping_state']['lead_capture_stage'] ?? null,
];
assert_gate($gates, $failures, 'BARE_NO_CONFIRMATION_AUTHORITY',
    ($tN['shopping_state']['lead_name'] ?? null) === null
    && (int) ($tN['meta']['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0) === 0
    && $tN['pending'] === null
    && ($tN['shopping_state']['lead_capture_stage'] ?? null) === 'name'
);

// Related residual (characterize only)
$related = [];
$relatedRepro = false;
foreach (['cheapest', 'fastest', 'morning'] as $msg) {
    $v = visitor_token('cq45rrel'.md5($msg));
    $c = seed($v, $gate);
    $t = chat($orch, $gate, $v, $msg, $c->public_id);
    $related[$msg] = [
        'LEAD_NAME_AFTER' => $t['shopping_state']['lead_name'] ?? null,
        'LEAD_STAGE_AFTER' => $t['shopping_state']['lead_capture_stage'] ?? null,
    ];
    if (is_string($related[$msg]['LEAD_NAME_AFTER']) && $related[$msg]['LEAD_NAME_AFTER'] !== '') {
        $relatedRepro = true;
    }
}

$summary = [
    'MERGE_SHA' => $mergeSha,
    'RUNTIME_SHA' => $runtimeSha,
    'DEPLOY_MARKER' => $deployMarker,
    'gates' => $gates,
    'failures' => $failures,
    'observations' => $obs,
    'RELATED_CONSTRAINT_FALSE_LEAD_REPRODUCED' => $relatedRepro ? 'YES' : 'NO',
    'CQ46_RANKING_TIME_LEAD_AUTHORITY_REQUIRED' => $relatedRepro ? 'YES' : 'NO',
    'related_observations' => $related,
    'OVERALL' => count($failures) === 0 ? 'PASS' : 'FAIL',
];
file_put_contents($outRoot.'/SUMMARY-retry.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "OVERALL=".$summary['OVERALL']."\n";
exit(count($failures) === 0 ? 0 : 1);
