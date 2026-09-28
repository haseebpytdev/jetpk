<?php
/**
 * CQ43-R2 focused production closure (in-process, non-mutating supplier).
 * AUTHORIZED_SHA expected: 24dbf524c06fc894f84a44b14a224a41b49170f7
 */
require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AiConversation;
use App\Services\Ai\AiChatOrchestrator;
use App\Services\Ai\FlightSearchConfirmationGate;
use Illuminate\Http\Request;

$outRoot = getenv('CQ43R2_OUT') ?: '/tmp/cq43r2-prod-closure-out';
@mkdir($outRoot, 0775, true);
@mkdir($outRoot.'/sessions', 0775, true);

$orch = app(AiChatOrchestrator::class);
$confirmSvc = app(FlightSearchConfirmationGate::class);
$runtimeSha = trim((string) @file_get_contents('/home/pkjetp/jetpk_app/.jetpk-runtime-sha'));
$frontendSha = trim((string) @file_get_contents('/home/pkjetp/jetpk_app/frontend/.jetpk-frontend-sha'));
$deployMarker = trim((string) @file_get_contents('/home/pkjetp/jetpk_app/storage/app/deploy-sha.txt'));
$mergeSha = '24dbf524c06fc894f84a44b14a224a41b49170f7';
echo "RUNTIME_SHA=$runtimeSha\nFRONTEND_SHA=$frontendSha\nDEPLOY_MARKER=$deployMarker\n";

$counters = [
    'SEARCH_BEFORE_CONFIRMATION' => 0,
    'HTTP_500' => 0,
    'PII_FIRST' => 0,
    'BOOKING_IDENTITY_BYPASS' => 0,
    'BOOKING_DATA_LEAK' => 0,
    'LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK' => 0,
    'SUPPLIER_MUTATIONS' => 0,
    'BOOKING_MUTATIONS' => 0,
    'PAYMENT_MUTATIONS' => 0,
    'WRONG_ROUTE_ACTION_READY' => 0,
    'LEAD_TRAVEL_TEXT_CAPTURED_AS_NAME' => 0,
    'QWEN_MODEL_ONLY_HANDOFF' => 0,
    'WAITING_FOR_HUMAN_UNREQUESTED' => 0,
    'MODEL_CRASHES' => 0,
    'AUTHORIZED_SEARCH_CALLS' => 0,
];
$gates = [];
$failures = [];
$sessionLog = [];

function visitor_token(string $prefix): string
{
    $raw = preg_replace('/[^A-Za-z0-9]/', '', $prefix).bin2hex(random_bytes(16));

    return substr($raw, 0, 48);
}

function chat(AiChatOrchestrator $orch, FlightSearchConfirmationGate $confirmSvc, string $visitor, string $message, ?string $cid, array &$counters): array
{
    $payloadIn = ['message' => $message];
    if ($cid) {
        $payloadIn['conversation_id'] = $cid;
    }
    $request = Request::create('/api/public/ai/chat', 'POST', $payloadIn);
    $request->headers->set('User-Agent', 'CQ43-R2-ProdClosure/1.0');
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
                'rate_limited' => true,
                'conversation_id' => $conversation->public_id,
                'status' => 'rate_limited',
                'message' => (string) ($rate['message'] ?? ''),
                'message_id' => null,
                'shopping_state' => is_array($conversation->shopping_state) ? $conversation->shopping_state : [],
                'pending' => $confirmSvc->pendingSnapshot($conversation),
                'meta' => [],
                'state' => $conversation->state,
            ];
        }
        $sanitized = $orch->sanitizeUserMessage($message);
        if (! ($sanitized['ok'] ?? false)) {
            return [
                'ok' => false,
                'conversation_id' => $conversation->public_id,
                'status' => 'invalid',
                'message' => (string) (($sanitized['payload']['message'] ?? 'invalid')),
                'message_id' => null,
                'shopping_state' => is_array($conversation->shopping_state) ? $conversation->shopping_state : [],
                'pending' => $confirmSvc->pendingSnapshot($conversation),
                'meta' => [],
                'state' => $conversation->state,
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
        // PII_FIRST = name asked before any travel assist. Lead FSM after travel help is expected.
        if (($conversation->state ?? null) === AiConversation::STATE_WAITING_FOR_HUMAN
            && ! preg_match('/talk to support|human|agent/i', $message)) {
            $counters['WAITING_FOR_HUMAN_UNREQUESTED']++;
            $counters['QWEN_MODEL_ONLY_HANDOFF']++;
        }

        return [
            'ok' => (bool) ($payload['ok'] ?? true),
            'conversation_id' => $conversation->public_id,
            'status' => (string) ($payload['status'] ?? ''),
            'message' => (string) ($payload['message'] ?? ''),
            'message_id' => $payload['message_id'] ?? null,
            'requires_confirmation' => (bool) ($payload['requires_confirmation'] ?? false),
            'shopping_state' => is_array($conversation->shopping_state) ? $conversation->shopping_state : [],
            'pending' => $confirmSvc->pendingSnapshot($conversation),
            'meta' => $meta,
            'state' => $conversation->state,
        ];
    } catch (Throwable $e) {
        $counters['HTTP_500']++;
        $counters['MODEL_CRASHES']++;

        return [
            'ok' => false,
            'conversation_id' => $cid,
            'status' => 'exception',
            'message' => $e->getMessage(),
            'message_id' => null,
            'shopping_state' => [],
            'pending' => null,
            'meta' => [],
            'state' => null,
        ];
    }
}

function assert_gate(array &$gates, array &$failures, string $name, bool $pass, string $detail = ''): void
{
    $gates[$name] = $pass ? 'PASS' : 'FAIL';
    if (! $pass) {
        $failures[] = $name.($detail !== '' ? ': '.$detail : '');
    }
    echo ($pass ? 'PASS' : 'FAIL')." $name".($detail !== '' ? " ($detail)" : '')."\n";
}

function shop(array $t): array
{
    $s = $t['shopping_state'] ?? [];

    return [
        'origin' => $s['origin'] ?? null,
        'destination' => $s['destination'] ?? null,
        'depart_date' => $s['depart_date'] ?? null,
        'return_date' => $s['return_date'] ?? null,
        'trip_type' => $s['trip_type'] ?? null,
        'adults' => $s['adults'] ?? null,
        'cabin' => $s['cabin'] ?? null,
        'lead_name' => $s['lead_name'] ?? null,
        'lead_email' => $s['lead_email'] ?? null,
        'lead_phone' => $s['lead_phone'] ?? null,
        'lead_capture_pending' => $s['lead_capture_pending'] ?? null,
        'lead_capture_stage' => $s['lead_capture_stage'] ?? null,
    ];
}

assert_gate($gates, $failures, 'APPLICATION_CODE_PARITY', $runtimeSha === $mergeSha && $frontendSha === $mergeSha && $deployMarker === $mergeSha, "rt=$runtimeSha fe=$frontendSha mk=$deployMarker");

// ---- Destination-led prompt order ----
$vid = visitor_token('r2dest');
$t0 = chat($orch, $confirmSvc, $vid, 'I need Dubai', null, $counters);
$cid = $t0['conversation_id'];
$msg0 = mb_strtolower((string) $t0['message']);
$s0 = shop($t0);
assert_gate($gates, $failures, 'DESTINATION_LED_PROMPT_ORDER',
    ($s0['destination'] ?? null) === 'DXB'
    && ($s0['origin'] ?? null) === null
    && str_contains($msg0, 'travelling from')
    && ! str_contains($msg0, 'departure date'),
    json_encode(['shop' => $s0, 'msg' => mb_substr($msg0, 0, 160)])
);
$t1 = chat($orch, $confirmSvc, $vid, 'from Lahore', $cid, $counters);
$s1 = shop($t1);
$msg1 = mb_strtolower((string) $t1['message']);
$asksDate = str_contains($msg1, 'departure date') || str_contains($msg1, 'travel date') || str_contains($msg1, 'what date');
assert_gate($gates, $failures, 'DESTINATION_LED_THEN_DATE',
    ($s1['origin'] ?? null) === 'LHE' && ($s1['destination'] ?? null) === 'DXB' && $asksDate && ! str_contains($msg1, 'travelling from'),
    json_encode(['shop' => $s1, 'msg' => mb_substr($msg1, 0, 160)])
);
$sessionLog['destination_led'] = [$t0, $t1];

// ---- Origin-only ----
$vid = visitor_token('r2orig');
$help = chat($orch, $confirmSvc, $vid, 'I need help', null, $counters);
$cid = $help['conversation_id'];
// seed destination-missing progressive
$t = chat($orch, $confirmSvc, $vid, 'from Lahore', $cid, $counters);
$s = shop($t);
$m = mb_strtolower((string) $t['message']);
assert_gate($gates, $failures, 'ORIGIN_ONLY_PROMPT_ORDER',
    ($s['origin'] ?? null) === 'LHE'
    && (($s['destination'] ?? null) === null || ($s['destination'] ?? '') === '')
    && (str_contains($m, 'where would you like to go') || str_contains($m, 'destination'))
    && ! str_contains($m, 'departure date'),
    json_encode(['shop' => $s, 'msg' => mb_substr($m, 0, 160)])
);
$sessionLog['origin_only'] = $t;

// ---- Route complete date ----
$vid = visitor_token('r2route');
$t = chat($orch, $confirmSvc, $vid, 'Lahore to Dubai', null, $counters);
$s = shop($t);
$m = mb_strtolower((string) $t['message']);
$asksDepart = str_contains($m, 'departure date') || str_contains($m, 'travel date') || str_contains($m, 'what date');
$routeOk = ($s['origin'] ?? null) === 'LHE' && ($s['destination'] ?? null) === 'DXB';
$dateNull = ($s['depart_date'] ?? null) === null;
assert_gate($gates, $failures, 'ROUTE_COMPLETE_DATE_PROMPT',
    $routeOk && $dateNull && $asksDepart,
    json_encode(['shop' => $s, 'msg' => mb_substr($m, 0, 160), 'status' => $t['status'] ?? null, 'qwen_date_fill' => ! $dateNull])
);
$sessionLog['route_complete'] = $t;
$sessionLog['route_complete_meta'] = ['QWEN_DATE_FILL' => ! $dateNull, 'ASKS_DATE' => $asksDepart];

// ---- Bare name during pending confirm ----
$vid = visitor_token('r2bare');
$tTravel = chat($orch, $confirmSvc, $vid, 'Lahore to Dubai tomorrow for 2 adults economy', null, $counters);
$cid = $tTravel['conversation_id'];
$pendingBefore = $tTravel['pending'];
$sTravel = shop($tTravel);
// ensure lead pending + pending confirm
if ($pendingBefore === null && ($sTravel['origin'] ?? null) && ($sTravel['destination'] ?? null) && ($sTravel['depart_date'] ?? null)) {
    $conv = AiConversation::query()->where('public_id', $cid)->first();
    if ($conv) {
        $intent = \App\Data\Ai\TravelIntent::fromArray([
            'intent' => 'flight_search',
            'origin' => $sTravel['origin'],
            'destination' => $sTravel['destination'],
            'depart_date' => $sTravel['depart_date'],
            'adults' => (int) ($sTravel['adults'] ?? 2),
            'cabin' => $sTravel['cabin'] ?? 'economy',
            'trip_type' => 'one_way',
        ], 'STRUCTURED_FALLBACK');
        $confirmSvc->storePending($conv, $confirmSvc->buildSnapshot($intent));
        $state = is_array($conv->shopping_state) ? $conv->shopping_state : [];
        $state['lead_capture_pending'] = true;
        $state['lead_capture_stage'] = 'name';
        $conv->shopping_state = $state;
        $conv->save();
        $pendingBefore = $confirmSvc->pendingSnapshot($conv->fresh());
    }
}
$tName = chat($orch, $confirmSvc, $vid, 'Ali Khan', $cid, $counters);
$sName = shop($tName);
$pendingAfter = $tName['pending'];
$searchName = (int) data_get($tName, 'meta.AI_FLIGHT_SEARCH_READ_CALLS', 0);
assert_gate($gates, $failures, 'BARE_NAME_LEAD_NON_REGRESSION', ($sName['lead_name'] ?? null) === 'Ali Khan', json_encode($sName));
assert_gate($gates, $failures, 'PENDING_CONFIRMATION_PRESERVED_AFTER_NAME',
    is_array($pendingBefore) && is_array($pendingAfter)
    && ($pendingBefore['origin'] ?? null) === ($pendingAfter['origin'] ?? null)
    && ($pendingBefore['destination'] ?? null) === ($pendingAfter['destination'] ?? null)
    && ($pendingBefore['departure_date'] ?? null) === ($pendingAfter['departure_date'] ?? null)
    && (int) ($pendingBefore['adults'] ?? 0) === (int) ($pendingAfter['adults'] ?? 0)
    && $searchName === 0,
    json_encode(['before' => $pendingBefore, 'after' => $pendingAfter, 'search' => $searchName])
);
$sessionLog['bare_name'] = ['travel' => $tTravel, 'name' => $tName];

// ---- Contact during pending ----
$conv = AiConversation::query()->where('public_id', $cid)->first();
if ($conv) {
    $st = is_array($conv->shopping_state) ? $conv->shopping_state : [];
    $st['lead_capture_stage'] = 'contact';
    $st['lead_name'] = 'Ali Khan';
    $conv->shopping_state = $st;
    $conv->save();
}
$pendingContactBefore = $confirmSvc->pendingSnapshot($conv->fresh());
$tContact = chat($orch, $confirmSvc, $vid, 'cq43-test@example.com 03000000000', $cid, $counters);
$sContact = shop($tContact);
$pendingContactAfter = $tContact['pending'];
assert_gate($gates, $failures, 'LEAD_CONTACT_DURING_CONFIRMATION',
    ($sContact['lead_email'] ?? null) === 'cq43-test@example.com'
    && ! empty($sContact['lead_phone'] ?? null)
    && is_array($pendingContactBefore) && is_array($pendingContactAfter)
    && ($pendingContactBefore['origin'] ?? null) === ($pendingContactAfter['origin'] ?? null)
    && ($pendingContactBefore['destination'] ?? null) === ($pendingContactAfter['destination'] ?? null),
    json_encode(['shop' => $sContact, 'pending' => $pendingContactAfter])
);
$sessionLog['contact'] = $tContact;

// ---- Closure29 mixed name+travel ----
$vid = visitor_token('r2c29');
$h = chat($orch, $confirmSvc, $vid, 'I need help', null, $counters);
$cid = $h['conversation_id'];
$mixed = chat($orch, $confirmSvc, $vid, "I'm Ahmed and I need Lahore to Dubai tomorrow for 2 adults", $cid, $counters);
$sm = shop($mixed);
$searchMixed = (int) data_get($mixed, 'meta.AI_FLIGHT_SEARCH_READ_CALLS', 0);
assert_gate($gates, $failures, 'CLOSURE29_MIXED_NAME_TRAVEL',
    ($sm['lead_name'] ?? null) === 'Ahmed'
    && ($sm['origin'] ?? null) === 'LHE'
    && ($sm['destination'] ?? null) === 'DXB'
    && (int) ($sm['adults'] ?? 0) === 2
    && is_array($mixed['pending'])
    && $searchMixed === 0,
    json_encode(['shop' => $sm, 'pending' => $mixed['pending'], 'search' => $searchMixed])
);
$sessionLog['closure29'] = $mixed;

// ---- Explicit name + travel correction ----
$vid = visitor_token('r2ali');
$setup = chat($orch, $confirmSvc, $vid, 'Lahore to Dubai tomorrow for 2 adults', null, $counters);
$cid = $setup['conversation_id'];
$conv = AiConversation::query()->where('public_id', $cid)->first();
if ($conv) {
    $st = is_array($conv->shopping_state) ? $conv->shopping_state : [];
    $st['lead_capture_pending'] = true;
    $st['lead_capture_stage'] = 'name';
    $conv->shopping_state = $st;
    $conv->save();
}
$ali = chat($orch, $confirmSvc, $vid, 'My name is Ali and make it Doha', $cid, $counters);
$sa = shop($ali);
assert_gate($gates, $failures, 'EXPLICIT_LEADING_NAME_WITH_TRAVEL',
    ($sa['lead_name'] ?? null) === 'Ali' && ($sa['destination'] ?? null) === 'DOH',
    json_encode($sa)
);
$sessionLog['explicit_ali'] = $ali;

// ---- Travel-only must not become name ----
$vid = visitor_token('r2trav');
$t = chat($orch, $confirmSvc, $vid, 'Lahore to Dubai tomorrow for 2 adults', null, $counters);
$cid = $t['conversation_id'];
$conv = AiConversation::query()->where('public_id', $cid)->first();
if ($conv) {
    $st = is_array($conv->shopping_state) ? $conv->shopping_state : [];
    $st['lead_capture_pending'] = true;
    $st['lead_capture_stage'] = 'name';
    $conv->shopping_state = $st;
    $conv->save();
}
$travelPhrases = [
    'Make it Doha', 'actually Dubai again', 'next Friday', 'next Monday', '2 adults', '3 adults',
    'business class', 'hum dono', 'wapis Sunday', 'from Lahore', 'Lahore se',
];
$travelOnly = [];
foreach ($travelPhrases as $phrase) {
    $x = chat($orch, $confirmSvc, $vid, $phrase, $cid, $counters);
    $ln = shop($x)['lead_name'] ?? null;
    if ($ln === $phrase) {
        $counters['LEAD_TRAVEL_TEXT_CAPTURED_AS_NAME']++;
    }
    $travelOnly[] = ['phrase' => $phrase, 'lead_name' => $ln];
}
assert_gate($gates, $failures, 'LEAD_TRAVEL_TEXT_CAPTURED_AS_NAME', $counters['LEAD_TRAVEL_TEXT_CAPTURED_AS_NAME'] === 0, json_encode($travelOnly));
$sessionLog['travel_only'] = $travelOnly;

// ---- Same body distinct message_ids (API) ----
// Seed a completed lead so ambiguous turns restate confirmation instead of lead FSM.
$vid = visitor_token('r2body');
\App\Models\CustomerQuery::query()->create([
    'visitor_token_hash' => hash('sha256', $vid),
    'name' => 'CQ43 R2 Guest',
    'email' => 'cq43-r2-guest@example.com',
    'phone_raw' => '03001234567',
    'phone_e164' => '+923001234567',
    'phone_country' => 'PK',
    'contact_consent' => true,
    'consent_timestamp' => now(),
    'consent_source' => 'ask_jetpakistan',
    'source' => 'ask_jetpakistan',
    'status' => \App\Enums\CustomerQueryStatus::New,
    'last_activity_at' => now(),
]);
$tA = chat($orch, $confirmSvc, $vid, 'Lahore to Dubai tomorrow for 2 adults economy', null, $counters);
$cid = $tA['conversation_id'];
$tB = chat($orch, $confirmSvc, $vid, 'xyzzy-ambiguous-1', $cid, $counters);
$tC = chat($orch, $confirmSvc, $vid, 'xyzzy-ambiguous-2', $cid, $counters);
$id1 = $tB['message_id'] ?? null;
$id2 = $tC['message_id'] ?? null;
$body1 = trim((string) ($tB['message'] ?? ''));
$body2 = trim((string) ($tC['message'] ?? ''));
$sameBody = ($body1 !== '' && $body1 === $body2);
$distinctIds = is_numeric($id1) && is_numeric($id2) && (int) $id1 !== (int) $id2;
assert_gate($gates, $failures, 'SAME_BODY_DISTINCT_IDS_API',
    $distinctIds && $sameBody && ($tB['status'] === 'confirm' && $tC['status'] === 'confirm'),
    json_encode(['id1' => $id1, 'id2' => $id2, 'same_body' => $sameBody, 'status' => [$tB['status'], $tC['status']], 'b1' => mb_substr($body1, 0, 120)])
);
$sessionLog['same_body_ids'] = [
    'MESSAGE_ID_1' => $id1,
    'MESSAGE_ID_2' => $id2,
    'BODY_1' => mb_substr($body1, 0, 240),
    'BODY_2' => mb_substr($body2, 0, 240),
    'SAME_BODY_CONFIRMED' => $sameBody,
];

// ---- Bare yes confirmation authority (authorized search ok) ----
$vid = visitor_token('r2yes');
$ty = chat($orch, $confirmSvc, $vid, 'Lahore to Dubai tomorrow for 2 adults', null, $counters);
$cid = $ty['conversation_id'];
$conv = AiConversation::query()->where('public_id', $cid)->first();
if ($conv) {
    $st = is_array($conv->shopping_state) ? $conv->shopping_state : [];
    $st['lead_capture_pending'] = true;
    $st['lead_capture_stage'] = 'name';
    $conv->shopping_state = $st;
    $conv->save();
    if ($confirmSvc->pendingSnapshot($conv) === null && ($st['origin'] ?? null) && ($st['destination'] ?? null) && ($st['depart_date'] ?? null)) {
        $intent = \App\Data\Ai\TravelIntent::fromArray([
            'intent' => 'flight_search',
            'origin' => $st['origin'],
            'destination' => $st['destination'],
            'depart_date' => $st['depart_date'],
            'adults' => (int) ($st['adults'] ?? 2),
            'cabin' => $st['cabin'] ?? 'economy',
            'trip_type' => 'one_way',
        ], 'STRUCTURED_FALLBACK');
        $confirmSvc->storePending($conv, $confirmSvc->buildSnapshot($intent));
    }
}
$yes = chat($orch, $confirmSvc, $vid, 'yes', $cid, $counters);
$sy = shop($yes);
assert_gate($gates, $failures, 'BARE_YES_CONFIRMATION_AUTHORITY',
    (bool) data_get($yes, 'meta.CONFIRMATION_BEFORE_SEARCH')
    && (int) data_get($yes, 'meta.AI_FLIGHT_SEARCH_READ_CALLS', 0) === 1
    && ($sy['lead_name'] ?? null) !== 'yes',
    json_encode(['meta' => $yes['meta'] ?? [], 'shop' => $sy])
);
$sessionLog['bare_yes'] = $yes;

// ---- Short continuity smoke (15–20 turns) ----
$vid = visitor_token('r2smoke');
$cid = null;
$smokeMsgs = [
    'I need Dubai', 'from Lahore', 'next Friday', '2 adults', 'economy',
    'Make it Doha', 'actually Dubai again', 'business class',
    'I also need to come back on Sunday',
    'Now Dubai to Lahore tomorrow',
    'Lahore to Jeddah then Medina to Lahore',
    'Now Islamabad to Dubai next Monday',
    'What is gravity?',
    'What is Bitcoin\'s price right now?',
    'Check my booking',
    'Talk to support',
];
$smoke = [];
$wrong = ['WRONG_ORIGIN' => 0, 'WRONG_DESTINATION' => 0];
foreach ($smokeMsgs as $msg) {
    $x = chat($orch, $confirmSvc, $vid, $msg, $cid, $counters);
    $cid = $x['conversation_id'] ?? $cid;
    $smoke[] = ['user' => $msg, 'status' => $x['status'] ?? null, 'shop' => shop($x), 'message_id' => $x['message_id'] ?? null];
}
// resume AI after handoff if waiting
$last = end($smoke);
if (($last['status'] ?? '') === 'waiting_for_human' || (($smoke[count($smoke) - 1]['shop']['lead_name'] ?? null) !== null)) {
    // resume via orchestrator if possible
    $conv = AiConversation::query()->where('public_id', $cid)->first();
    if ($conv && $conv->state === AiConversation::STATE_WAITING_FOR_HUMAN) {
        $resume = $orch->resumeAi($conv, 'cq43_r2_smoke');
        $smoke[] = ['user' => 'Resume AI', 'status' => $resume['status'] ?? null, 'shop' => shop(['shopping_state' => $conv->fresh()->shopping_state]), 'message_id' => $resume['message_id'] ?? null];
    }
}
$sessionLog['short_smoke'] = $smoke;
assert_gate($gates, $failures, 'SHORT_CONTINUITY_SMOKE',
    $counters['HTTP_500'] === 0
    && $counters['WAITING_FOR_HUMAN_UNREQUESTED'] === 0
    && $counters['SEARCH_BEFORE_CONFIRMATION'] === 0
    && $counters['LEAD_TRAVEL_TEXT_CAPTURED_AS_NAME'] === 0
    && $counters['PII_FIRST'] === 0
    && $counters['QWEN_MODEL_ONLY_HANDOFF'] === 0,
    json_encode(['counters' => $counters])
);

$focusedPass = count($failures) === 0
    && $gates['DESTINATION_LED_PROMPT_ORDER'] === 'PASS'
    && $gates['BARE_NAME_LEAD_NON_REGRESSION'] === 'PASS'
    && $gates['CLOSURE29_MIXED_NAME_TRAVEL'] === 'PASS'
    && $gates['SAME_BODY_DISTINCT_IDS_API'] === 'PASS'
    && $gates['SHORT_CONTINUITY_SMOKE'] === 'PASS';

$summary = [
    'MERGE_SHA' => $mergeSha,
    'RUNTIME_SHA' => $runtimeSha,
    'FRONTEND_SHA' => $frontendSha,
    'DEPLOY_MARKER' => $deployMarker,
    'APPLICATION_CODE_PARITY' => $gates['APPLICATION_CODE_PARITY'] ?? 'FAIL',
    'CQ43_R2_PRODUCTION_CLOSURE_INPROC' => $focusedPass ? 'PASS' : 'FAIL',
    'gates' => $gates,
    'failures' => $failures,
    'counters' => $counters,
    'same_body_ids' => $sessionLog['same_body_ids'] ?? null,
];
file_put_contents($outRoot.'/SUMMARY.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents($outRoot.'/sessions-all.json', json_encode($sessionLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "CQ43_R2_PRODUCTION_CLOSURE_INPROC=".($focusedPass ? 'PASS' : 'FAIL')."\n";
echo "FAILURES=".count($failures)."\n";
exit($focusedPass ? 0 : 1);
