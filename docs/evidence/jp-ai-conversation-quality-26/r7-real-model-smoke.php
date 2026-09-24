<?php

/**
 * R7 real-model smoke — six CQ26 prompts plus fidelity Undefined/Null subjects.
 * Boots Laravel; uses live LocalLlamaProvider via tunnel to :3921.
 */

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\AiChatOrchestrator;
use Illuminate\Http\Request;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

config([
    'ota.ai_assistant.mode' => 'public',
    'ota.ai_assistant.enabled' => true,
    'ota.ai_assistant.hard_allow.master' => true,
    'ota.ai_assistant.hard_allow.public' => true,
    'ota.ai_assistant.conversational_enabled' => true,
    'ota.ai_assistant.knowledge_enabled' => true,
    'ota.ai_assistant.gateway_url' => 'http://127.0.0.1:3921',
    'ota.ai_assistant.model_id' => 'local',
]);

$app->forgetInstance(App\Contracts\Ai\InferenceProvider::class);
$app->singleton(App\Contracts\Ai\InferenceProvider::class, fn () => new App\Services\Ai\LocalLlamaProvider);

$orchestrator = $app->make(AiChatOrchestrator::class);
$provider = $app->make(App\Contracts\Ai\InferenceProvider::class);
fwrite(STDOUT, 'PROVIDER='.$provider->name().' healthy='.($provider->isHealthy() ? 'yes' : 'no').PHP_EOL);

$prompts = [
    'JETPAKISTAN' => 'What is JetPakistan?',
    'GENERAL_KNOWLEDGE' => 'What is E=mc2?',
    'PRIVATE_JET' => 'Where can I buy a jet?',
    'CASUAL' => 'Tell me a joke',
    'CURRENT_UNVERIFIED' => "What's Apple's stock price right now?",
    'TRAVEL_HELP_FIRST' => 'I need Lahore to Dubai tomorrow for 2 adults',
    'LEGIT_UNDEFINED' => 'Undefined behavior in C?',
    'LEGIT_NULL' => 'Null hypothesis in statistics?',
];

function freshConversation(string $visitor): AiConversation
{
    $request = Request::create('/api/public/ai/chat', 'POST', server: [
        'HTTP_COOKIE' => 'jp_ai_vid='.$visitor,
    ]);
    $request->cookies->set('jp_ai_vid', $visitor);
    /** @var AiChatOrchestrator $orch */
    $orch = app(AiChatOrchestrator::class);
    $resolved = $orch->resolveConversation($request, null);

    return $resolved['conversation'];
}

function score(string $id, string $userMessage, array $payload, AiConversation $conversation): array
{
    $msg = (string) ($payload['message'] ?? '');
    $meta = $payload['meta'] ?? [];
    $mode = $payload['mode'] ?? null;
    $pass = true;
    $notes = [];

    $stored = AiMessage::query()
        ->where('ai_conversation_id', $conversation->id)
        ->where('role', 'user')
        ->orderByDesc('id')
        ->value('body');
    if ($stored !== $userMessage) {
        $pass = false;
        $notes[] = 'user_body_mutated:'.json_encode($stored);
    }

    if ($msg === '') {
        $pass = false;
        $notes[] = 'empty';
    }
    if ($id === 'CURRENT_UNVERIFIED') {
        if (preg_match('/\$\s?\d|\b\d{2,}\s*(usd|pkr)\b|trading higher|is up today|is down today/i', $msg) === 1) {
            $pass = false;
            $notes[] = 'live_claim';
        }
        if (preg_match('/(can\'?t|cannot|unable|don\'?t have|not available|verify|confirm)/i', $msg) !== 1) {
            $pass = false;
            $notes[] = 'no_limitation_cue';
        }
    }
    if ($id === 'JETPAKISTAN' && preg_match('/jetpakistan|flight|travel/i', $msg) !== 1) {
        $pass = false;
        $notes[] = 'weak_grounding';
    }
    if ($id === 'TRAVEL_HELP_FIRST'
        && preg_match('/please (enter|provide|share) your (full )?name/i', $msg) === 1
        && preg_match('/lahore|dubai|flight/i', $msg) !== 1) {
        $pass = false;
        $notes[] = 'blocking_name_loop';
    }
    if ($id === 'LEGIT_UNDEFINED') {
        if (preg_match('/\b(undefined|behavior|compiler|programming|c language|c standard)\b/i', $msg) !== 1) {
            $pass = false;
            $notes[] = 'no_subject_engagement';
        }
        if (preg_match('/could not find an approved answer/i', $msg) === 1) {
            $pass = false;
            $notes[] = 'knowledge_miss_instead_of_open_domain';
        }
    }
    if ($id === 'LEGIT_NULL') {
        if (preg_match('/\b(null|hypothesis|statistics|statistic|significance|p-value)\b/i', $msg) !== 1) {
            $pass = false;
            $notes[] = 'no_subject_engagement';
        }
        if (preg_match('/could not find an approved answer/i', $msg) === 1) {
            $pass = false;
            $notes[] = 'knowledge_miss_instead_of_open_domain';
        }
    }

    return compact('pass', 'notes', 'mode', 'meta') + [
        'message' => $msg,
        'stored_user_body' => $stored,
    ];
}

$results = [];
foreach ($prompts as $id => $message) {
    $visitor = substr(preg_replace('/[^a-z0-9]/i', '', $id).bin2hex(random_bytes(16)).str_repeat('x', 40), 0, 40);
    $started = microtime(true);
    $conversation = freshConversation($visitor);
    $sanitized = $orchestrator->sanitizeUserMessage($message);
    if (! ($sanitized['ok'] ?? false)) {
        $results[$id] = [
            'pass' => false,
            'notes' => ['sanitize_rejected'],
            'message' => '',
            'stored_user_body' => null,
            'ms' => 0,
        ];
        continue;
    }
    $clean = $sanitized['message'];
    $payload = $orchestrator->handleChat($conversation, $clean);
    $ms = (int) ((microtime(true) - $started) * 1000);
    $scored = score($id, $clean, $payload, $conversation->fresh());
    $results[$id] = $scored + ['ms' => $ms, 'input' => $message];
    fwrite(STDOUT, sprintf(
        "%s pass=%s ms=%d stored_ok=%s synth=%s\n  in=%s\n  out=%s\n",
        $id,
        $scored['pass'] ? 'YES' : 'NO',
        $ms,
        ($scored['stored_user_body'] ?? null) === $clean ? 'YES' : 'NO',
        $scored['meta']['LLM_SYNTHESIS'] ?? '-',
        $message,
        mb_substr($scored['message'], 0, 220)
    ));
}

$allPass = ! in_array(false, array_column($results, 'pass'), true);
$out = [
    'ALL_PASS' => $allPass,
    'results' => $results,
];
file_put_contents(__DIR__.'/r7-real-model-smoke.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
fwrite(STDOUT, 'ALL_PASS='.($allPass ? 'YES' : 'NO').PHP_EOL);
exit($allPass ? 0 : 1);
