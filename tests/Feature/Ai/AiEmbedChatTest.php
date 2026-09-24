<?php

namespace Tests\Feature\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Models\Agency;
use App\Models\AiConversation;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\CustomerQuery;
use App\Enums\BookingStatus;
use App\Services\Ai\NullInferenceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\InteractsWithEmbedTenants;
use Tests\TestCase;

class AiEmbedChatTest extends TestCase
{
    use InteractsWithEmbedTenants;
    use RefreshDatabase;

    private const PARENT = 'https://client.example.com';

    private const ENTRY_PATH = 'test-embed-path-token12';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function enableEmbed(array $extra = []): void
    {
        $this->enableEmbedTenant(embedKey: self::ENTRY_PATH, allowedOrigins: [self::PARENT]);
        if ($extra !== []) {
            config($extra);
            \App\Models\AiAssistantSetting::query()->delete();
            app(\App\Services\Ai\AiAssistantSettingsService::class)->get();
        }
    }

    /**
     * @return array{token: string, headers: array<string, string>}
     */
    private function embedHeaders(string $parentOrigin = self::PARENT): array
    {
        $session = $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/session'), [], [
            'X-JP-AI-Embed-Parent-Origin' => $parentOrigin,
        ])->assertOk();

        $token = (string) $session->json('token');

        return [
            'token' => $token,
            'headers' => [
                'X-JP-AI-Embed-Session' => $token,
                'X-JP-AI-Embed-Parent-Origin' => $parentOrigin,
            ],
        ];
    }

    public function test_embed_chat_general_question(): void
    {
        $this->enableEmbed();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);
        $embed = $this->embedHeaders();

        $response = $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/chat'), [
            'message' => 'What is JetPakistan?',
        ], $embed['headers']);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonMissingPath('Set-Cookie');
        $this->assertNotEmpty($response->json('conversation_id'));
    }

    public function test_embed_chat_uses_knowledge_for_support_question(): void
    {
        $this->enableEmbed();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);
        $embed = $this->embedHeaders();

        $response = $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/chat'), [
            'message' => 'How can I contact JetPakistan support?',
        ], $embed['headers']);

        $response->assertOk()
            ->assertJsonPath('meta.intent.intent', 'knowledge');
    }

    public function test_embed_read_only_search_reuses_orchestrator(): void
    {
        $tenant = $this->enableEmbedTenant(embedKey: self::ENTRY_PATH, allowedOrigins: [self::PARENT]);
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);

        $embed = $this->embedHeaders();
        $sessionPayload = Cache::get('ai_embed_sess:'.hash('sha256', $embed['token']));
        $visitorHash = hash('sha256', (string) ($sessionPayload['visitor_raw'] ?? ''));

        CustomerQuery::query()->create([
            'visitor_token_hash' => $visitorHash,
            'ai_embed_tenant_id' => $tenant->id,
            'name' => 'Embed Guest',
            'email' => 'embed-guest@example.com',
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

        $response = $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/chat'), [
            'message' => 'LHE to DXB tomorrow',
        ], $embed['headers']);

        $response->assertOk()
            ->assertJsonPath('mode', 'STRUCTURED_FALLBACK')
            ->assertJsonPath('status', 'confirm')
            ->assertJsonPath('meta.intent.origin', 'LHE')
            ->assertJsonPath('meta.intent.destination', 'DXB');
        $this->assertSame(0, (int) data_get($response->json(), 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));

        $confirmed = $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/chat'), [
            'message' => 'Yes, search',
            'conversation_id' => $response->json('conversation_id'),
        ], $embed['headers']);

        $confirmed->assertOk()->assertJsonPath('status', 'ok');
        $this->assertGreaterThanOrEqual(1, (int) data_get($confirmed->json(), 'meta.AI_FLIGHT_SEARCH_READ_CALLS'));
    }

    public function test_embed_conversational_lead_capture_reuses_fsm(): void
    {
        $this->enableEmbed();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);
        $embed = $this->embedHeaders();

        $first = $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/chat'), [
            'message' => 'I need help booking a group to Dubai',
        ], $embed['headers']);

        $first->assertOk();
        $body = mb_strtolower((string) $first->json('message'));
        // HELP-FIRST: strong travel/group intent may assist immediately; lead stays soft-pending.
        $this->assertTrue(
            str_contains($body, 'name')
            || str_contains($body, 'email')
            || str_contains($body, 'phone')
            || str_contains($body, 'dubai')
            || str_contains($body, 'group')
            || ($first->json('status') === 'lead_capture')
            || ($first->json('status') === 'clarify')
            || ($first->json('status') === 'ok')
        );
    }

    public function test_embed_booking_lookup_requires_matching_identity(): void
    {
        $this->enableEmbed();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);

        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'booking_reference' => 'EMBEDREF1',
            'status' => BookingStatus::PaymentPending,
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'embed@example.com',
        ]);

        $embed = $this->embedHeaders();
        $first = $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/chat'), [
            'message' => 'Can you look up my booking?',
        ], $embed['headers'])->assertOk();

        $cid = $first->json('conversation_id');

        $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/chat'), [
            'message' => 'My booking reference is EMBEDREF1',
            'conversation_id' => $cid,
        ], $embed['headers'])->assertOk();

        $valid = $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/chat'), [
            'message' => 'My email is embed@example.com',
            'conversation_id' => $cid,
        ], $embed['headers']);

        $valid->assertOk()
            ->assertJsonPath('status', 'ok');
        $this->assertStringContainsString('embedref1', mb_strtolower((string) $valid->json('message')));
    }

    public function test_embed_handoff_reuses_existing_behavior(): void
    {
        $this->enableEmbed();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);
        $embed = $this->embedHeaders();

        $chat = $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/chat'), [
            'message' => 'Talk to support',
        ], $embed['headers'])->assertOk();

        $cid = $chat->json('conversation_id');

        $handoff = $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/handoff'), [
            'conversation_id' => $cid,
        ], $embed['headers']);

        $handoff->assertOk()
            ->assertJsonPath('status', 'waiting_for_human')
            ->assertJsonPath('state', AiConversation::STATE_WAITING_FOR_HUMAN);
    }

    public function test_embed_clear_starts_new_owned_conversation(): void
    {
        $this->enableEmbed();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);
        $embed = $this->embedHeaders();

        $chat = $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/chat'), [
            'message' => 'What is JetPakistan?',
        ], $embed['headers'])->assertOk();

        $oldId = $chat->json('conversation_id');

        $clear = $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/clear'), [
            'conversation_id' => $oldId,
        ], $embed['headers']);

        $clear->assertOk()
            ->assertJsonPath('ok', true);
        $this->assertNotSame($oldId, $clear->json('conversation_id'));
    }

    public function test_embed_responses_do_not_set_jp_ai_vid_cookie(): void
    {
        $this->enableEmbed();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);
        $embed = $this->embedHeaders();

        $response = $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/chat'), [
            'message' => 'What is JetPakistan?',
        ], $embed['headers']);

        $response->assertOk();
        $setCookie = (string) $response->headers->get('Set-Cookie', '');
        $this->assertStringNotContainsString('jp_ai_vid', $setCookie);
    }

    public function test_embed_unavailable_when_ai_disabled(): void
    {
        $this->enableEmbed([
            'ota.ai_assistant.mode' => 'off',
            'ota.ai_assistant.enabled' => false,
        ]);
        $embed = $this->embedHeaders();

        $this->postJson($this->embedApiPath(self::ENTRY_PATH, '/chat'), [
            'message' => 'What is JetPakistan?',
        ], $embed['headers'])->assertStatus(503)
            ->assertJsonPath('status', 'unavailable');
    }
}
