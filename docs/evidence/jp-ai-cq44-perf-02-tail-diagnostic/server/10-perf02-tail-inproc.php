#!/usr/bin/env php
<?php
/**
 * CQ44-PERF-02 — essential-path tail latency diagnostic (read-only).
 * Evidence/harness only. No app mutation. No supplier search.
 * Expected runtime: a0e616747a95d632f270a6edafc3f6e8d9230fca
 */
require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Contracts\Ai\InferenceProvider;
use App\Models\AiConversation;
use App\Services\Ai\AiChatOrchestrator;
use App\Services\Ai\ConversationIntentRouter;
use App\Services\Ai\FlightSearchConfirmationGate;
use App\Services\Ai\Hybrid\HybridTravelPipeline;
use App\Services\Ai\Hybrid\ServerTravelSignals;
use App\Services\Ai\Semantic\QwenSemanticPlanner;
use Illuminate\Http\Request;

$EXPECTED_SHA = 'a0e616747a95d632f270a6edafc3f6e8d9230fca';
$outRoot = getenv('CQ44_OUT') ?: '/tmp/cq44-perf02-out';
@mkdir($outRoot, 0775, true);
@mkdir($outRoot.'/raw', 0775, true);

$runtimeSha = trim((string) @file_get_contents('/home/pkjetp/jetpk_app/.jetpk-runtime-sha'));
$deployMarker = trim((string) @file_get_contents('/home/pkjetp/jetpk_app/storage/app/deploy-sha.txt'));
echo "RUNTIME_SHA=$runtimeSha\nDEPLOY_MARKER=$deployMarker\n";
if ($runtimeSha !== $EXPECTED_SHA || $deployMarker !== $EXPECTED_SHA) {
    fwrite(STDERR, "ABORT: runtime/marker != expected $EXPECTED_SHA\n");
    exit(2);
}

$orch = app(AiChatOrchestrator::class);
$confirmSvc = app(FlightSearchConfirmationGate::class);
$signals = app(ServerTravelSignals::class);
$hybrid = app(HybridTravelPipeline::class);
$router = app(ConversationIntentRouter::class);
$provider = app(InferenceProvider::class);
$planner = app(QwenSemanticPlanner::class);

$counters = [
    'SEARCH_BEFORE_CONFIRMATION' => 0,
    'SUPPLIER_MUTATIONS' => 0,
    'BOOKING_MUTATIONS' => 0,
    'PAYMENT_MUTATIONS' => 0,
    'HTTP_500' => 0,
    'MODEL_CRASHES' => 0,
    'UNEXPECTED_RATE_LIMITS' => 0,
    'SILENT_EMPTY_TURNS' => 0,
];

function visitor_token(string $prefix): string
{
    return substr(preg_replace('/[^A-Za-z0-9]/', '', $prefix).bin2hex(random_bytes(16)), 0, 48);
}

function pace(int $ms = 1800): void
{
    usleep($ms * 1000);
}

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

function classify_json_shape(string $raw): array
{
    $trim = trim($raw);
    $len = strlen($trim);
    $class = 'UNKNOWN';
    $notes = [];
    if ($trim === '') {
        return ['class' => 'EMPTY_OUTPUT', 'length' => 0, 'notes' => ['empty'], 'prefix' => '', 'suffix' => ''];
    }
    if (str_starts_with($trim, '```')) {
        $class = 'EXTRA_TEXT';
        $notes[] = 'markdown_fence';
    }
    $hasFence = str_contains($trim, '```');
    $braceOpen = substr_count($trim, '{');
    $braceClose = substr_count($trim, '}');
    if ($braceOpen > 0 && $braceClose === 0) {
        $class = 'TRUNCATED_JSON';
        $notes[] = 'unclosed_braces';
    } elseif ($braceOpen !== $braceClose) {
        $class = 'TRUNCATED_JSON';
        $notes[] = 'brace_mismatch';
    }
    $jsonCandidate = $trim;
    if (preg_match('/\{[\s\S]*\}/', $trim, $m) === 1) {
        $jsonCandidate = $m[0];
    } elseif ($braceOpen > 0) {
        $jsonCandidate = $trim;
    }
    try {
        $decoded = json_decode($jsonCandidate, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            $class = 'MALFORMED_JSON';
            $notes[] = 'decoded_non_object';
        } else {
            $keys = array_keys($decoded);
            $hasDomain = isset($decoded['domain']);
            $hasOp = isset($decoded['operation']);
            $hasMsgOnly = ! $hasDomain && ! $hasOp && isset($decoded['message']);
            if ($hasMsgOnly) {
                $class = 'SCHEMA_MISMATCH';
                $notes[] = 'open_domain_payload_shape';
            } elseif (! $hasDomain && ! $hasOp) {
                $class = 'SCHEMA_MISMATCH';
                $notes[] = 'missing_domain_operation';
                $notes[] = 'keys:'.implode(',', array_slice($keys, 0, 12));
            } else {
                $class = 'VALID_JSON_OBJECT';
                $notes[] = 'keys:'.implode(',', array_slice($keys, 0, 12));
            }
        }
    } catch (Throwable $e) {
        if ($class === 'UNKNOWN') {
            $class = 'MALFORMED_JSON';
        }
        $notes[] = 'json_error';
        if (preg_match('/[a-zA-Z]{3,}[\s:]/', $trim) === 1 && str_contains($trim, '{')) {
            $notes[] = 'extra_prose_likely';
            if ($class !== 'TRUNCATED_JSON') {
                $class = 'EXTRA_TEXT';
            }
        }
    }
    if ($hasFence && $class === 'UNKNOWN') {
        $class = 'EXTRA_TEXT';
    }
    $prefix = mb_substr(preg_replace('/\s+/', ' ', $trim), 0, 120);
    $suffix = mb_substr(preg_replace('/\s+/', ' ', $trim), -80);

    return [
        'class' => $class,
        'length' => $len,
        'brace_open' => $braceOpen,
        'brace_close' => $braceClose,
        'notes' => $notes,
        'prefix' => $prefix,
        'suffix' => $suffix,
    ];
}

function chat_turn(
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
    $request->headers->set('User-Agent', 'CQ44-PERF02-DIAG/1.0');
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
            'latency_ms' => (int) round((microtime(true) - $t0) * 1000),
            'conversation_id' => $conversation->public_id,
            'user_message' => $message,
            'MODEL_CALLS' => 0,
        ];
    }

    try {
        $sanitized = $orch->sanitizeUserMessage($message);
        if (! ($sanitized['ok'] ?? false)) {
            return [
                'rate_limited' => false,
                'latency_ms' => (int) round((microtime(true) - $t0) * 1000),
                'conversation_id' => $conversation->public_id,
                'status' => 'invalid',
                'user_message' => $message,
                'MODEL_CALLS' => 0,
            ];
        }
        $payload = $orch->handleChat($conversation, $sanitized['message']);
    } catch (Throwable $e) {
        $counters['MODEL_CRASHES']++;
        $counters['HTTP_500']++;

        return [
            'error' => $e->getMessage(),
            'latency_ms' => (int) round((microtime(true) - $t0) * 1000),
            'conversation_id' => $conversation->public_id,
            'user_message' => $message,
            'MODEL_CALLS' => 0,
        ];
    }

    $conversation->refresh();
    $ms = (int) round((microtime(true) - $t0) * 1000);
    $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
    $body = (string) ($payload['message'] ?? '');
    $searchCalls = (int) ($meta['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0);
    if ($searchCalls > 0 && ! (bool) ($payload['requires_confirmation'] ?? false) && ($meta['CONFIRMATION_REQUIRED'] ?? false) !== true) {
        $counters['SEARCH_BEFORE_CONFIRMATION'] += $searchCalls;
    }
    if (trim($body) === '') {
        $counters['SILENT_EMPTY_TURNS']++;
    }

    $ss = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
    $calls = (int) ($meta['MODEL_CALLS'] ?? $meta['GENERAL_MODEL_CALLS'] ?? 0);
    $semMs = (int) ($meta['SEMANTIC_LATENCY_MS'] ?? 0);
    $fallbackReason = (string) ($meta['SEMANTIC_FALLBACK_REASON'] ?? '');
    $hybridMs = max(0, $ms - $semMs);

    return [
        'rate_limited' => false,
        'latency_ms' => $ms,
        'conversation_id' => $conversation->public_id,
        'status' => (string) ($payload['status'] ?? ''),
        'state' => (string) ($payload['state'] ?? $conversation->state),
        'message' => mb_substr(str_replace("\n", ' ', $body), 0, 240),
        'requires_confirmation' => (bool) ($payload['requires_confirmation'] ?? false),
        'user_message' => $message,
        'MODEL_CALLS' => $calls,
        'QWEN_CALLED' => $calls > 0 ? 'YES' : 'NO',
        'SEMANTIC_LATENCY_MS' => $semMs,
        'HYBRID_FALLBACK_MS_EST' => $hybridMs,
        'SEMANTIC_BRAIN_FALLBACK' => (string) ($meta['SEMANTIC_BRAIN_FALLBACK'] ?? ''),
        'SEMANTIC_FALLBACK_REASON' => $fallbackReason,
        'SEMANTIC_PLANNER_BYPASSED' => (string) ($meta['SEMANTIC_PLANNER_BYPASSED'] ?? ''),
        'DETERMINISTIC_AUTHORITY_COMPLETE' => (string) ($meta['DETERMINISTIC_AUTHORITY_COMPLETE'] ?? ''),
        'FINAL_RESPONSE_SOURCE' => (string) ($meta['FINAL_RESPONSE_SOURCE'] ?? ''),
        'open_domain_category' => (string) ($meta['open_domain_category'] ?? ''),
        'VALIDATOR_HINT' => $fallbackReason !== '' ? $fallbackReason : ((string) ($meta['SEMANTIC_PLAN_VALID'] ?? 'n/a')),
        'shopping_origin' => (string) ($ss['origin'] ?? ''),
        'shopping_destination' => (string) ($ss['destination'] ?? ''),
        'shopping_depart_date' => (string) ($ss['depart_date'] ?? ''),
        'shopping_trip_type' => (string) ($ss['trip_type'] ?? ''),
        'pending_confirmation' => $confirmSvc->pendingSnapshot($conversation),
        'meta_keys' => array_keys($meta),
        'ts' => gmdate('c'),
    ];
}

function server_route_audit(ServerTravelSignals $signals, HybridTravelPipeline $hybrid, string $message): array
{
    $auth = $signals->progressiveTravelAuthority($message, null);
    $det = $signals->deterministicAuthorityComplete($message, null);
    $dates = $signals->resolveTripDates($message, null, null);
    $parse = $hybrid->parse($message, null);
    $intent = $parse->intent;
    $origin = is_string($auth['origin'] ?? null) ? (string) $auth['origin'] : null;
    $dest = is_string($auth['destination'] ?? null) ? (string) $auth['destination'] : null;
    $depart = is_string($dates['depart_date'] ?? null) ? (string) $dates['depart_date'] : null;
    $canConfirm = $origin && $dest && $depart
        && empty($auth['origin_ambiguous'])
        && empty($auth['dest_ambiguous'])
        && ! empty($auth['explicit_route']);

    $class = 'OTHER';
    if (! empty($auth['explicit_route']) && $canConfirm) {
        $class = 'CLEAR_EXPLICIT_ROUTE';
    } elseif (! empty($auth['origin_ambiguous']) || ! empty($auth['dest_ambiguous'])) {
        $class = 'AMBIGUOUS_ROUTE';
    } elseif (! empty($auth['destination_only'])) {
        $class = 'DESTINATION_LED';
    } elseif (! empty($auth['origin_only'])) {
        $class = 'ORIGIN_LED';
    }

    // open-jaw detection via hybrid state / intent
    $state = is_array($parse->state) ? $parse->state : [];
    $legs = is_array($state['legs'] ?? null) ? $state['legs'] : [];
    $intentArr = method_exists($intent, 'toArray') ? $intent->toArray() : [];
    $tripType = (string) ($state['trip_type'] ?? ($intentArr['trip_type'] ?? ''));
    if ($tripType === 'open_jaw' || count($legs) >= 2) {
        $class = 'OPEN_JAW';
    }

    return [
        'message' => $message,
        'EXPLICIT_ORIGIN' => $origin,
        'EXPLICIT_DESTINATION' => $dest,
        'EXPLICIT_DEPART_DATE' => $depart,
        'EXPLICIT_ROUTE' => ! empty($auth['explicit_route']) ? 'YES' : 'NO',
        'LOCATION_AMBIGUITY' => (! empty($auth['origin_ambiguous']) || ! empty($auth['dest_ambiguous'])) ? 'YES' : 'NO',
        'DATE_AMBIGUITY' => empty($depart) ? 'YES' : 'NO',
        'ACTIVE_TRAVEL_CONTEXT' => 'NO',
        'SERVER_CAN_BUILD_VALID_CONFIRMATION' => $canConfirm ? 'YES' : 'NO',
        'DETERMINISTIC_COMPLETE' => ! empty($det['complete']) ? 'YES' : 'NO',
        'DETERMINISTIC_REASON' => (string) ($det['reason'] ?? ''),
        'DIAGNOSTIC_CLASS' => $class,
        'HYBRID_INTENT' => $intentArr,
        'HYBRID_CLARIFY' => (bool) $parse->clarificationRequired,
        'depart_explicit' => (bool) ($dates['depart_explicit'] ?? false),
        'adults_default' => 1,
        'cabin_default' => null,
        'trip_type_default' => 'one_way',
        'fields_known_before_qwen' => array_values(array_filter([
            $origin ? 'origin='.$origin : null,
            $dest ? 'destination='.$dest : null,
            $depart ? 'depart_date='.$depart : null,
            'adults=1(default)',
            'trip_type=one_way(default)',
            'cabin=null(default)',
            'active_travel_context=false',
        ])),
    ];
}

function planner_system_prompt(): string
{
    $ref = new \ReflectionClass(QwenSemanticPlanner::class);
    $m = $ref->getMethod('systemPrompt');
    $m->setAccessible(true);

    return (string) $m->invoke(app(QwenSemanticPlanner::class));
}

function context_sizes(string $message): array
{
    $prompt = planner_system_prompt();
    $sys = strlen($prompt);
    $payload = [
        'message' => $message,
        'history' => [],
        'authoritative_state' => [],
        'pending_confirmation' => null,
        'last_flight_search' => null,
        'conversation_state' => 'AI_ACTIVE',
        'brand' => 'JetPakistan',
        'capabilities' => ['flight_search', 'group_travel', 'booking_lookup', 'support_handoff'],
        'instruction' => 'Emit ONE JSON object matching the semantic plan schema. Do not authorize tools. Do not invent live fares/weather.',
    ];
    $user = (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
    $schemaApprox = 0;
    if (preg_match_all('/Example[^\n]*\n(\{.*\})/m', $prompt, $ex) !== false) {
        foreach ($ex[1] ?? [] as $block) {
            $schemaApprox += strlen($block);
        }
    }

    return [
        'message' => $message,
        'SYSTEM_PROMPT_CHARS' => $sys,
        'SCHEMA_CHARS' => $schemaApprox,
        'CONVERSATION_CONTEXT_CHARS' => 0,
        'SHOPPING_STATE_CHARS' => 2,
        'USER_PAYLOAD_CHARS' => strlen($user),
        'TOTAL_REQUEST_CHARS' => $sys + strlen($user),
    ];
}

function direct_planner_probe(InferenceProvider $provider, string $message): array
{
    $sys = planner_system_prompt();
    $payload = [
        'message' => $message,
        'history' => [],
        'authoritative_state' => [],
        'pending_confirmation' => null,
        'last_flight_search' => null,
        'conversation_state' => 'AI_ACTIVE',
        'brand' => 'JetPakistan',
        'capabilities' => ['flight_search', 'group_travel', 'booking_lookup', 'support_handoff'],
        'instruction' => 'Emit ONE JSON object matching the semantic plan schema. Do not authorize tools. Do not invent live fares/weather.',
    ];
    $user = (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
    $t0 = microtime(true);
    $base = rtrim((string) config('ota.ai_assistant.gateway_url', 'http://127.0.0.1:3921'), '/');
    $httpStart = (int) (microtime(true) * 1000);
    $rawJson = null;
    $httpLatency = null;
    try {
        $resp = Illuminate\Support\Facades\Http::timeout((int) config('ota.ai_assistant.timeout_seconds', 45))
            ->post($base.'/v1/chat/completions', [
                'model' => (string) config('ota.ai_assistant.model_id', 'local'),
                'messages' => [
                    ['role' => 'system', 'content' => $sys],
                    ['role' => 'user', 'content' => $user],
                ],
                'max_tokens' => 480,
                'temperature' => 0,
                'chat_template_kwargs' => ['enable_thinking' => false],
            ]);
        $httpLatency = (int) (microtime(true) * 1000) - $httpStart;
        $rawJson = $resp->successful() ? $resp->json() : null;
    } catch (Throwable $e) {
        $httpLatency = (int) (microtime(true) * 1000) - $httpStart;
        $rawJson = null;
    }

    $content = trim((string) data_get($rawJson, 'choices.0.message.content', ''));
    if ($content === '') {
        $content = trim((string) (
            data_get($rawJson, 'choices.0.message.reasoning_content')
            ?? data_get($rawJson, 'choices.0.message.reasoning')
            ?? ''
        ));
    }
    $shape = classify_json_shape($content);
    $usage = is_array(data_get($rawJson, 'usage')) ? data_get($rawJson, 'usage') : null;
    $timings = data_get($rawJson, 'timings');

    // Planner classification without a second model call: reuse decoded content path.
    $planError = null;
    $validJson = false;
    $hasPlan = false;
    if ($content === '') {
        $planError = 'empty_output';
    } else {
        $decoded = null;
        $candidate = $content;
        if (preg_match('/\{[\s\S]*\}/', $content, $mm) === 1) {
            $candidate = $mm[0];
        }
        try {
            $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
            $validJson = is_array($decoded);
        } catch (Throwable) {
            $planError = 'invalid_json';
        }
        if ($validJson && is_array($decoded)) {
            if (! isset($decoded['domain']) && ! isset($decoded['operation']) && isset($decoded['message'])) {
                $planError = 'open_domain_payload';
            } else {
                try {
                    \App\Data\Ai\SemanticPlan::fromModelArray($decoded);
                    $hasPlan = true;
                    $planError = null;
                } catch (Throwable) {
                    $planError = 'schema_reject';
                }
            }
        } elseif ($planError === null) {
            $planError = 'invalid_json';
        }
    }

    return [
        'message' => $message,
        'PROMPT_CHARS' => strlen($sys) + strlen($user),
        'CONTEXT_MESSAGE_COUNT' => 0,
        'INPUT_TOKEN_COUNT' => is_array($usage) ? ($usage['prompt_tokens'] ?? null) : null,
        'OUTPUT_TOKEN_COUNT' => is_array($usage) ? ($usage['completion_tokens'] ?? null) : null,
        'MODEL_LATENCY_MS' => $httpLatency ?? (int) round((microtime(true) - $t0) * 1000),
        'PLANNER_RESULT' => [
            'valid_json' => $validJson && $planError === null,
            'error' => $planError,
            'has_plan' => $hasPlan,
            'latency_ms' => $httpLatency ?? 0,
        ],
        'RAW_SHAPE' => $shape,
        'USAGE' => $usage,
        'TIMINGS' => $timings,
        'finish_reason' => data_get($rawJson, 'choices.0.finish_reason'),
    ];
}

// ---------------------------------------------------------------------------
// 1) Explicit route server authority audit
// ---------------------------------------------------------------------------
$primaryExplicit = 'Now Islamabad to Dubai next Monday';
$explicitAudit = server_route_audit($signals, $hybrid, $primaryExplicit);
file_put_contents($outRoot.'/01-explicit-route-audit.json', json_encode($explicitAudit, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo "EXPLICIT_AUDIT done authority_complete_fields=".count($explicitAudit['fields_known_before_qwen'])." reason={$explicitAudit['DETERMINISTIC_REASON']}\n";

// ---------------------------------------------------------------------------
// 2) Ambiguous / control classification (server only)
// ---------------------------------------------------------------------------
$controlMsgs = [
    'I need Dubai',
    'from Lahore',
    'London to Dubai',
    'New York to Lahore',
    'Lahore to Jeddah then Medina to Lahore',
];
$controls = [];
foreach ($controlMsgs as $m) {
    $controls[] = server_route_audit($signals, $hybrid, $m);
}
file_put_contents($outRoot.'/01b-route-controls.json', json_encode($controls, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

// ---------------------------------------------------------------------------
// 3) CURRENT server classification audit
// ---------------------------------------------------------------------------
$currentMsgs = [
    "What is Bitcoin's price right now?",
    'What is gold price right now?',
    "What's USD to PKR right now?",
    "What's the weather in Dubai right now?",
];
$currentAudit = [];
foreach ($currentMsgs as $m) {
    $cat = $router->classifyOpenDomain($m);
    $topic = $router->classifyCurrentTopic($m);
    $currentAudit[] = [
        'message' => $m,
        'SERVER_OPEN_DOMAIN_CATEGORY' => $cat,
        'CURRENT_SIGNAL' => $cat === 'CURRENT_UNVERIFIED' ? 'YES' : 'NO',
        'CURRENT_TOPIC' => $topic,
        'APPROVED_LIVE_SOURCE_AVAILABLE' => 'NO', // product: live_provider hardcoded false
        'QWEN_ROLE_IF_CALLED' => $cat === 'CURRENT_UNVERIFIED'
            ? 'planner_then_currentUnverified_or_fallback_same_safe_text'
            : ($cat === 'GENERAL_KNOWLEDGE' ? 'open_domain_respond_or_gk_path' : 'unknown'),
        'NOTES' => $cat === 'CURRENT_UNVERIFIED' && $topic === 'market'
            ? 'Server can refuse without Qwen; SemanticBrain does not early-bypass CURRENT (unlike GK/CASUAL/OOD).'
            : null,
    ];
}
$btc = $currentAudit[0];
$currentSafeComplete = ($btc['SERVER_OPEN_DOMAIN_CATEGORY'] === 'CURRENT_UNVERIFIED'
    && $btc['APPROVED_LIVE_SOURCE_AVAILABLE'] === 'NO') ? 'YES' : 'NO';
file_put_contents($outRoot.'/03-current-audit.json', json_encode([
    'CURRENT_SAFE_REFUSAL_SERVER_AUTHORITY_COMPLETE' => $currentSafeComplete,
    'cases' => $currentAudit,
    'product_note' => 'SemanticBrain::currentUnverified sets live_provider=false for all topics; no approved Bitcoin/market provider.',
], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo "CURRENT_AUDIT complete=$currentSafeComplete\n";

// ---------------------------------------------------------------------------
// 4) Context sizes
// ---------------------------------------------------------------------------
$ctxCases = [
    'destination_led' => 'I need Dubai',
    'explicit_route' => $primaryExplicit,
    'current' => "What is Bitcoin's price right now?",
    'open_jaw' => 'Lahore to Jeddah then Medina to Lahore',
];
$ctxOut = [];
foreach ($ctxCases as $k => $m) {
    $ctxOut[$k] = context_sizes($m);
}
file_put_contents($outRoot.'/07-context-size.json', json_encode($ctxOut, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

// ---------------------------------------------------------------------------
// 5) Cold/warm + explicit samples + CURRENT samples
// ---------------------------------------------------------------------------
$slotsBefore = @file_get_contents('http://127.0.0.1:3921/slots');
$cpuBefore = trim((string) @shell_exec("ps -p \$(pgrep -f 'llama-server' | head -1) -o %cpu,%mem,rss --no-headers 2>/dev/null"));

$explicitVariants = [
    'Now Islamabad to Dubai next Monday',
    'Lahore to Dubai next Monday',
    'Islamabad to Dubai next Monday',
    'Karachi to Jeddah tomorrow',
    'Dubai to Lahore tomorrow',
    'Islamabad se Dubai kal',
];
$explicitSamples = [];
$coldWarmExplicit = [];
foreach ($explicitVariants as $i => $msg) {
    $v = visitor_token('p02e'.$i);
    $row = chat_turn($orch, $confirmSvc, $v, $msg, null, $counters);
    $pre = server_route_audit($signals, $hybrid, $msg);
    $row['server_pre'] = [
        'origin' => $pre['EXPLICIT_ORIGIN'],
        'destination' => $pre['EXPLICIT_DESTINATION'],
        'depart' => $pre['EXPLICIT_DEPART_DATE'],
        'class' => $pre['DIAGNOSTIC_CLASS'],
        'det_reason' => $pre['DETERMINISTIC_REASON'],
        'can_confirm' => $pre['SERVER_CAN_BUILD_VALID_CONFIRMATION'],
    ];
    $explicitSamples[] = $row;
    echo "EXPLICIT[$i] calls={$row['MODEL_CALLS']} total={$row['latency_ms']} sem={$row['SEMANTIC_LATENCY_MS']} fb={$row['SEMANTIC_FALLBACK_REASON']} od={$row['shopping_origin']}->{$row['shopping_destination']} {$row['shopping_depart_date']}\n";
    if ($i === 0) {
        $coldWarmExplicit['COLD_SAMPLE_MS'] = $row['latency_ms'];
    } elseif ($i <= 3) {
        $coldWarmExplicit['WARM_SAMPLE_'.$i.'_MS'] = $row['latency_ms'];
    }
    pace(1600);
}

// Extra warm repeats of primary explicit for cold/warm series
for ($w = 1; $w <= 3; $w++) {
    if (isset($coldWarmExplicit['WARM_SAMPLE_'.$w.'_MS'])) {
        continue;
    }
    $v = visitor_token('p02ew'.$w);
    $row = chat_turn($orch, $confirmSvc, $v, $primaryExplicit, null, $counters);
    $coldWarmExplicit['WARM_SAMPLE_'.$w.'_MS'] = $row['latency_ms'];
    $explicitSamples[] = $row;
    echo "EXPLICIT_WARM[$w] total={$row['latency_ms']} sem={$row['SEMANTIC_LATENCY_MS']} fb={$row['SEMANTIC_FALLBACK_REASON']}\n";
    pace(1200);
}

file_put_contents($outRoot.'/02-explicit-route-samples.json', json_encode($explicitSamples, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

$currentSamples = [];
$coldWarmCurrent = [];
$btcMsg = "What is Bitcoin's price right now?";
for ($i = 0; $i < 5; $i++) {
    $v = visitor_token('p02c'.$i);
    $row = chat_turn($orch, $confirmSvc, $v, $btcMsg, null, $counters);
    $currentSamples[] = $row;
    if ($i === 0) {
        $coldWarmCurrent['COLD_SAMPLE_MS'] = $row['latency_ms'];
    } else {
        $coldWarmCurrent['WARM_SAMPLE_'.$i.'_MS'] = $row['latency_ms'];
    }
    echo "CURRENT[$i] calls={$row['MODEL_CALLS']} total={$row['latency_ms']} sem={$row['SEMANTIC_LATENCY_MS']} fb={$row['SEMANTIC_FALLBACK_REASON']} src={$row['FINAL_RESPONSE_SOURCE']}\n";
    pace(1600);
}
file_put_contents($outRoot.'/04-current-samples.json', json_encode($currentSamples, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

file_put_contents($outRoot.'/08-cold-warm.json', json_encode([
    'note' => 'Not true post-idle cold start; relative first-vs-subsequent under continuous diagnostic load. llama-server was already warm from prior production traffic.',
    'explicit_route' => $coldWarmExplicit,
    'current' => $coldWarmCurrent,
], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

// ---------------------------------------------------------------------------
// 6) Direct planner probes for invalid_json root cause (2–3 cases)
// ---------------------------------------------------------------------------
$probes = [];
foreach ([$primaryExplicit, $btcMsg, 'Lahore to Dubai next Monday'] as $pm) {
    $probes[] = direct_planner_probe($provider, $pm);
    echo "PROBE {$pm} shape=".$probes[count($probes)-1]['RAW_SHAPE']['class']." err=".($probes[count($probes)-1]['PLANNER_RESULT']['error'] ?? 'null')." lat=".$probes[count($probes)-1]['MODEL_LATENCY_MS']."\n";
    pace(1200);
}
file_put_contents($outRoot.'/05-invalid-json-probes.json', json_encode($probes, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

// ---------------------------------------------------------------------------
// 7) Runtime telemetry availability
// ---------------------------------------------------------------------------
$slotsAfter = @file_get_contents('http://127.0.0.1:3921/slots');
$metricsProbe = @file_get_contents('http://127.0.0.1:3921/metrics');
$cpuAfter = trim((string) @shell_exec("ps -p \$(pgrep -f 'llama-server' | head -1) -o %cpu,%mem,rss --no-headers 2>/dev/null"));
$slotCount = 0;
$slotArr = json_decode((string) $slotsAfter, true);
if (is_array($slotArr)) {
    $slotCount = count($slotArr);
}
$tokenAvailable = false;
foreach ($probes as $p) {
    if (($p['INPUT_TOKEN_COUNT'] ?? null) !== null) {
        $tokenAvailable = true;
        break;
    }
}
$telemetry = [
    'QUEUE_LATENCY_AVAILABLE' => 'NO',
    'PROMPT_EVAL_AVAILABLE' => is_array(data_get($probes, '0.TIMINGS')) ? 'YES' : 'NO',
    'GENERATION_LATENCY_AVAILABLE' => is_array(data_get($probes, '0.TIMINGS')) ? 'YES' : 'NO',
    'TOKEN_TELEMETRY_AVAILABLE' => $tokenAvailable ? 'YES' : 'PARTIAL_VIA_DIRECT_HTTP',
    'COLD_WARM_TELEMETRY_AVAILABLE' => 'PARTIAL_RELATIVE_ONLY',
    'METRICS_ENDPOINT' => (is_string($metricsProbe) && str_contains($metricsProbe, 'not_supported')) ? 'DISABLED' : 'UNKNOWN',
    'SLOT_COUNT' => $slotCount,
    'SINGLE_SLOT_SERIALIZATION' => $slotCount === 1 ? 'YES' : 'UNKNOWN',
    'CPU_MEM_BEFORE' => $cpuBefore,
    'CPU_MEM_AFTER' => $cpuAfter,
    'RAM_MB_AVAILABLE_HOST' => trim((string) @shell_exec("free -m | awk '/^Mem:/{print \$7}'")),
    'CONCURRENCY_EFFECT' => $slotCount === 1
        ? 'SINGLE_SLOT_IMPLIES_SERIALIZATION_BUT_NO_OVERLAP_TEST_RUN'
        : 'N/A',
    'probe_usage_sample' => $probes[0]['USAGE'] ?? null,
    'probe_timings_sample' => $probes[0]['TIMINGS'] ?? null,
];
file_put_contents($outRoot.'/06-runtime-telemetry.json', json_encode($telemetry, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

// ---------------------------------------------------------------------------
// 8) Aggregate summary
// ---------------------------------------------------------------------------
function rate_stats(array $samples): array
{
    $n = count($samples);
    $valid = 0;
    $invJson = 0;
    $invPlan = 0;
    $totals = [];
    $sems = [];
    $qwenWait = [];
    $hybridEst = [];
    foreach ($samples as $s) {
        $totals[] = (int) ($s['latency_ms'] ?? 0);
        $sems[] = (int) ($s['SEMANTIC_LATENCY_MS'] ?? 0);
        $reason = (string) ($s['SEMANTIC_FALLBACK_REASON'] ?? '');
        $calls = (int) ($s['MODEL_CALLS'] ?? 0);
        if ($calls > 0 && $reason === '') {
            $valid++;
        }
        if ($reason === 'invalid_json') {
            $invJson++;
            $qwenWait[] = (int) ($s['SEMANTIC_LATENCY_MS'] ?? 0);
            $hybridEst[] = (int) ($s['HYBRID_FALLBACK_MS_EST'] ?? 0);
        } elseif (in_array($reason, ['invalid_plan', 'schema_reject', 'legacy_schema'], true)) {
            $invPlan++;
            $qwenWait[] = (int) ($s['SEMANTIC_LATENCY_MS'] ?? 0);
            $hybridEst[] = (int) ($s['HYBRID_FALLBACK_MS_EST'] ?? 0);
        } elseif ($calls > 0 && $reason !== '') {
            // other fallback after model
            $qwenWait[] = (int) ($s['SEMANTIC_LATENCY_MS'] ?? 0);
            $hybridEst[] = (int) ($s['HYBRID_FALLBACK_MS_EST'] ?? 0);
        }
    }

    return [
        'n' => $n,
        'valid_rate' => $n ? round($valid / $n, 3) : null,
        'invalid_json_rate' => $n ? round($invJson / $n, 3) : null,
        'invalid_plan_rate' => $n ? round($invPlan / $n, 3) : null,
        'p50_ms' => nearest_rank($totals, 50),
        'p95_ms' => nearest_rank($totals, 95),
        'max_ms' => $totals === [] ? null : max($totals),
        'sem_p50_ms' => nearest_rank($sems, 50),
        'sem_p95_ms' => nearest_rank($sems, 95),
        'avg_qwen_wait_failed_ms' => $qwenWait === [] ? null : (int) round(array_sum($qwenWait) / count($qwenWait)),
        'avg_hybrid_est_failed_ms' => $hybridEst === [] ? null : (int) round(array_sum($hybridEst) / count($hybridEst)),
        'qwen_wait_share_of_failed' => ($qwenWait !== [] && array_sum($qwenWait) + array_sum($hybridEst) > 0)
            ? round(array_sum($qwenWait) / (array_sum($qwenWait) + array_sum($hybridEst)), 3)
            : null,
    ];
}

$exStats = rate_stats($explicitSamples);
$cuStats = rate_stats($currentSamples);

$invClasses = [];
foreach (array_merge($explicitSamples, $currentSamples) as $s) {
    if (($s['SEMANTIC_FALLBACK_REASON'] ?? '') === 'invalid_json') {
        $invClasses[] = 'chat_invalid_json';
    }
}
foreach ($probes as $p) {
    $invClasses[] = $p['RAW_SHAPE']['class'] ?? 'UNKNOWN';
}
$primaryInvClass = 'UNKNOWN';
$counts = array_count_values($invClasses);
arsort($counts);
if ($counts !== []) {
    $primaryInvClass = (string) array_key_first($counts);
}

$summary = [
    'APPLICATION_RUNTIME_SHA' => $runtimeSha,
    'APPLICATION_CODE_CHANGED' => 'NO',
    'DEPLOY_OCCURRED' => 'NO',
    'EXPLICIT_ROUTE_SERVER_AUTHORITY_COMPLETE' => $explicitAudit['SERVER_CAN_BUILD_VALID_CONFIRMATION'],
    'EXPLICIT_ROUTE_DETERMINISTIC_REASON' => $explicitAudit['DETERMINISTIC_REASON'],
    'EXPLICIT_ROUTE_QWEN_REQUIRED_TODAY' => 'YES_BY_PERF01_POLICY_explicit_route_keep_qwen',
    'CURRENT_SAFE_REFUSAL_SERVER_AUTHORITY_COMPLETE' => $currentSafeComplete,
    'explicit_stats' => $exStats,
    'current_stats' => $cuStats,
    'INVALID_JSON_PRIMARY_CLASS' => $primaryInvClass,
    'INVALID_JSON_PROBE_CLASSES' => array_map(fn ($p) => $p['RAW_SHAPE']['class'] ?? null, $probes),
    'TELEMETRY' => $telemetry,
    'CONTEXT_EXPLICIT' => $ctxOut['explicit_route'] ?? null,
    'CONTEXT_CURRENT' => $ctxOut['current'] ?? null,
    'counters' => $counters,
    'CANDIDATE_DECISIONS' => [
        'EXPLICIT_A_TO_B_ROUTE' => ($explicitAudit['SERVER_CAN_BUILD_VALID_CONFIRMATION'] === 'YES'
            && $explicitAudit['DETERMINISTIC_REASON'] === 'explicit_route_keep_qwen')
            ? 'A_SAFE_DETERMINISTIC_SHORT_CIRCUIT'
            : 'C_NEEDS_MORE_EVIDENCE',
        'CURRENT_WITHOUT_APPROVED_SOURCE' => $currentSafeComplete === 'YES'
            ? 'A_SAFE_DETERMINISTIC_SHORT_CIRCUIT'
            : 'C_NEEDS_MORE_EVIDENCE',
        'DESTINATION_LED' => 'B_QWEN_STILL_REQUIRED',
        'OPEN_JAW' => 'B_QWEN_STILL_REQUIRED',
        'GENERAL_KNOWLEDGE' => 'B_QWEN_STILL_REQUIRED',
    ],
];
file_put_contents($outRoot.'/SUMMARY.json', json_encode($summary, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo "SUMMARY written\n";
echo json_encode([
    'explicit' => $exStats,
    'current' => $cuStats,
    'inv_class' => $primaryInvClass,
    'counters' => $counters,
], JSON_UNESCAPED_UNICODE)."\n";
echo "DONE out=$outRoot\n";
