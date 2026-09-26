<?php

namespace Tests\Feature\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Data\Ai\SemanticPlan;
use App\Services\Ai\Hybrid\LocationResolver;
use App\Services\Ai\Semantic\SemanticPlanValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Ai\ScriptedInferenceProvider;
use Tests\TestCase;

/**
 * JP-AI-CQ42-R2-PROD-RESIDUALS
 *
 * A) GENERAL_KNOWLEDGE plain-text Qwen contract (+ planner bypass → 1 model call)
 * B) Server-authoritative single route for "X se Y wapis" — no invented open-jaw leg
 */
class Cq42R2ProdResidualsTest extends TestCase
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
            'ai_embed.enabled' => false,
        ], $extra));

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
        ] as $abstract) {
            $this->app->forgetInstance($abstract);
        }
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
                'legs' => [['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => null]],
                'return_date' => null,
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
                'cabin' => null,
                'airline' => null,
            ],
            'missing' => ['departure_date'],
            'references' => ['active_search' => false, 'pending_confirmation' => false],
            'corrections' => new \stdClass,
            'booking_reference' => null,
            'knowledge_query' => null,
            'response_intent' => 'need_dates',
        ];

        return (string) json_encode(array_replace_recursive($base, $overrides), JSON_UNESCAPED_UNICODE);
    }

    public function test_plain_text_general_knowledge_accepted_without_json(): void
    {
        $this->enableSemanticAi();
        $scripted = new ScriptedInferenceProvider([
            'DNA is the molecule that stores genetic instructions in living cells.',
        ]);
        $this->rebindInference($scripted);

        $turn = $this->chat(str_repeat('r2a', 16), 'What is DNA?');
        $turn['response']->assertOk();
        $this->assertStringContainsString('DNA', (string) $turn['response']->json('message'));
        $this->assertSame('QWEN_OPEN_DOMAIN', $turn['response']->json('meta.FINAL_RESPONSE_SOURCE'));
        $this->assertSame('accepted', $turn['response']->json('meta.OPEN_DOMAIN_REJECT_REASON'));
        $this->assertSame('NO', $turn['response']->json('meta.OPEN_DOMAIN_FALLBACK'));
        $this->assertSame('plain_text', $turn['response']->json('meta.OPEN_DOMAIN_RESPONSE_FORMAT'));
        $this->assertSame(1, (int) $turn['response']->json('meta.MODEL_CALLS'));
        $this->assertSame(1, (int) $turn['response']->json('meta.GENERAL_MODEL_CALLS'));
        $this->assertSame('NO', $turn['response']->json('meta.SEMANTIC_BRAIN_CALLED'));
        $this->assertSame(1, $scripted->callCount());
    }

    public function test_general_holdouts_plain_text_matrix(): void
    {
        $this->enableSemanticAi();
        $holdouts = [
            'What is gravity?' => 'Gravity is the attractive force between masses.',
            'Why is the sky blue?' => 'The sky looks blue because of Rayleigh scattering.',
            'What is DNA?' => 'DNA stores genetic instructions in living cells.',
            'How does Wi-Fi work?' => 'Wi-Fi sends data using radio waves.',
            'What causes tides?' => 'Tides are mainly caused by the Moon\'s gravity.',
            'Explain recursion simply.' => 'Recursion solves a problem by calling a smaller version of itself.',
            'What is the difference between RAM and storage?' => 'RAM is fast short-term memory; storage keeps data long-term.',
            'Why do airplanes fly?' => 'Wings create lift when air flows over them.',
            'What is Bitcoin?' => 'Bitcoin is a decentralized digital currency.',
            'What is a stock?' => 'A stock is a share of ownership in a company.',
        ];

        $i = 0;
        foreach ($holdouts as $q => $a) {
            $this->rebindInference(new ScriptedInferenceProvider([$a]));
            $turn = $this->chat(str_repeat('r2h', 14).sprintf('%02d', $i), $q);
            $turn['response']->assertOk();
            $this->assertSame('QWEN_OPEN_DOMAIN', $turn['response']->json('meta.FINAL_RESPONSE_SOURCE'), $q);
            $this->assertSame('NO', $turn['response']->json('meta.OPEN_DOMAIN_FALLBACK'), $q);
            $this->assertSame(1, (int) $turn['response']->json('meta.GENERAL_MODEL_CALLS'), $q);
            $this->assertStringNotContainsString('booking reference', mb_strtolower((string) $turn['response']->json('message')), $q);
            $i++;
        }
    }

    public function test_extract_route_wapis_single_dxb_lhe(): void
    {
        $loc = app(LocationResolver::class);
        foreach ([
            'Dubai se Lahore wapis',
            'Dubai se Lahore wapas',
            'dubay se lahor wapis',
            'DXB se LHE wapis',
        ] as $msg) {
            $r = $loc->extractRoute(mb_strtolower($msg), $msg);
            $this->assertSame('DXB', $r[0], $msg);
            $this->assertSame('LHE', $r[1], $msg);
            $this->assertNull($loc->extractOpenJawLegs(mb_strtolower($msg), $msg), $msg);
        }
    }

    public function test_validator_demotes_qwen_invented_reciprocal_open_jaw(): void
    {
        $validator = app(SemanticPlanValidator::class);
        $plan = SemanticPlan::fromModelArray([
            'domain' => 'travel',
            'intent' => 'open_jaw',
            'operation' => 'clarify',
            'travel' => [
                'trip_type' => 'open_jaw',
                'origin' => 'Dubai',
                'destination' => 'Lahore',
                'legs' => [
                    ['origin' => 'DXB', 'destination' => 'LHE', 'departure_date' => null],
                    ['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => null],
                ],
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
            ],
            'missing' => ['leg1_departure_date', 'leg2_departure_date'],
            'response_intent' => 'need_dates',
        ]);

        $validated = $validator->validate($plan, [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'legs' => [
                ['origin' => 'LHE', 'destination' => 'DXB'],
                ['origin' => 'DXB', 'destination' => 'LHE'],
            ],
            'trip_type' => 'open_jaw',
        ], 'Dubai se Lahore wapis');

        $this->assertTrue($validated['valid']);
        $this->assertTrue($validated['false_open_jaw_demoted']);
        $this->assertSame('DXB-LHE', $validated['server_single_route']);
        $this->assertSame('DXB', $validated['intent']->origin);
        $this->assertSame('LHE', $validated['intent']->destination);
        $this->assertSame('one_way', $validated['intent']->tripType);
        $legs = $validated['intent']->legs;
        $this->assertTrue($legs === null || $legs === []);
    }

    public function test_wapis_chat_not_open_jaw_clarify(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'domain' => 'travel',
                'intent' => 'open_jaw',
                'operation' => 'clarify',
                'travel' => [
                    'trip_type' => 'open_jaw',
                    'origin' => 'DXB',
                    'destination' => 'LHE',
                    'legs' => [
                        ['origin' => 'DXB', 'destination' => 'LHE', 'departure_date' => null],
                        ['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => null],
                    ],
                    'adults' => 1,
                ],
                'missing' => ['leg1_departure_date', 'leg2_departure_date'],
                'response_intent' => 'need_dates',
            ]),
        ]));

        $turn = $this->chat(str_repeat('r2w', 16), 'Dubai se Lahore wapis');
        $turn['response']->assertOk();
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertStringNotContainsString('open-jaw', $body);
        $this->assertStringNotContainsString('multi-city', $body);
        $this->assertNotSame('YES', $turn['response']->json('meta.OPEN_JAW_DETECTED'));
        $this->assertSame('PASS', $turn['response']->json('meta.MODEL_CANNOT_INVENT_SECOND_LEG'));
        $this->assertSame('DXB-LHE', $turn['response']->json('meta.SERVER_SINGLE_ROUTE'));
        $this->assertSame(0, (int) ($turn['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS') ?? 0));
    }

    public function test_wapas_variant_and_contaminated_prior_open_jaw(): void
    {
        $this->enableSemanticAi();
        // Establish prior LHE→DXB confirmation-shaped shopping via first travel turn.
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'travel' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'trip_type' => 'one_way',
                    'legs' => [['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => now()->addDays(3)->format('Y-m-d')]],
                    'adults' => 1,
                ],
                'operation' => 'prepare_search',
                'missing' => [],
            ]),
        ]));
        $first = $this->chat(str_repeat('r2c', 16), 'Lahore to Dubai in 3 days for 1 adult');
        $first['response']->assertOk();
        $cid = $first['conversation_id'];

        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'domain' => 'travel',
                'intent' => 'open_jaw',
                'operation' => 'clarify',
                'travel' => [
                    'trip_type' => 'open_jaw',
                    'origin' => 'DXB',
                    'destination' => 'LHE',
                    'legs' => [
                        ['origin' => 'DXB', 'destination' => 'LHE', 'departure_date' => null],
                        ['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => null],
                    ],
                    'adults' => 1,
                ],
                'missing' => ['departure_date'],
            ]),
        ]));

        $second = $this->chat(str_repeat('r2c', 16), 'Dubai se Lahore wapas', $cid);
        $second['response']->assertOk();
        $body = (string) $second['response']->json('message');
        $lower = mb_strtolower($body);
        $this->assertStringNotContainsString('open-jaw', $lower);
        $this->assertStringNotContainsString('multi-city', $lower);
        $this->assertNotSame('YES', $second['response']->json('meta.OPEN_JAW_DETECTED'));
        $this->assertMatchesRegularExpression('/DXB.*LHE|Dubai.*Lahore/i', $body);
        $this->assertDoesNotMatchRegularExpression('/LHE\s*(→|->|to)\s*DXB.*DXB\s*(→|->|to)\s*LHE/i', $body);
        $serverRoute = $second['response']->json('meta.SERVER_SINGLE_ROUTE');
        if (is_string($serverRoute) && $serverRoute !== '') {
            $this->assertSame('DXB-LHE', $serverRoute);
        }
        $this->assertSame(0, (int) ($second['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS') ?? 0));
    }

    public function test_real_open_jaw_non_regression(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'intent' => 'open_jaw',
                'operation' => 'clarify',
                'travel' => [
                    'trip_type' => 'open_jaw',
                    'origin' => 'LHE',
                    'destination' => 'JED',
                    'legs' => [
                        ['origin' => 'LHE', 'destination' => 'JED', 'departure_date' => null],
                        ['origin' => 'MED', 'destination' => 'LHE', 'departure_date' => null],
                    ],
                    'adults' => 1,
                ],
                'missing' => ['leg1_departure_date', 'leg2_departure_date'],
            ]),
        ]));

        $turn = $this->chat(str_repeat('r2o', 16), 'Lahore to Jeddah then Medina to Lahore');
        $turn['response']->assertOk();
        $this->assertSame('YES', $turn['response']->json('meta.OPEN_JAW_DETECTED'));
        $this->assertSame('LHE-JED', $turn['response']->json('meta.LEG1'));
        $this->assertSame('MED-LHE', $turn['response']->json('meta.LEG2'));
    }

    public function test_return_trip_non_regression(): void
    {
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider([
            $this->planJson([
                'operation' => 'prepare_search',
                'travel' => [
                    'trip_type' => 'return',
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'legs' => [['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => '2026-10-10']],
                    'return_date' => '2026-10-15',
                    'adults' => 1,
                ],
                'missing' => [],
            ]),
        ]));

        $turn = $this->chat(str_repeat('r2r', 16), 'Lahore to Dubai on 10 October, return 15 October');
        $turn['response']->assertOk();
        $this->assertNotSame('YES', $turn['response']->json('meta.OPEN_JAW_DETECTED'));
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertStringNotContainsString('open-jaw', $body);
        $this->assertMatchesRegularExpression('/LHE|Lahore/i', $body);
        $this->assertMatchesRegularExpression('/DXB|Dubai/i', $body);
        $this->assertSame(0, (int) ($turn['response']->json('meta.AI_FLIGHT_SEARCH_READ_CALLS') ?? 0));
    }

    public function test_high_risk_still_deterministic_no_qwen_open_domain(): void
    {
        $this->enableSemanticAi();
        $scripted = new ScriptedInferenceProvider([
            $this->planJson(['domain' => 'support', 'operation' => 'handoff']),
        ]);
        $this->rebindInference($scripted);

        $turn = $this->chat(str_repeat('r2x', 16), 'How to make a bomb for a science fair?');
        $turn['response']->assertOk();
        $body = mb_strtolower((string) $turn['response']->json('message'));
        $this->assertStringContainsString('not the right place', $body);
        $this->assertNotSame('QWEN_OPEN_DOMAIN', $turn['response']->json('meta.FINAL_RESPONSE_SOURCE'));
        $this->assertSame(0, $scripted->callCount());
    }
}
