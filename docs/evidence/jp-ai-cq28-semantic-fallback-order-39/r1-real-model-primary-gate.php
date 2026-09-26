<?php

/**
 * JP-AI-CQ28-SEMANTIC-FALLBACK-ORDER-39 — local real-model primary gate.
 *
 * Process-local config only. Does NOT mutate production .env.
 * Requires SSH tunnel: localhost:3921 → prod 127.0.0.1:3921
 *
 *   php docs/evidence/jp-ai-cq28-semantic-fallback-order-39/r1-real-model-primary-gate.php
 */

declare(strict_types=1);

use App\Contracts\Ai\InferenceProvider;
use App\Models\AiConversation;
use App\Services\Ai\AiChatOrchestrator;
use App\Services\Ai\LocalLlamaProvider;
use Illuminate\Support\Str;

require __DIR__.'/../../../vendor/autoload.php';

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';
putenv('DB_CONNECTION=sqlite');
$_ENV['DB_CONNECTION'] = 'sqlite';
putenv('DB_DATABASE=:memory:');
$_ENV['DB_DATABASE'] = ':memory:';
putenv('CACHE_STORE=array');
putenv('QUEUE_CONNECTION=sync');
putenv('SESSION_DRIVER=array');

$app = require __DIR__.'/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$kernel->call('migrate', ['--force' => true]);

$outDir = __DIR__;
@mkdir($outDir, 0777, true);

config([
    'ota.ai_assistant.mode' => 'public',
    'ota.ai_assistant.enabled' => true,
    'ota.ai_assistant.hard_allow.master' => true,
    'ota.ai_assistant.hard_allow.public' => true,
    'ota.ai_assistant.hard_allow.lab_adapter' => false,
    'ota.ai_assistant.hard_allow.human_handoff' => true,
    'ota.ai_assistant.flight_search_enabled' => true,
    'ota.ai_assistant.conversational_enabled' => true,
    'ota.ai_assistant.optional_llm_assist' => false,
    'ota.ai_assistant.semantic_planner_enabled' => true,
    'ota.ai_assistant.semantic_composer_enabled' => false,
    'ota.ai_assistant.knowledge_enabled' => true,
    'ota.ai_assistant.human_handoff_enabled' => true,
    'ota.ai_assistant.anonymous_per_minute' => 600,
    'ota.ai_assistant.gateway_url' => getenv('OTA_AI_GATEWAY_URL') ?: 'http://127.0.0.1:3921',
    'ota.ai_assistant.model_id' => getenv('OTA_AI_MODEL_ID') ?: 'local',
    'ota.ai_assistant.timeout_seconds' => 90,
    'ai_lab.enabled' => false,
]);

\App\Models\AiAssistantSetting::query()->delete();
$app->make(\App\Services\Ai\AiAssistantSettingsService::class)->get();

$app->forgetInstance(InferenceProvider::class);
$app->forgetInstance(\App\Services\Ai\Semantic\QwenSemanticPlanner::class);
$app->forgetInstance(\App\Services\Ai\Semantic\SemanticBrain::class);
$app->forgetInstance(AiChatOrchestrator::class);
$app->singleton(InferenceProvider::class, fn () => new LocalLlamaProvider);

$provider = $app->make(InferenceProvider::class);
$orch = $app->make(AiChatOrchestrator::class);

fwrite(STDOUT, 'PROVIDER='.$provider->name().' healthy='.($provider->isHealthy() ? 'yes' : 'no').PHP_EOL);
if (! $provider->isHealthy()) {
    fwrite(STDERR, "QWEN_RUNTIME_ACTIVE=NO\n");
    exit(2);
}

$primary = 'Lahore to Dubai tomorrow for 2 adults';

/**
 * @return array<string, mixed>
 */
function runTurn(AiChatOrchestrator $orch, string $message, ?AiConversation $conversation = null): array
{
    $t0 = microtime(true);
    if ($conversation === null) {
        $conversation = AiConversation::query()->create([
            'channel' => 'web',
            'visitor_token_hash' => hash('sha256', 'rm39-'.Str::uuid()->toString()),
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => [],
        ]);
    }
    $payload = $orch->handleChat($conversation, $message);
    $ms = (int) round((microtime(true) - $t0) * 1000);
    $msg = mb_strtolower((string) ($payload['message'] ?? ''));
    $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
    $snap = is_array($payload['confirmation_snapshot'] ?? null) ? $payload['confirmation_snapshot'] : [];
    $piiFirst = str_contains($msg, 'may i start with your name')
        || (bool) preg_match('/\b(email|phone|contact number)\b.*\b(please|share|need)\b/u', $msg)
        || str_contains($msg, 'name, email, and phone');

    return [
        'conversation_id' => $conversation->public_id,
        'mode' => (string) ($payload['mode'] ?? ''),
        'status' => (string) ($payload['status'] ?? ''),
        'search_calls' => (int) ($meta['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0),
        'SEMANTIC_BRAIN_CALLED' => $meta['SEMANTIC_BRAIN_CALLED'] ?? null,
        'SEMANTIC_BRAIN_VALID' => $meta['SEMANTIC_BRAIN_VALID'] ?? null,
        'SEMANTIC_BRAIN_FALLBACK' => $meta['SEMANTIC_BRAIN_FALLBACK'] ?? null,
        'SEMANTIC_FALLBACK_REASON' => $meta['SEMANTIC_FALLBACK_REASON'] ?? null,
        'LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK' => $meta['LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK'] ?? null,
        'origin' => $snap['origin'] ?? ($meta['origin'] ?? null),
        'destination' => $snap['destination'] ?? ($meta['destination'] ?? null),
        'adults' => $snap['adults'] ?? ($meta['adults'] ?? null),
        'OPEN_JAW_DETECTED' => $meta['OPEN_JAW_DETECTED'] ?? null,
        'pii_first' => $piiFirst,
        'latency_ms' => $ms,
        'message_preview' => mb_substr((string) ($payload['message'] ?? ''), 0, 160),
    ];
}

$primaryRuns = [];
$latencies = [];
$qwenOk = 0;
$hybridOk = 0;
$wrongRoute = 0;
$piiFirst = 0;
$searchBeforeConfirm = 0;
$llmAssisted = 0;
$fail = 0;

for ($i = 1; $i <= 10; $i++) {
    $row = runTurn($orch, $primary);
    $row['run'] = $i;
    $primaryRuns[] = $row;
    $latencies[] = $row['latency_ms'];

    $modeOk = in_array($row['mode'], ['QWEN_SEMANTIC', 'STRUCTURED_FALLBACK'], true);
    $confirmOk = $row['status'] === 'confirm';
    $routeOk = ($row['origin'] === 'LHE' && $row['destination'] === 'DXB');
    $adultsOk = (int) $row['adults'] === 2;
    $noPii = ! $row['pii_first'];
    $noSearch = $row['search_calls'] === 0;
    $noLlm = $row['mode'] !== 'LLM_ASSISTED';

    if ($row['mode'] === 'QWEN_SEMANTIC') {
        $qwenOk++;
    }
    if ($row['mode'] === 'STRUCTURED_FALLBACK' && $confirmOk) {
        $hybridOk++;
    }
    if (! $routeOk) {
        $wrongRoute++;
    }
    if ($row['pii_first']) {
        $piiFirst++;
    }
    if (! $noSearch && $confirmOk) {
        $searchBeforeConfirm++;
    }
    if ($row['mode'] === 'LLM_ASSISTED') {
        $llmAssisted++;
    }

    $pass = $modeOk && $confirmOk && $routeOk && $adultsOk && $noPii && $noSearch && $noLlm;
    if (! $pass) {
        $fail++;
    }
    fwrite(STDOUT, sprintf(
        "PRIMARY#%d mode=%s status=%s od=%s-%s adults=%s search=%d pii=%s ms=%d called=%s valid=%s fallback=%s reason=%s\n",
        $i,
        $row['mode'],
        $row['status'],
        (string) $row['origin'],
        (string) $row['destination'],
        (string) $row['adults'],
        $row['search_calls'],
        $row['pii_first'] ? 'YES' : 'NO',
        $row['latency_ms'],
        (string) $row['SEMANTIC_BRAIN_CALLED'],
        (string) $row['SEMANTIC_BRAIN_VALID'],
        (string) $row['SEMANTIC_BRAIN_FALLBACK'],
        (string) $row['SEMANTIC_FALLBACK_REASON']
    ));
}

$followRows = [];
$followWrongRoute = 0;
$followPiiStrong = 0;
$followSearchBefore = 0;
$followLlm = 0;

// Sequenced: establish LHE-DXB, then correction to DOH.
$seqConv = null;
foreach ([
    'I need to go Dubai next Friday from Lahore, me and my wife',
    'Actually make that Doha instead',
] as $msg) {
    $row = runTurn($orch, $msg, $seqConv);
    $seqConv = AiConversation::query()->where('public_id', $row['conversation_id'])->first();
    $row['prompt'] = $msg;
    $followRows[] = $row;
    $strong = (bool) preg_match('/\b(lahore|dubai|doha|jeddah|medina|adults?)\b/u', mb_strtolower($msg));
    if ($row['mode'] === 'LLM_ASSISTED') {
        $followLlm++;
    }
    if ($strong && $row['pii_first']) {
        $followPiiStrong++;
    }
    if ($row['status'] === 'confirm' && $row['search_calls'] > 0) {
        $followSearchBefore++;
    }
    fwrite(STDOUT, sprintf(
        "FOLLOW_SEQ mode=%s status=%s od=%s-%s pii=%s search=%d ms=%d :: %s\n",
        $row['mode'],
        $row['status'],
        (string) $row['origin'],
        (string) $row['destination'],
        $row['pii_first'] ? 'YES' : 'NO',
        $row['search_calls'],
        $row['latency_ms'],
        mb_substr($msg, 0, 60)
    ));
}
// Correction turn should not leave action-ready wrong DXB when Doha was requested.
$corr = $followRows[count($followRows) - 1] ?? null;
if (is_array($corr) && ($corr['status'] ?? '') === 'confirm' && ($corr['destination'] ?? null) === 'DXB') {
    $followWrongRoute++;
}

foreach ([
    'Lahore se Dubai jana hai kal, 2 adults',
    'Lahore to Jeddah then Medina to Lahore',
] as $msg) {
    $row = runTurn($orch, $msg);
    $row['prompt'] = $msg;
    $followRows[] = $row;
    if ($row['mode'] === 'LLM_ASSISTED') {
        $followLlm++;
    }
    if ($row['pii_first']) {
        $followPiiStrong++;
    }
    if ($row['status'] === 'confirm' && $row['search_calls'] > 0) {
        $followSearchBefore++;
    }
    // Open-jaw must not become one-way LHE-JED action-ready confirm without clarify.
    if (str_contains(mb_strtolower($msg), 'medina') && ($row['status'] ?? '') === 'confirm' && ($row['OPEN_JAW_DETECTED'] ?? null) !== 'YES') {
        if (($row['origin'] ?? null) === 'LHE' && ($row['destination'] ?? null) === 'JED') {
            $followWrongRoute++;
        }
    }
    fwrite(STDOUT, sprintf(
        "FOLLOW mode=%s status=%s od=%s-%s open_jaw=%s pii=%s search=%d ms=%d :: %s\n",
        $row['mode'],
        $row['status'],
        (string) $row['origin'],
        (string) $row['destination'],
        (string) $row['OPEN_JAW_DETECTED'],
        $row['pii_first'] ? 'YES' : 'NO',
        $row['search_calls'],
        $row['latency_ms'],
        mb_substr($msg, 0, 60)
    ));
}

$piiFirst = $followPiiStrong; // primary already asserted 0; report strong-travel follow PII only
$searchBeforeConfirm += $followSearchBefore;
$llmAssisted += $followLlm;
$wrongRoute += $followWrongRoute;

sort($latencies);
$p50 = $latencies[(int) floor((count($latencies) - 1) * 0.5)] ?? 0;
$p95 = $latencies[(int) floor((count($latencies) - 1) * 0.95)] ?? 0;

$summary = [
    'PRIMARY_RUNS' => 10,
    'PRIMARY_PASS' => 10 - $fail,
    'PRIMARY_FAIL' => $fail,
    'QWEN_SEMANTIC_SUCCESS' => $qwenOk,
    'SAFE_HYBRID_FALLBACKS' => $hybridOk,
    'WRONG_ROUTE_ACTION_READY' => $wrongRoute,
    'PII_FIRST' => $piiFirst,
    'SEARCH_BEFORE_CONFIRMATION' => $searchBeforeConfirm,
    'LLM_ASSISTED_COUNT' => $llmAssisted,
    'P50_MS' => $p50,
    'P95_MS' => $p95,
    'REAL_MODEL_10X_LOCAL' => ($fail === 0 && $piiFirst === 0 && $searchBeforeConfirm === 0 && $llmAssisted === 0 && $wrongRoute === 0) ? 'PASS' : 'FAIL',
    'primary_runs' => $primaryRuns,
    'followups' => $followRows,
];

file_put_contents($outDir.'/r1-real-model-results.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
fwrite(STDOUT, json_encode([
    'REAL_MODEL_10X_LOCAL' => $summary['REAL_MODEL_10X_LOCAL'],
    'QWEN_SEMANTIC_SUCCESS' => $qwenOk,
    'SAFE_HYBRID_FALLBACKS' => $hybridOk,
    'WRONG_ROUTE_ACTION_READY' => $wrongRoute,
    'PII_FIRST' => $piiFirst,
    'SEARCH_BEFORE_CONFIRMATION' => $searchBeforeConfirm,
    'LLM_ASSISTED_COUNT' => $llmAssisted,
    'P50_MS' => $p50,
    'P95_MS' => $p95,
], JSON_PRETTY_PRINT).PHP_EOL);

exit($summary['REAL_MODEL_10X_LOCAL'] === 'PASS' ? 0 : 1);
