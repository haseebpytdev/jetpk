<?php

namespace Tests\Feature\Ai;

use App\Contracts\Ai\Lab\AiLabConsultantGateway;
use App\Data\Ai\Lab\V1\ConsultantTurnRequest;
use App\Data\Ai\Lab\V1\ConsultantTurnResponse;
use App\Models\AiConversation;
use App\Services\Ai\Lab\FakeAiLabConsultantGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiLabControlledIntegrationPhase13Test extends TestCase
{
    use RefreshDatabase;

    private FakeAiLabConsultantGateway $fakeGateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
        Storage::fake('local');

        $this->fakeGateway = new FakeAiLabConsultantGateway;
        $this->app->instance(AiLabConsultantGateway::class, $this->fakeGateway);

        config([
            'ota.ai_assistant.mode' => 'public',
            'ota.ai_assistant.enabled' => true,
            'ai_lab.enabled' => true,
            'ai_lab.shadow_flight_search' => true,
            'ai_lab.fallback_to_legacy' => false,
            'ai_lab.learning_queue_enabled' => true,
        ]);
    }

    public function test_english_flight_clarify_flow(): void
    {
        $this->fakeGateway->setTurnHandler(fn (ConsultantTurnRequest $req) => ConsultantTurnResponse::fromArray([
            'assistant_message' => 'When would you like to travel from LHE to DXB?',
            'status' => 'clarify',
            'mode' => 'LAB_CONSULTANT_V1',
            'action' => ['kind' => 'NONE'],
            'confirmation' => ['state' => 'NONE'],
            'lab_state' => ['dialog_state' => 'COLLECTING', 'origin' => 'LHE', 'destination' => 'DXB'],
        ]));

        $response = $this->withCookie('jp_ai_vid', str_repeat('e', 40))
            ->postJson('/api/public/ai/chat', ['message' => 'LHE to DXB']);

        $response->assertOk()
            ->assertJsonPath('mode', 'LAB_CONSULTANT_V1')
            ->assertJsonPath('status', 'clarify');
    }

    public function test_needs_confirmation_blocks_shadow_without_confirm(): void
    {
        $this->fakeGateway->setTurnHandler(fn () => ConsultantTurnResponse::fromArray([
            'assistant_message' => 'Please confirm: LHE to DXB on 2026-12-10.',
            'status' => 'needs_confirmation',
            'mode' => 'LAB_CONSULTANT_V1',
            'action' => ['kind' => 'SHADOW_FLIGHT_SEARCH', 'payload' => ['query' => ['origin' => 'LHE', 'destination' => 'DXB']]],
            'confirmation' => ['state' => 'PROPOSED', 'snapshot_hash' => 'token1', 'snapshot' => ['origin' => 'LHE', 'destination' => 'DXB']],
            'lab_state' => ['dialog_state' => 'AWAITING_CONFIRMATION', 'confirmation_token' => 'token1', 'tool_executed' => false],
        ]));

        $response = $this->withCookie('jp_ai_vid', str_repeat('f', 40))
            ->postJson('/api/public/ai/chat', ['message' => 'LHE to DXB 10 dec']);

        $response->assertOk()
            ->assertJsonPath('status', 'needs_confirmation')
            ->assertJsonPath('meta.shadow_flight_search', null);
        $this->assertEmpty($response->json('recommendations'));
    }

    public function test_confirmed_shadow_search_records_stub_not_live(): void
    {
        $this->fakeGateway->setTurnHandler(fn () => ConsultantTurnResponse::fromArray([
            'assistant_message' => 'Lab stub search complete.',
            'status' => 'ok',
            'mode' => 'LAB_CONSULTANT_V1',
            'action' => [
                'kind' => 'SHADOW_FLIGHT_SEARCH',
                'payload' => ['query' => ['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => '2026-12-10']],
            ],
            'confirmation' => ['state' => 'CONFIRMED', 'snapshot_hash' => 'token1'],
            'lab_state' => [
                'dialog_state' => 'RESULTS',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'tool_executed' => true,
                'user_confirmed' => true,
                'confirmation_valid' => true,
                'confirmation_token' => 'token1',
            ],
            'learning_event' => [
                'conversation_id_hash' => 'hash1',
                'language' => 'english',
                'redacted_user_text' => 'yes confirm',
                'user_confirmed' => true,
            ],
        ]));

        $conversation = AiConversation::query()->create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'channel' => 'web',
            'visitor_token_hash' => hash('sha256', str_repeat('g', 40)),
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => ['lab' => ['confirmation_token' => 'token1']],
        ]);

        $response = $this->withCookie('jp_ai_vid', str_repeat('g', 40))
            ->postJson('/api/public/ai/chat', [
                'message' => 'yes confirm',
                'conversation_id' => $conversation->public_id,
            ]);

        $response->assertOk()
            ->assertJsonPath('meta.shadow_flight_search', true)
            ->assertJsonPath('meta.AI_FLIGHT_SEARCH_READ_CALLS', 0);
        $this->assertNotEmpty($response->json('recommendations'));
        Storage::disk('local')->assertExists('ai-lab/learning/events.jsonl');
    }

    public function test_rag_live_data_blocked(): void
    {
        $this->fakeGateway->setTurnHandler(fn () => ConsultantTurnResponse::fromArray([
            'assistant_message' => 'Current fare is PKR 50000',
            'status' => 'ok',
            'mode' => 'LAB_CONSULTANT_V1',
            'action' => ['kind' => 'RAG_ANSWER'],
            'confirmation' => ['state' => 'NONE'],
            'rag' => ['hits' => [], 'blocked_live_data' => false],
            'lab_state' => ['dialog_state' => 'COLLECTING'],
        ]));

        $response = $this->withCookie('jp_ai_vid', str_repeat('h', 40))
            ->postJson('/api/public/ai/chat', ['message' => 'What is the live fare for LHE to DXB?']);

        $response->assertOk()
            ->assertJsonPath('status', 'refused');
        $this->assertStringContainsString('verified booking tools', (string) $response->json('message'));
    }

    public function test_handoff_without_consent_blocked(): void
    {
        $this->fakeGateway->setTurnHandler(fn () => ConsultantTurnResponse::fromArray([
            'assistant_message' => 'Sending to support...',
            'status' => 'ok',
            'mode' => 'LAB_CONSULTANT_V1',
            'action' => ['kind' => 'MOCK_HANDOFF', 'payload' => ['category' => 'visa']],
            'confirmation' => ['state' => 'NONE'],
            'lab_state' => ['dialog_state' => 'UNSUPPORTED_CAPABILITY', 'handoff_consent' => null],
        ]));

        $response = $this->withCookie('jp_ai_vid', str_repeat('i', 40))
            ->postJson('/api/public/ai/chat', ['message' => 'I need a visa']);

        $response->assertOk()
            ->assertJsonPath('status', 'clarify');
    }

    public function test_gateway_timeout_returns_safe_response(): void
    {
        $this->fakeGateway->setTurnHandler(function (): void {
            throw new \RuntimeException('gateway timeout');
        });

        $response = $this->withCookie('jp_ai_vid', str_repeat('j', 40))
            ->postJson('/api/public/ai/chat', ['message' => 'hello']);

        $response->assertOk()
            ->assertJsonPath('status', 'unavailable');
        $this->assertNotEmpty($response->json('message'));
    }

    public function test_malformed_action_kind_blocked(): void
    {
        $this->fakeGateway->setTurnHandler(fn () => ConsultantTurnResponse::fromArray([
            'assistant_message' => 'Running live search',
            'status' => 'ok',
            'mode' => 'LAB_CONSULTANT_V1',
            'action' => ['kind' => 'LIVE_FLIGHT_SEARCH'],
            'confirmation' => ['state' => 'NONE'],
            'lab_state' => [],
        ]));

        $response = $this->withCookie('jp_ai_vid', str_repeat('m', 40))
            ->postJson('/api/public/ai/chat', ['message' => 'search now']);

        $response->assertOk()
            ->assertJsonPath('status', 'refused');
        $this->assertEmpty($response->json('recommendations'));
    }

    public function test_malformed_empty_response_not_silent(): void
    {
        $this->fakeGateway->setTurnHandler(fn () => ConsultantTurnResponse::fromArray([
            'assistant_message' => '',
            'status' => 'ok',
            'mode' => 'LAB_CONSULTANT_V1',
            'action' => ['kind' => 'NONE'],
            'confirmation' => ['state' => 'NONE'],
            'lab_state' => [],
        ]));

        $response = $this->withCookie('jp_ai_vid', str_repeat('k', 40))
            ->postJson('/api/public/ai/chat', ['message' => 'hello']);

        $response->assertOk();
        $this->assertNotEmpty($response->json('message'));
    }

    public function test_lab03_residual_cases_contained(): void
    {
        $fixtures = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/ai/lab-03-residual-5.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        foreach ($fixtures['cases'] as $case) {
            $origin = $case['expected_origin'];
            $destination = $case['expected_destination'];

            $this->fakeGateway->setTurnHandler(fn () => ConsultantTurnResponse::fromArray([
                'assistant_message' => "Please confirm: {$origin} to {$destination}.",
                'status' => 'needs_confirmation',
                'mode' => 'LAB_CONSULTANT_V1',
                'action' => ['kind' => 'NONE'],
                'confirmation' => [
                    'state' => 'PROPOSED',
                    'snapshot' => ['origin' => $origin, 'destination' => $destination],
                    'snapshot_hash' => 'residual-'.$case['case_id'],
                ],
                'parser' => ['route_visible' => true],
                'lab_state' => [
                    'dialog_state' => 'AWAITING_CONFIRMATION',
                    'origin' => $origin,
                    'destination' => $destination,
                    'confirmation_token' => 'residual-'.$case['case_id'],
                    'tool_executed' => false,
                ],
            ]));

            $response = $this->withCookie('jp_ai_vid', str_repeat('l', 40))
                ->postJson('/api/public/ai/chat', ['message' => $case['message']]);

            $response->assertOk()
                ->assertJsonPath('status', 'needs_confirmation');
            $this->assertStringContainsString($origin, (string) $response->json('message'));
            $this->assertStringContainsString($destination, (string) $response->json('message'));
            $this->assertEmpty($response->json('recommendations'));
        }
    }

    public function test_localhost_gateway_enforcement(): void
    {
        config(['ai_lab.gateway_url' => 'http://example.com:8765']);
        $gateway = new \App\Services\Ai\Lab\HttpAiLabConsultantGateway;

        $this->assertFalse($gateway->isHealthy());
    }
}
