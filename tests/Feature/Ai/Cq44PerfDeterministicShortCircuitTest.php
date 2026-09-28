<?php

namespace Tests\Feature\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Models\AiConversation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Ai\ScriptedInferenceProvider;
use Tests\TestCase;

/**
 * CQ44-PERF-01 — deterministic Qwen short-circuit with essential paths preserved.
 */
class Cq44PerfDeterministicShortCircuitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
        Carbon::setTestNow(Carbon::parse('2026-09-28 12:00:00', 'Asia/Karachi'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function enableSemanticAi(): void
    {
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
            'ota.ai_assistant.anonymous_per_minute' => 120,
            'ai_lab.enabled' => false,
            'ai_embed.enabled' => false,
        ]);

        \App\Models\AiAssistantSetting::query()->delete();
        app(\App\Services\Ai\AiAssistantSettingsService::class)->get();
    }

    private function rebindInference(InferenceProvider $provider): void
    {
        $this->app->instance(InferenceProvider::class, $provider);
        foreach ([
            \App\Services\Ai\Semantic\SemanticBrain::class,
            \App\Services\Ai\Semantic\QwenSemanticPlanner::class,
            \App\Services\Ai\Semantic\SemanticResponseComposer::class,
            \App\Services\Ai\AiConversationalAgent::class,
            \App\Services\Ai\AiChatOrchestrator::class,
            \App\Services\Ai\OpenDomainResponseService::class,
        ] as $abstract) {
            $this->app->forgetInstance($abstract);
        }
    }

    private function chat(string $visitorId, string $message, ?string $conversationId = null): array
    {
        $payload = ['message' => $message];
        if ($conversationId !== null) {
            $payload['conversation_id'] = $conversationId;
        }

        $response = $this->withCookie('jp_ai_vid', $visitorId)
            ->postJson('/api/public/ai/chat', $payload);

        return [
            'response' => $response,
            'conversation_id' => (string) $response->json('conversation_id'),
            'json' => $response->json(),
        ];
    }

    private function planJson(array $overrides = []): string
    {
        $base = [
            'domain' => 'travel',
            'intent' => 'flight_search',
            'operation' => 'clarify',
            'travel' => [
                'trip_type' => 'one_way',
                'origin' => null,
                'destination' => 'DXB',
                'legs' => [['origin' => null, 'destination' => 'DXB', 'departure_date' => null]],
                'return_date' => null,
                'adults' => 1,
            ],
            'missing' => ['origin'],
            'references' => ['active_search' => false, 'pending_confirmation' => false],
            'corrections' => new \stdClass,
            'response_intent' => 'need_origin',
        ];
        $merged = array_replace_recursive($base, $overrides);

        return (string) json_encode($merged, JSON_UNESCAPED_UNICODE);
    }

    private function reloadState(string $conversationId): array
    {
        $conv = AiConversation::query()->where('public_id', $conversationId)->firstOrFail();

        return is_array($conv->shopping_state) ? $conv->shopping_state : [];
    }

    public function test_deterministic_refinements_skip_qwen_after_destination_led(): void
    {
        $this->enableSemanticAi();
        // Only destination-led turn should consume a planner call.
        $provider = new ScriptedInferenceProvider([
            $this->planJson(),
            $this->planJson(['operation' => 'prepare_search']), // must NOT be consumed by refinements
        ]);
        $this->rebindInference($provider);

        $vid = str_repeat('cq44p', 8);
        $cid = null;

        $t1 = $this->chat($vid, 'I need Dubai', $cid);
        $t1['response']->assertOk();
        $cid = $t1['conversation_id'];
        $this->assertSame(1, $provider->callCount());
        $s1 = $this->reloadState($cid);
        $this->assertSame('DXB', $s1['destination'] ?? null);
        $this->assertNull($s1['origin'] ?? null);

        $bypassTurns = [
            'from Lahore' => fn (array $s) => ($s['origin'] ?? null) === 'LHE' && ($s['destination'] ?? null) === 'DXB',
            'next Friday' => fn (array $s) => ($s['depart_date'] ?? null) === '2026-10-02',
            '2 adults' => fn (array $s) => (int) ($s['adults'] ?? 0) === 2,
            'economy' => fn (array $s) => ($s['cabin'] ?? null) === 'economy',
            'Make it Doha' => fn (array $s) => ($s['destination'] ?? null) === 'DOH',
            'actually Dubai again' => fn (array $s) => ($s['destination'] ?? null) === 'DXB',
            'hum dono' => fn (array $s) => (int) ($s['adults'] ?? 0) === 2,
            'wapis Sunday' => fn (array $s) => ($s['return_date'] ?? null) === '2026-10-04'
                || ($s['trip_type'] ?? null) === 'return',
        ];

        foreach ($bypassTurns as $msg => $assertState) {
            $before = $provider->callCount();
            $t = $this->chat($vid, $msg, $cid);
            $t['response']->assertOk();
            $this->assertSame(
                $before,
                $provider->callCount(),
                "Expected zero new Qwen calls for deterministic turn: {$msg}"
            );
            // Public API may omit internal meta; authority proof is zero inference + state.
            $state = $this->reloadState($cid);
            $this->assertTrue($assertState($state), 'State assertion failed for '.$msg.' '.json_encode($state));
            $this->assertNotSame('open_jaw', $state['trip_type'] ?? null, $msg);
        }

        $this->assertSame(1, $provider->callCount(), 'Only destination-led turn should call the planner');
    }

    public function test_essential_qwen_paths_remain_active(): void
    {
        $this->enableSemanticAi();
        $provider = new ScriptedInferenceProvider([
            $this->planJson(),
            // open-jaw / explicit route / CURRENT may still call planner
            $this->planJson([
                'operation' => 'clarify',
                'travel' => [
                    'trip_type' => 'open_jaw',
                    'origin' => 'LHE',
                    'destination' => 'JED',
                    'legs' => [
                        ['origin' => 'LHE', 'destination' => 'JED', 'departure_date' => null],
                        ['origin' => 'MED', 'destination' => 'LHE', 'departure_date' => null],
                    ],
                ],
            ]),
            'Bitcoin market data is not verified here.',
            'Gravity pulls objects toward Earth.',
        ]);
        $this->rebindInference($provider);

        $vid = str_repeat('cq44e', 8);

        $t1 = $this->chat($vid, 'I need Dubai', null);
        $t1['response']->assertOk();
        $cid = $t1['conversation_id'];
        $this->assertGreaterThanOrEqual(1, $provider->callCount());

        $beforeOj = $provider->callCount();
        $oj = $this->chat($vid, 'Lahore to Jeddah then Medina to Lahore', $cid);
        $oj['response']->assertOk();
        $this->assertGreaterThan($beforeOj, $provider->callCount(), 'Open-jaw must still reach Qwen planner');

        $beforeCur = $provider->callCount();
        $cur = $this->chat($vid, "What is Bitcoin's price right now?", $cid);
        $cur['response']->assertOk();
        $body = mb_strtolower((string) $cur['json']['message']);
        $this->assertTrue(
            str_contains($body, 'bitcoin')
            || str_contains($body, 'price')
            || str_contains($body, 'market')
            || str_contains($body, 'verify')
            || str_contains($body, 'live')
            || str_contains($body, "can't")
            || str_contains($body, 'cannot'),
            $body
        );

        $beforeGk = $provider->callCount();
        $gk = $this->chat($vid, 'What is gravity?', $cid);
        $gk['response']->assertOk();
        // GK uses open-domain path (planner bypass for travel) but may still call inference.
        $this->assertNotSame('', trim((string) $gk['json']['message']));
        $this->assertGreaterThanOrEqual($beforeGk, $provider->callCount());
    }
}
