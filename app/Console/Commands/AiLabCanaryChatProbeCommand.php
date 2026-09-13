<?php

namespace App\Console\Commands;

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Ai\AiChatOrchestrator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Server-side internal canary chat probe (authorized admin user context).
 */
class AiLabCanaryChatProbeCommand extends Command
{
    protected $signature = 'ai:lab-canary-chat-probe {--json : JSON output}';

    protected $description = 'Exercise lab adapter canary path with platform admin conversation context';

    public function handle(AiChatOrchestrator $orchestrator): int
    {
        $admin = User::query()
            ->where('email', 'jp-dash-03-qa-admin@jetpakistan.pk')
            ->first();

        if ($admin === null) {
            $this->error('Canary admin user not found');

            return self::FAILURE;
        }

        $cases = [
            ['id' => '01-english', 'message' => 'LHE to DXB tomorrow'],
            ['id' => '02-roman-urdu', 'message' => 'lahore se dubai kal'],
            ['id' => '21-rag', 'message' => 'baggage allowance'],
            ['id' => '26-live-fare', 'message' => 'what is the live fare LHE to DXB'],
            ['id' => '18-visa', 'message' => 'I need a tourist visa'],
            ['id' => 'residual-1', 'message' => 'dubay se lahor 10 dec'],
        ];

        $passed = 0;
        $failed = 0;
        $results = [];

        foreach ($cases as $case) {
            $conversation = AiConversation::query()->create([
                'public_id' => (string) Str::uuid(),
                'channel' => 'web',
                'visitor_token_hash' => hash('sha256', Str::random(40)),
                'user_id' => $admin->id,
                'state' => AiConversation::STATE_AI_ACTIVE,
                'shopping_state' => [],
            ]);

            try {
                $payload = $orchestrator->handleChat($conversation, $case['message']);
                $ok = ($payload['message'] ?? '') !== '' && ($payload['ok'] ?? false) === true;
                $mode = (string) ($payload['mode'] ?? '');
                if ($case['id'] === '26-live-fare') {
                    $ok = $ok && (($payload['status'] ?? '') === 'refused' || str_contains(strtolower((string) $payload['message']), 'booking'));
                }
                if (str_starts_with($case['id'], 'residual')) {
                    $reply = (string) ($payload['message'] ?? '');
                    $ok = $ok
                        && str_contains($reply, 'DXB')
                        && str_contains($reply, 'LHE')
                        && ! (bool) data_get($payload, 'meta.tool_executed', false);
                }
                $results[] = [
                    'id' => $case['id'],
                    'ok' => $ok,
                    'mode' => $mode,
                    'status' => $payload['status'] ?? null,
                    'lab' => (bool) data_get($payload, 'meta.lab_adapter'),
                ];
                $ok ? $passed++ : $failed++;
            } catch (\Throwable $e) {
                $results[] = ['id' => $case['id'], 'ok' => false, 'error' => $e->getMessage()];
                $failed++;
            }
        }

        $anon = AiConversation::query()->create([
            'public_id' => (string) Str::uuid(),
            'channel' => 'web',
            'visitor_token_hash' => hash('sha256', Str::random(40)),
            'user_id' => null,
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => [],
        ]);
        $anonPayload = $orchestrator->handleChat($anon, 'LHE to DXB tomorrow');
        $anonUsesLab = (bool) data_get($anonPayload, 'meta.lab_adapter');
        $results[] = ['id' => 'anon-legacy', 'ok' => ! $anonUsesLab, 'mode' => $anonPayload['mode'] ?? null];
        $anonUsesLab ? $failed++ : $passed++;

        $summary = [
            'canary_admin_id' => $admin->id,
            'passed' => $passed,
            'failed' => $failed,
            'results' => $results,
            'config' => [
                'mode' => config('ota.ai_assistant.mode'),
                'canary_only' => config('ai_lab.canary_only'),
                'adapter_enabled' => config('ai_lab.enabled'),
            ],
        ];

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT));
        } else {
            $this->info("Canary chat probe: {$passed} passed, {$failed} failed");
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
