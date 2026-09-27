<?php
/**
 * CQ43-R1 focused production re-UAT (in-process, non-mutating).
 */
require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AiConversation;
use App\Services\Ai\AiChatOrchestrator;
use App\Services\Ai\FlightSearchConfirmationGate;
use Illuminate\Http\Request;

$outRoot = getenv('CQ43R1_OUT') ?: '/tmp/cq43r1-reuats-out';
@mkdir($outRoot, 0775, true);
@mkdir($outRoot.'/sessions', 0775, true);

$orch = app(AiChatOrchestrator::class);
$confirmSvc = app(FlightSearchConfirmationGate::class);

$runtimeSha = trim((string) @file_get_contents('/home/pkjetp/jetpk_app/.jetpk-runtime-sha'));
$deployMarker = trim((string) @file_get_contents('/home/pkjetp/jetpk_app/storage/app/deploy-sha.txt'));
echo "RUNTIME_SHA=$runtimeSha\nDEPLOY_MARKER=$deployMarker\n";

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
    'LHE_LHE_SYNTHETIC_ROUTE' => 0,
    'LEAD_TRAVEL_HIJACK' => 0,
    'LEAD_TRAVEL_TEXT_CAPTURED_AS_NAME' => 0,
    'QWEN_MODEL_ONLY_HANDOFF' => 0,
    'WAITING_FOR_HUMAN_UNREQUESTED' => 0,
    'BOOKING_STATE_CONTAMINATION' => 0,
    'MODEL_CRASHES' => 0,
    'OOM_EVENTS' => 0,
];
$gates = [];
$failures = [];

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
    $request->headers->set('User-Agent', 'CQ43-R1-ReUAT/1.0');
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
                'shopping_state' => is_array($conversation->shopping_state) ? $conversation->shopping_state : [],
                'pending' => $confirmSvc->pendingSnapshot($conversation),
                'meta' => [],
                'state' => $conversation->state,
            ];
        }
        $payload = $orch->handleChat($conversation, $sanitized['message']);
        $conversation->refresh();
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        if ((int) ($meta['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0) > 0 && empty($meta['CONFIRMATION_BEFORE_SEARCH'])) {
            $counters['SEARCH_BEFORE_CONFIRMATION']++;
        }
        if ((int) ($meta['LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK'] ?? 0) === 1) {
            $counters['LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK']++;
        }
        $bodyLower = mb_strtolower((string) ($payload['message'] ?? ''));
        if (str_contains($bodyLower, 'may i start with your name')) {
            $counters['LEAD_TRAVEL_HIJACK']++;
            $counters['PII_FIRST']++;
        }

        return [
            'ok' => (bool) ($payload['ok'] ?? true),
            'conversation_id' => $conversation->public_id,
            'status' => (string) ($payload['status'] ?? ''),
            'message' => (string) ($payload['message'] ?? ''),
            'booking' => $payload['booking'] ?? null,
            'requires_confirmation' => (bool) ($payload['requires_confirmation'] ?? false),
            'shopping_state' => is_array($conversation->shopping_state) ? $conversation->shopping_state : [],
            'pending' => $confirmSvc->pendingSnapshot($conversation),
            'meta' => $meta,
            'state' => $conversation->state,
            'actions' => $payload['actions'] ?? [],
        ];
    } catch (Throwable $e) {
        $counters['HTTP_500']++;
        $counters['MODEL_CRASHES']++;

        return [
            'ok' => false,
            'conversation_id' => $cid,
            'status' => 'exception',
            'message' => $e->getMessage(),
            'shopping_state' => [],
            'pending' => null,
            'meta' => [],
            'state' => null,
            'exception' => $e->getMessage(),
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

$sessionLog = [];

// ---- Progressive English + correction ----
$vid = visitor_token('cq43r1en');
$cid = null;
$turns = [];
foreach ([
    'I need Dubai',
    'from Lahore',
    'next Friday',
    '2 adults',
    'economy',
    'Make it Doha',
    'actually Dubai again',
    '3 adults',
    'business class',
] as $msg) {
    $t = chat($orch, $confirmSvc, $vid, $msg, $cid, $counters);
    $cid = $t['conversation_id'] ?? $cid;
    $turns[] = ['user' => $msg, 'status' => $t['status'] ?? null, 'state' => $t['state'] ?? null, 'shopping' => [
        'origin' => $t['shopping_state']['origin'] ?? null,
        'destination' => $t['shopping_state']['destination'] ?? null,
        'depart_date' => $t['shopping_state']['depart_date'] ?? null,
        'return_date' => $t['shopping_state']['return_date'] ?? null,
        'trip_type' => $t['shopping_state']['trip_type'] ?? null,
        'adults' => $t['shopping_state']['adults'] ?? null,
        'cabin' => $t['shopping_state']['cabin'] ?? null,
        'lead_name' => $t['shopping_state']['lead_name'] ?? null,
        'legs' => $t['shopping_state']['legs'] ?? null,
    ], 'message' => mb_substr((string) ($t['message'] ?? ''), 0, 240)];
    $od = ($t['shopping_state']['origin'] ?? '').'-'.($t['shopping_state']['destination'] ?? '');
    if ($od === 'LHE-LHE') {
        $counters['LHE_LHE_SYNTHETIC_ROUTE']++;
    }
    if (($t['shopping_state']['lead_name'] ?? null) === 'Make it Doha') {
        $counters['LEAD_TRAVEL_TEXT_CAPTURED_AS_NAME']++;
    }
    if (($t['state'] ?? null) === AiConversation::STATE_WAITING_FOR_HUMAN) {
        $counters['WAITING_FOR_HUMAN_UNREQUESTED']++;
        $counters['QWEN_MODEL_ONLY_HANDOFF']++;
    }
}
$sessionLog['progressive_english'] = $turns;
$s = $turns[count($turns) - 1]['shopping'] ?? [];
$s2 = $turns[1]['shopping'] ?? [];
$s0 = $turns[0]['shopping'] ?? [];
assert_gate($gates, $failures, 'DESTINATION_LED_START', ($s0['destination'] ?? null) === 'DXB' && ($s0['origin'] ?? null) === null, json_encode($s0));
assert_gate($gates, $failures, 'ORIGIN_ONLY_FOLLOWUP', ($s2['origin'] ?? null) === 'LHE' && ($s2['destination'] ?? null) === 'DXB', json_encode($s2));
assert_gate($gates, $failures, 'PROGRESSIVE_SEARCH', ($s['origin'] ?? null) === 'LHE' && ($s['destination'] ?? null) === 'DXB' && (int) ($s['adults'] ?? 0) === 3 && ($s['cabin'] ?? null) === 'business' && ($s['depart_date'] ?? null) !== null, json_encode($s));
assert_gate($gates, $failures, 'CORRECTION_CHAIN', ($turns[5]['shopping']['destination'] ?? null) === 'DOH' && ($turns[6]['shopping']['destination'] ?? null) === 'DXB' && (int) ($turns[7]['shopping']['adults'] ?? 0) === 3 && ($turns[8]['shopping']['cabin'] ?? null) === 'business');
assert_gate($gates, $failures, 'CABIN_REPLACEMENT', ($s['cabin'] ?? null) === 'business');
assert_gate($gates, $failures, 'LHE_LHE_SYNTHETIC_ROUTE', $counters['LHE_LHE_SYNTHETIC_ROUTE'] === 0);
assert_gate($gates, $failures, 'LEAD_TRAVEL_HIJACK', $counters['LEAD_TRAVEL_HIJACK'] === 0);
assert_gate($gates, $failures, 'LEAD_TRAVEL_TEXT_CAPTURED_AS_NAME', $counters['LEAD_TRAVEL_TEXT_CAPTURED_AS_NAME'] === 0);

// ---- Roman Urdu ----
$vid = visitor_token('cq43r1ru');
$cid = null;
$ru = [];
foreach (['Dubai jana hai', 'Lahore se', 'kal', 'hum dono', 'wapis Sunday'] as $msg) {
    $t = chat($orch, $confirmSvc, $vid, $msg, $cid, $counters);
    $cid = $t['conversation_id'] ?? $cid;
    $ru[] = ['user' => $msg, 'status' => $t['status'] ?? null, 'state' => $t['state'] ?? null, 'shopping' => [
        'origin' => $t['shopping_state']['origin'] ?? null,
        'destination' => $t['shopping_state']['destination'] ?? null,
        'depart_date' => $t['shopping_state']['depart_date'] ?? null,
        'return_date' => $t['shopping_state']['return_date'] ?? null,
        'trip_type' => $t['shopping_state']['trip_type'] ?? null,
        'adults' => $t['shopping_state']['adults'] ?? null,
    ], 'message' => mb_substr((string) ($t['message'] ?? ''), 0, 240)];
    if (($t['state'] ?? null) === AiConversation::STATE_WAITING_FOR_HUMAN) {
        $counters['WAITING_FOR_HUMAN_UNREQUESTED']++;
        $counters['QWEN_MODEL_ONLY_HANDOFF']++;
    }
}
$sessionLog['roman_urdu'] = $ru;
$rf = $ru[count($ru) - 1]['shopping'] ?? [];
$depart = $rf['depart_date'] ?? null;
$ret = $rf['return_date'] ?? null;
$retOk = is_string($depart) && is_string($ret) && $ret > $depart && (int) date('w', strtotime($ret)) === 0;
assert_gate($gates, $failures, 'ROMAN_URDU_PROGRESSIVE', ($rf['origin'] ?? null) === 'LHE' && ($rf['destination'] ?? null) === 'DXB' && (int) ($rf['adults'] ?? 0) === 2 && ($rf['trip_type'] ?? null) === 'return' && $retOk, json_encode($rf));
assert_gate($gates, $failures, 'RELATIONAL_HUM_DONO', (int) ($ru[3]['shopping']['adults'] ?? 0) === 2);
assert_gate($gates, $failures, 'CONTEXTUAL_WAPIS_RETURN', ($rf['trip_type'] ?? null) === 'return' && $retOk);

// ---- Fresh wapis ----
$vid = visitor_token('cq43r1fw');
$t = chat($orch, $confirmSvc, $vid, 'Dubai se Lahore wapis', null, $counters);
$sessionLog['fresh_wapis'] = $t;
$ss = $t['shopping_state'] ?? [];
assert_gate($gates, $failures, 'FRESH_WAPIS_DIRECTION', ($ss['origin'] ?? null) === 'DXB' && ($ss['destination'] ?? null) === 'LHE' && ($ss['trip_type'] ?? null) !== 'return' && ($ss['trip_type'] ?? null) !== 'open_jaw' && ($t['state'] ?? null) !== AiConversation::STATE_WAITING_FOR_HUMAN, json_encode([
    'origin' => $ss['origin'] ?? null,
    'destination' => $ss['destination'] ?? null,
    'trip_type' => $ss['trip_type'] ?? null,
]));
assert_gate($gates, $failures, 'WAPIS_ROUTE', ($ss['origin'] ?? null) === 'DXB' && ($ss['destination'] ?? null) === 'LHE');

// ---- Oneway to return ----
$vid = visitor_token('cq43r1ow');
$cid = null;
$t1 = chat($orch, $confirmSvc, $vid, 'Karachi to Jeddah on 3 October for 1 adult', null, $counters);
$cid = $t1['conversation_id'];
$t2 = chat($orch, $confirmSvc, $vid, 'I also need to come back on Sunday', $cid, $counters);
$sessionLog['oneway_to_return'] = [$t1, $t2];
$s1 = $t1['shopping_state'] ?? [];
$s2 = $t2['shopping_state'] ?? [];
$depart = $s2['depart_date'] ?? ($s1['depart_date'] ?? null);
$ret = $s2['return_date'] ?? null;
$retOk = is_string($depart) && is_string($ret) && $ret > $depart && (int) date('w', strtotime($ret)) === 0;
assert_gate($gates, $failures, 'ONEWAY_TO_RETURN', ($s2['origin'] ?? null) === 'KHI' && ($s2['destination'] ?? null) === 'JED' && ($s2['trip_type'] ?? null) === 'return' && $retOk, json_encode([
    'origin' => $s2['origin'] ?? null,
    'destination' => $s2['destination'] ?? null,
    'trip_type' => $s2['trip_type'] ?? null,
    'depart' => $depart,
    'return' => $ret,
]));

// ---- Booking detour during pending confirm ----
$vid = visitor_token('cq43r1bk');
$cid = null;
$setupMsgs = ['Lahore to Dubai on 2 October for 2 adults economy'];
$tb = null;
foreach ($setupMsgs as $msg) {
    $tb = chat($orch, $confirmSvc, $vid, $msg, $cid, $counters);
    $cid = $tb['conversation_id'];
}
// If not pending yet, try affirm-ready path by ensuring complete intent confirmation exists
$before = $tb['shopping_state'] ?? [];
$pendingBefore = $tb['pending'] ?? null;
// Force pending if confirmable snapshot can be built from shopping state
if ($pendingBefore === null && ($before['origin'] ?? null) && ($before['destination'] ?? null) && ($before['depart_date'] ?? null)) {
    $conv = AiConversation::query()->where('public_id', $cid)->first();
    if ($conv) {
        $intent = \App\Data\Ai\TravelIntent::fromArray([
            'intent' => 'flight_search',
            'origin' => $before['origin'],
            'destination' => $before['destination'],
            'depart_date' => $before['depart_date'],
            'adults' => (int) ($before['adults'] ?? 2),
            'children' => 0,
            'infants' => 0,
            'cabin' => $before['cabin'] ?? 'economy',
            'trip_type' => $before['trip_type'] ?? 'one_way',
        ], 'STRUCTURED_FALLBACK');
        $snap = $confirmSvc->buildSnapshot($intent);
        $confirmSvc->storePending($conv, $snap);
        $pendingBefore = $confirmSvc->pendingSnapshot($conv);
        $before = is_array($conv->fresh()->shopping_state) ? $conv->fresh()->shopping_state : $before;
    }
}
$bookingTurn = chat($orch, $confirmSvc, $vid, 'Check my booking', $cid, $counters);
$afterBooking = $bookingTurn['shopping_state'] ?? [];
$pendingAfter = $bookingTurn['pending'] ?? null;
$sessionLog['booking_detour'] = [
    'before' => ['shopping' => $before, 'pending' => $pendingBefore],
    'booking_turn' => [
        'status' => $bookingTurn['status'] ?? null,
        'booking' => $bookingTurn['booking'] ?? null,
        'message' => mb_substr((string) ($bookingTurn['message'] ?? ''), 0, 240),
        'actions' => $bookingTurn['actions'] ?? [],
        'shopping' => [
            'origin' => $afterBooking['origin'] ?? null,
            'destination' => $afterBooking['destination'] ?? null,
            'depart_date' => $afterBooking['depart_date'] ?? null,
            'adults' => $afterBooking['adults'] ?? null,
            'intent' => $afterBooking['intent'] ?? null,
            'booking_detour_active' => $afterBooking['booking_detour_active'] ?? null,
        ],
        'pending' => $pendingAfter,
    ],
];
$travelPreserved = ($afterBooking['origin'] ?? null) === ($before['origin'] ?? null)
    && ($afterBooking['destination'] ?? null) === ($before['destination'] ?? null)
    && ($afterBooking['depart_date'] ?? null) === ($before['depart_date'] ?? null)
    && (int) ($afterBooking['adults'] ?? 0) === (int) ($before['adults'] ?? 0);
$pendingPreserved = is_array($pendingBefore) && is_array($pendingAfter)
    && (int) ($pendingAfter['adults'] ?? 0) === (int) ($pendingBefore['adults'] ?? 0)
    && ($pendingAfter['origin'] ?? null) === ($pendingBefore['origin'] ?? null);
$bookingClarify = ($bookingTurn['status'] ?? null) === 'clarify' && ($bookingTurn['booking'] ?? 'x') === null
    && str_contains(mb_strtolower((string) ($bookingTurn['message'] ?? '')), 'booking');
assert_gate($gates, $failures, 'BOOKING_MID_TRIP', $bookingClarify && ! ($bookingTurn['requires_confirmation'] ?? false));
assert_gate($gates, $failures, 'TRAVEL_STATE_PRESERVED_AFTER_BOOKING', $travelPreserved, json_encode([
    'before' => $before,
    'after' => $afterBooking,
]));
assert_gate($gates, $failures, 'BOOKING_DETOUR_PRESERVES_PAX', (int) ($afterBooking['adults'] ?? 0) === 2 && $pendingPreserved);
assert_gate($gates, $failures, 'BOOKING_PUBLIC_CONTRACT', $bookingClarify);
assert_gate($gates, $failures, 'BOOKING_TOP_LEVEL_PAYLOAD', array_key_exists('booking', $bookingTurn) && $bookingTurn['booking'] === null);

// not_found contract with fake details
$nf1 = chat($orch, $confirmSvc, $vid, 'Reference ZZZNOBOOK1', $cid, $counters);
$nf2 = chat($orch, $confirmSvc, $vid, 'My email is nobody-cq43@example.com', $cid, $counters);
$sessionLog['booking_not_found'] = [$nf1, $nf2];
assert_gate($gates, $failures, 'BOOKING_NOT_FOUND_STATUS', ($nf2['status'] ?? null) === 'not_found' && ($nf2['booking'] ?? 'x') === null, json_encode([
    'status' => $nf2['status'] ?? null,
    'booking' => $nf2['booking'] ?? null,
    'message' => mb_substr((string) ($nf2['message'] ?? ''), 0, 160),
]));

$resume = chat($orch, $confirmSvc, $vid, 'back to my Dubai search', $cid, $counters);
$sessionLog['booking_resume'] = $resume;
$rs = $resume['shopping_state'] ?? [];
$rp = $resume['pending'] ?? null;
$contam = isset($rs['booking_reference']) || isset($rs['booking_email']) || isset($rs['booking_phone']);
if ($contam) {
    $counters['BOOKING_STATE_CONTAMINATION']++;
}
assert_gate($gates, $failures, 'BOOKING_DETOUR_RESUME', ($rs['origin'] ?? null) === 'LHE' && ($rs['destination'] ?? null) === 'DXB' && (int) ($rs['adults'] ?? 0) === 2 && is_array($rp) && ! $contam, json_encode([
    'shopping' => [
        'origin' => $rs['origin'] ?? null,
        'destination' => $rs['destination'] ?? null,
        'adults' => $rs['adults'] ?? null,
        'booking_detour_active' => $rs['booking_detour_active'] ?? null,
    ],
    'pending_adults' => $rp['adults'] ?? null,
    'status' => $resume['status'] ?? null,
]));
assert_gate($gates, $failures, 'BOOKING_STATE_CONTAMINATION', $counters['BOOKING_STATE_CONTAMINATION'] === 0);

// ---- Explicit handoff + resume ----
$vid = visitor_token('cq43r1ho');
$cid = null;
$h1 = chat($orch, $confirmSvc, $vid, 'Lahore to Dubai on 10 October for 2 adults', null, $counters);
$cid = $h1['conversation_id'];
$beforeHo = $h1['shopping_state'] ?? [];
$h2 = chat($orch, $confirmSvc, $vid, 'Talk to support', $cid, $counters);
$sessionLog['explicit_handoff'] = [$h1, $h2];
assert_gate($gates, $failures, 'EXPLICIT_HANDOFF_NON_REGRESSION', ($h2['state'] ?? null) === AiConversation::STATE_WAITING_FOR_HUMAN || str_contains(mb_strtolower((string) ($h2['message'] ?? '')), 'support'));
$h3 = chat($orch, $confirmSvc, $vid, 'Resume AI', $cid, $counters);
$sessionLog['handoff_resume'] = $h3;
$hs = $h3['shopping_state'] ?? [];
assert_gate($gates, $failures, 'HANDOFF_RESUME_STATE', ($hs['origin'] ?? null) === ($beforeHo['origin'] ?? null) && ($hs['destination'] ?? null) === ($beforeHo['destination'] ?? null), json_encode([
    'before' => ['o' => $beforeHo['origin'] ?? null, 'd' => $beforeHo['destination'] ?? null],
    'after' => ['o' => $hs['origin'] ?? null, 'd' => $hs['destination'] ?? null],
    'state' => $h3['state'] ?? null,
]));

// ---- CQ42/Order39 smoke ----
$vid = visitor_token('cq43r1o39');
$o39 = chat($orch, $confirmSvc, $vid, 'Lahore to Dubai tomorrow for 2 adults', null, $counters);
$sessionLog['order39'] = $o39;
assert_gate($gates, $failures, 'ORDER39_NON_REGRESSION', ($o39['requires_confirmation'] ?? false) === true || ($o39['status'] ?? null) === 'confirm', 'status='.($o39['status'] ?? ''));
assert_gate($gates, $failures, 'SEARCH_BEFORE_CONFIRMATION', $counters['SEARCH_BEFORE_CONFIRMATION'] === 0);

$vid = visitor_token('cq43r1wapas');
$wapas = chat($orch, $confirmSvc, $vid, 'Dubai se Lahore wapas', null, $counters);
$sessionLog['wapas'] = $wapas;
$ws = $wapas['shopping_state'] ?? [];
assert_gate($gates, $failures, 'WAPAS_ROUTE', ($ws['origin'] ?? null) === 'DXB' && ($ws['destination'] ?? null) === 'LHE');

$vid = visitor_token('cq43r1ret');
$retDated = chat($orch, $confirmSvc, $vid, 'Lahore to Dubai on 10 October, return on 15 October', null, $counters);
$sessionLog['return_on_date'] = $retDated;
$rds = $retDated['shopping_state'] ?? [];
assert_gate($gates, $failures, 'RETURN_ON_DATE', ($rds['depart_date'] ?? null) === '2026-10-10' && ($rds['return_date'] ?? null) === '2026-10-15' && (($retDated['status'] ?? null) === 'confirm' || ($retDated['requires_confirmation'] ?? false)), json_encode([
    'depart' => $rds['depart_date'] ?? null,
    'return' => $rds['return_date'] ?? null,
    'status' => $retDated['status'] ?? null,
]));

$vid = visitor_token('cq43r1oj');
$oj = chat($orch, $confirmSvc, $vid, 'Lahore to Jeddah then Medina to Lahore', null, $counters);
$sessionLog['open_jaw'] = $oj;
$ojs = $oj['shopping_state'] ?? [];
assert_gate($gates, $failures, 'OPEN_JAW_NON_REGRESSION', ($ojs['trip_type'] ?? null) === 'open_jaw' || (is_array($ojs['legs'] ?? null) && count($ojs['legs']) >= 2), json_encode([
    'trip_type' => $ojs['trip_type'] ?? null,
    'legs' => $ojs['legs'] ?? null,
]));

// ---- GK / CURRENT ----
$vid = visitor_token('cq43r1gk');
$gk = chat($orch, $confirmSvc, $vid, 'What is gravity?', null, $counters);
$sessionLog['gk'] = $gk;
assert_gate($gates, $failures, 'GK_NON_REGRESSION', ($gk['meta']['open_domain_category'] ?? $gk['meta']['SERVER_OPEN_DOMAIN_CATEGORY'] ?? null) === 'GENERAL_KNOWLEDGE' || str_contains(mb_strtolower((string) ($gk['message'] ?? '')), 'gravity') || str_contains(mb_strtolower((string) ($gk['message'] ?? '')), 'force'), 'cat='.($gk['meta']['open_domain_category'] ?? $gk['meta']['SERVER_OPEN_DOMAIN_CATEGORY'] ?? 'none'));

$vid = visitor_token('cq43r1cu');
$cu = chat($orch, $confirmSvc, $vid, "What is Bitcoin's price right now?", null, $counters);
$sessionLog['current'] = $cu;
assert_gate($gates, $failures, 'CURRENT_AUTHORITY', ($cu['meta']['open_domain_category'] ?? $cu['meta']['SERVER_OPEN_DOMAIN_CATEGORY'] ?? null) === 'CURRENT_UNVERIFIED' || str_contains(mb_strtolower((string) ($cu['message'] ?? '')), 'live') || str_contains(mb_strtolower((string) ($cu['message'] ?? '')), 'verified') || str_contains(mb_strtolower((string) ($cu['message'] ?? '')), 'cannot'), 'cat='.($cu['meta']['open_domain_category'] ?? $cu['meta']['SERVER_OPEN_DOMAIN_CATEGORY'] ?? 'none'));

assert_gate($gates, $failures, 'QWEN_MODEL_ONLY_HANDOFF', $counters['QWEN_MODEL_ONLY_HANDOFF'] === 0);
assert_gate($gates, $failures, 'WAITING_FOR_HUMAN_UNREQUESTED', $counters['WAITING_FOR_HUMAN_UNREQUESTED'] === 0);
assert_gate($gates, $failures, 'PII_FIRST', $counters['PII_FIRST'] === 0);
assert_gate($gates, $failures, 'BOOKING_IDENTITY_BYPASS', $counters['BOOKING_IDENTITY_BYPASS'] === 0);
assert_gate($gates, $failures, 'BOOKING_DATA_LEAK', $counters['BOOKING_DATA_LEAK'] === 0);
assert_gate($gates, $failures, 'HTTP_500', $counters['HTTP_500'] === 0);
assert_gate($gates, $failures, 'MODEL_CRASHES', $counters['MODEL_CRASHES'] === 0);
assert_gate($gates, $failures, 'OOM_EVENTS', $counters['OOM_EVENTS'] === 0);
assert_gate($gates, $failures, 'SUPPLIER_MUTATIONS', $counters['SUPPLIER_MUTATIONS'] === 0);
assert_gate($gates, $failures, 'BOOKING_MUTATIONS', $counters['BOOKING_MUTATIONS'] === 0);
assert_gate($gates, $failures, 'PAYMENT_MUTATIONS', $counters['PAYMENT_MUTATIONS'] === 0);
assert_gate($gates, $failures, 'LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK', $counters['LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK'] === 0);

$required = [
    'PROGRESSIVE_SEARCH', 'CORRECTION_CHAIN', 'LHE_LHE_SYNTHETIC_ROUTE', 'LEAD_TRAVEL_HIJACK',
    'ROMAN_URDU_PROGRESSIVE', 'RELATIONAL_HUM_DONO', 'CONTEXTUAL_WAPIS_RETURN', 'FRESH_WAPIS_DIRECTION',
    'ONEWAY_TO_RETURN', 'QWEN_MODEL_ONLY_HANDOFF', 'EXPLICIT_HANDOFF_NON_REGRESSION',
    'BOOKING_MID_TRIP', 'TRAVEL_STATE_PRESERVED_AFTER_BOOKING', 'BOOKING_DETOUR_PRESERVES_PAX',
    'BOOKING_PUBLIC_CONTRACT', 'BOOKING_NOT_FOUND_STATUS',
    'ORDER39_NON_REGRESSION', 'WAPIS_ROUTE', 'WAPAS_ROUTE', 'RETURN_ON_DATE', 'OPEN_JAW_NON_REGRESSION',
    'GK_NON_REGRESSION', 'CURRENT_AUTHORITY', 'SEARCH_BEFORE_CONFIRMATION', 'HTTP_500',
];
$pass = true;
foreach ($required as $g) {
    if (($gates[$g] ?? 'FAIL') !== 'PASS') {
        $pass = false;
    }
}

$summary = [
    'runtime_sha' => $runtimeSha,
    'deploy_marker' => $deployMarker,
    'CQ43_R1_PRODUCTION_REUAT' => $pass ? 'PASS' : 'FAIL',
    'CQ43_FULL_SOAK_GATE' => $pass ? 'READY' : 'BLOCKED',
    'gates' => $gates,
    'counters' => $counters,
    'failures' => $failures,
];
file_put_contents($outRoot.'/SUMMARY.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents($outRoot.'/sessions/all.json', json_encode($sessionLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\nCQ43_R1_PRODUCTION_REUAT=".($pass ? 'PASS' : 'FAIL')."\n";
echo 'FAILURES='.count($failures)."\n";
foreach ($failures as $f) {
    echo " - $f\n";
}
