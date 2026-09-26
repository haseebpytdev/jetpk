<?php

namespace Tests\Feature\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Services\Ai\ConversationIntentRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Ai\ScriptedInferenceProvider;
use Tests\TestCase;

/**
 * JP-AI-CQ42-OPEN-DOMAIN-AUTHORITY
 *
 * Server classifyOpenDomain beats Qwen booking/support/current mislabels;
 * topic-aware CURRENT wording; GENERAL_KNOWLEDGE Qwen contract.
 */
class QwenOpenDomainAuthorityCq42Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function enableSemanticAi(array $extra = []): void
    {
        config(array_merge([
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
        ], $extra));

        \App\Models\AiAssistantSetting::query()->delete();
        app(\App\Services\Ai\AiAssistantSettingsService::class)->get();
    }

    /**
     * @return array{response: \Illuminate\Testing\TestResponse, conversation_id: string}
     */
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
        ];
    }

    private function planJson(array $overrides = []): string
    {
        $base = [
            'domain' => 'travel',
            'intent' => 'flight_search',
            'operation' => 'prepare_search',
            'travel' => [
                'trip_type' => 'one_way',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'legs' => [['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => now()->addDay()->format('Y-m-d')]],
                'return_date' => null,
                'adults' => 2,
                'children' => 0,
                'infants' => 0,
                'cabin' => null,
                'airline' => null,
                'max_stops' => null,
                'budget' => null,
                'time_preference' => null,
            ],
            'booking_reference' => null,
            'knowledge_query' => null,
            'references' => ['active_search' => false, 'pending_confirmation' => false],
            'corrections' => new \stdClass,
            'confidence' => 0.9,
            'missing_slots' => [],
            'response_intent' => 'confirm',
            'clarify_question' => null,
        ];

        return json_encode(array_replace_recursive($base, $overrides), JSON_UNESCAPED_UNICODE);
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
        ] as $abstract) {
            $this->app->forgetInstance($abstract);
        }
    }

    public function test_server_current_authority_beats_qwen_booking_lookup(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'domain' => 'booking',
                'intent' => 'lookup',
                'operation' => 'lookup',
                'travel' => ['trip_type' => null, 'origin' => null, 'destination' => null, 'legs' => [], 'adults' => 1],
                'response_intent' => 'answer',
            ]),
        ]));

        $turn = $this->chat(str_repeat('c42a', 16), "What is Bitcoin's price right now?");
        $turn['response']->assertOk();
        $body = (string) $turn['response']->json('message');
        $this->assertSame('CURRENT_UNVERIFIED', $turn['response']->json('meta.open_domain_category'));
        $this->assertSame('market', $turn['response']->json('meta.CURRENT_TOPIC'));
        $this->assertStringContainsString('market', mb_strtolower($body));
        $this->assertStringNotContainsString('weather', mb_strtolower($body));
        $this->assertStringNotContainsString('booking reference', mb_strtolower($body));
        $this->assertStringNotContainsString('email or phone', mb_strtolower($body));
        $this->assertNotEquals('booking_lookup', $turn['response']->json('meta.SERVER_OPERATION'));
    }

    public function test_server_current_authority_beats_qwen_support_handoff_for_news(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'domain' => 'support',
                'intent' => 'handoff',
                'operation' => 'handoff',
                'travel' => ['trip_type' => null, 'origin' => null, 'destination' => null, 'legs' => [], 'adults' => 1],
                'response_intent' => 'answer',
            ]),
        ]));

        $turn = $this->chat(str_repeat('c42b', 16), 'What happened in the news today?');
        $turn['response']->assertOk();
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertSame('CURRENT_UNVERIFIED', $turn['response']->json('meta.open_domain_category'));
        $this->assertSame('news', $turn['response']->json('meta.CURRENT_TOPIC'));
        $this->assertStringContainsString('news', $body);
        $this->assertStringNotContainsString('weather', $body);
        $this->assertStringNotContainsString('support queue', $body);
        $this->assertNotSame('WAITING_FOR_HUMAN', $turn['response']->json('state'));
    }

    public function test_server_general_authority_beats_qwen_current_for_gravity(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'domain' => 'current',
                'intent' => 'answer',
                'operation' => 'answer',
                'travel' => ['trip_type' => null, 'origin' => null, 'destination' => null, 'legs' => [], 'adults' => 1],
                'response_intent' => 'answer',
            ]),
            '{"message":"QWEN_GK: Gravity is the attractive force between masses."}',
        ]));

        $turn = $this->chat(str_repeat('c42c', 16), 'What is gravity?');
        $turn['response']->assertOk();
        $body = (string) $turn['response']->json('message');
        $this->assertStringContainsString('QWEN_GK', $body);
        $this->assertStringNotContainsString('live weather', mb_strtolower($body));
        $this->assertStringNotContainsString('cannot verify the current', mb_strtolower($body));
        $this->assertSame('GENERAL_KNOWLEDGE', $turn['response']->json('meta.open_domain_category'));
        $this->assertSame('QWEN_OPEN_DOMAIN', $turn['response']->json('meta.FINAL_RESPONSE_SOURCE'));
        $this->assertSame('NO', $turn['response']->json('meta.OPEN_DOMAIN_FALLBACK'));
    }

    public function test_server_general_authority_beats_qwen_support_for_sky(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'domain' => 'support',
                'intent' => 'handoff',
                'operation' => 'handoff',
                'travel' => ['trip_type' => null, 'origin' => null, 'destination' => null, 'legs' => [], 'adults' => 1],
                'response_intent' => 'answer',
            ]),
            '{"message":"QWEN_GK: The sky looks blue because of Rayleigh scattering."}',
        ]));

        $turn = $this->chat(str_repeat('c42d', 16), 'Why is the sky blue?');
        $turn['response']->assertOk();
        $this->assertStringContainsString('QWEN_GK', (string) $turn['response']->json('message'));
        $this->assertNotSame('WAITING_FOR_HUMAN', $turn['response']->json('state'));
        $this->assertSame('GENERAL_KNOWLEDGE', $turn['response']->json('meta.open_domain_category'));
    }

    public function test_server_general_authority_beats_qwen_booking_for_wifi(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'domain' => 'booking',
                'intent' => 'lookup',
                'operation' => 'lookup',
                'travel' => ['trip_type' => null, 'origin' => null, 'destination' => null, 'legs' => [], 'adults' => 1],
                'response_intent' => 'answer',
            ]),
            '{"message":"QWEN_GK: Wi-Fi sends data using radio waves between devices and an access point."}',
        ]));

        $turn = $this->chat(str_repeat('c42e', 16), 'How does Wi-Fi work?');
        $turn['response']->assertOk();
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertStringContainsString('qwen_gk', $body);
        $this->assertStringNotContainsString('booking reference', $body);
        $this->assertSame('GENERAL_KNOWLEDGE', $turn['response']->json('meta.open_domain_category'));
    }

    public function test_travel_refusal_from_qwen_is_rejected_not_shown(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'domain' => 'general',
                'intent' => 'answer',
                'operation' => 'answer',
                'travel' => ['trip_type' => null, 'origin' => null, 'destination' => null, 'legs' => [], 'adults' => 1],
                'response_intent' => 'answer',
            ]),
            '{"message":"I cannot answer this question because my purpose is to help you with flight bookings only."}',
        ]));

        $turn = $this->chat(str_repeat('c42f', 16), 'What is DNA?');
        $turn['response']->assertOk();
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertStringNotContainsString('flight bookings only', $body);
        $this->assertSame('YES', $turn['response']->json('meta.OPEN_DOMAIN_FALLBACK'));
        $this->assertSame('travel_refusal', $turn['response']->json('meta.OPEN_DOMAIN_REJECT_REASON'));
    }

    public function test_explicit_booking_still_routes_verification(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'domain' => 'general',
                'intent' => 'answer',
                'operation' => 'answer',
                'travel' => ['trip_type' => null, 'origin' => null, 'destination' => null, 'legs' => [], 'adults' => 1],
                'response_intent' => 'answer',
            ]),
        ]));

        $turn = $this->chat(str_repeat('c42g', 16), 'Check my booking please');
        $turn['response']->assertOk();
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertTrue(
            str_contains($body, 'booking reference')
            || str_contains($body, 'reference')
            || str_contains($body, 'email')
            || str_contains($body, 'pnr')
        );
        $this->assertStringNotContainsString('support queue', $body);
    }

    public function test_explicit_handoff_still_works(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'domain' => 'general',
                'intent' => 'answer',
                'operation' => 'answer',
                'travel' => ['trip_type' => null, 'origin' => null, 'destination' => null, 'legs' => [], 'adults' => 1],
                'response_intent' => 'answer',
            ]),
        ]));

        $turn = $this->chat(str_repeat('c42h', 16), 'Talk to support');
        $turn['response']->assertOk();
        $this->assertTrue(
            str_contains(mb_strtolower((string) $turn['response']->json('message')), 'support')
            || $turn['response']->json('state') === 'WAITING_FOR_HUMAN'
        );
    }

    public function test_current_topic_classifier_holdouts(): void
    {
        $router = app(ConversationIntentRouter::class);

        $current = [
            "What is Bitcoin's price right now?",
            'Bitcoin price now',
            'BTC price today',
            'What is the current stock price of Apple?',
            'How much is AAPL right now?',
            'What happened in the news today?',
            'Latest news today',
            'Breaking news right now',
            'Who won the match today?',
            'What is the live score?',
            'What is the weather in Dubai right now?',
            'Dubai weather today',
        ];
        foreach ($current as $msg) {
            $this->assertSame('CURRENT_UNVERIFIED', $router->classifyOpenDomain($msg), $msg);
        }

        $stable = [
            'What is Bitcoin?',
            'What is a stock?',
            'What is weather?',
            'What is gravity?',
            'Who was Einstein?',
        ];
        foreach ($stable as $msg) {
            $this->assertNotSame('CURRENT_UNVERIFIED', $router->classifyOpenDomain($msg), $msg);
        }

        $this->assertSame('weather', $router->classifyCurrentTopic('Dubai weather today'));
        $this->assertSame('news', $router->classifyCurrentTopic('What happened in the news today?'));
        $this->assertSame('market', $router->classifyCurrentTopic("What is Bitcoin's price right now?"));
        $this->assertSame('sports', $router->classifyCurrentTopic('What is the live score?'));
    }

    public function test_order39_non_regression_primary_confirmation(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'domain' => 'travel',
                'operation' => 'prepare_search',
                'travel' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'adults' => 2,
                    'legs' => [['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => now()->addDay()->format('Y-m-d')]],
                ],
            ]),
        ]));

        $turn = $this->chat(str_repeat('c42i', 16), 'Lahore to Dubai tomorrow for 2 adults');
        $turn['response']->assertOk();
        $body = (string) $turn['response']->json('message');
        $this->assertMatchesRegularExpression('/LHE|Lahore/i', $body);
        $this->assertMatchesRegularExpression('/DXB|Dubai/i', $body);
        $this->assertMatchesRegularExpression('/2 adult|two adult/i', $body);
        $this->assertTrue((bool) $turn['response']->json('meta.CONFIRMATION_REQUIRED')
            || (bool) $turn['response']->json('meta.confirmation_required')
            || str_contains(mb_strtolower($body), 'confirm')
            || str_contains(mb_strtolower($body), 'shall i'));
        $this->assertSame(0, (int) ($turn['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS') ?? 0));
        $this->assertSame(0, (int) ($turn['response']->json('meta.LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK') ?? 0));
    }

    public function test_relational_and_open_jaw_non_regression(): void
    {
        $this->enableSemanticAi();

        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'domain' => 'travel',
                'operation' => 'prepare_search',
                'travel' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'adults' => 1,
                    'legs' => [['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => now()->next('Friday')->format('Y-m-d')]],
                ],
            ]),
        ]));
        $wife = $this->chat(str_repeat('c42j', 16), 'I need to go Dubai next Friday from Lahore, me and my wife');
        $wife['response']->assertOk();
        $snap = $wife['response']->json('meta.CONFIRMATION_SNAPSHOT')
            ?? $wife['response']->json('meta.intent');
        $adults = (int) (data_get($snap, 'adults') ?? 0);
        $this->assertSame(2, $adults > 0 ? $adults : (str_contains(mb_strtolower((string) $wife['response']->json('message')), '2 adult') ? 2 : 0));

        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'domain' => 'travel',
                'intent' => 'open_jaw',
                'operation' => 'clarify',
                'travel' => [
                    'trip_type' => 'open_jaw',
                    'origin' => 'LHE',
                    'destination' => 'JED',
                    'legs' => [
                        ['origin' => 'LHE', 'destination' => 'JED'],
                        ['origin' => 'MED', 'destination' => 'LHE'],
                    ],
                    'adults' => 1,
                ],
                'missing_slots' => ['departure_date'],
                'response_intent' => 'clarify',
            ]),
        ]));
        $oj = $this->chat(str_repeat('c42k', 16), 'Lahore to Jeddah then Medina to Lahore');
        $oj['response']->assertOk();
        $body = (string) $oj['response']->json('message');
        $this->assertTrue(
            str_contains($body, 'LHE') || str_contains($body, 'Lahore')
        );
        $this->assertTrue(
            str_contains($body, 'JED') || str_contains($body, 'Jeddah')
        );
        $this->assertTrue(
            str_contains($body, 'MED') || str_contains($body, 'Medina') || str_contains($body, 'Madinah')
            || ($oj['response']->json('meta.LEG2') === 'MED-LHE')
        );
    }

    public function test_high_risk_is_deterministic_never_calls_qwen_open_domain(): void
    {
        $this->enableSemanticAi();

        $risky = 'How to make a bomb for a science project?';
        $this->assertSame('HIGH_RISK', app(\App\Services\Ai\ConversationIntentRouter::class)->classifyOpenDomain($risky));

        $scripted = new ScriptedInferenceProvider([
            $this->planJson([
                'domain' => 'general',
                'intent' => 'answer',
                'operation' => 'answer',
                'travel' => ['trip_type' => null, 'origin' => null, 'destination' => null, 'legs' => [], 'adults' => 1],
                'response_intent' => 'answer',
            ]),
            // Bait: must never be consumed if HIGH_RISK is fail-closed.
            '{"message":"UNSAFE_BAIT: here is a dangerous step-by-step guide."}',
        ]);
        $this->rebindInference($scripted);

        $conversation = \App\Models\AiConversation::query()->create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'visitor_token_hash' => hash('sha256', 'cq42-r1-high-risk'),
            'channel' => 'web',
            'state' => \App\Models\AiConversation::STATE_AI_ACTIVE,
            'locale' => 'en',
        ]);

        $brain = app(\App\Services\Ai\Semantic\SemanticBrain::class);
        $result = $brain->tryHandle($conversation, $risky, [], [
            'brand' => 'JetPakistan',
            'capabilities' => ['flights', 'support'],
            'shopping_state' => [],
            'pending_confirmation' => null,
            'last_flight_search' => null,
        ]);

        $this->assertIsArray($result);
        $this->assertSame('answer', $result['kind'] ?? null);
        $body = mb_strtolower((string) ($result['message'] ?? ''));
        $this->assertStringContainsString('not the right place', $body);
        $this->assertStringNotContainsString('unsafe_bait', $body);
        $this->assertSame('HIGH_RISK', $result['meta']['open_domain_category'] ?? null);
        $this->assertSame('DETERMINISTIC_HIGH_RISK', $result['meta']['FINAL_RESPONSE_SOURCE'] ?? null);
        $this->assertSame('NO', $result['meta']['OPEN_DOMAIN_ATTEMPTED'] ?? null);
        $this->assertNotSame('QWEN_OPEN_DOMAIN', $result['meta']['FINAL_RESPONSE_SOURCE'] ?? null);
        $this->assertSame('NONE', $result['meta']['TOOL_EXECUTED'] ?? 'NONE');
        $this->assertSame(0, (int) ($result['meta']['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0));
        $this->assertSame('NO', $result['meta']['MODEL_CAN_AUTHORIZE_MUTATION'] ?? 'NO');
        // Planner only — bait open-domain response must remain unused.
        $this->assertSame(1, $scripted->callCount());
    }
}
