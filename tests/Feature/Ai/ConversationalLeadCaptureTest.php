<?php

namespace Tests\Feature\Ai;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\CustomerQuery;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Ai\Lab\LearningEventRedactor;
use App\Services\Ai\NullInferenceProvider;
use App\Contracts\Ai\InferenceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConversationalLeadCaptureTest extends TestCase
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
    private function enablePublicAi(array $extra = []): void
    {
        config(array_merge([
            'ota.ai_assistant.mode' => 'public',
            'ota.ai_assistant.enabled' => true,
            'ota.ai_assistant.hard_allow.master' => true,
            'ota.ai_assistant.hard_allow.public' => true,
            'ota.ai_assistant.flight_search_enabled' => true,
            'ota.ai_assistant.conversational_enabled' => false,
            'ota.ai_assistant.optional_llm_assist' => false,
            'ota.ai_assistant.knowledge_enabled' => true,
        ], $extra));

        \App\Models\AiAssistantSetting::query()->delete();
        app(\App\Services\Ai\AiAssistantSettingsService::class)->get();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);
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

    public function test_vague_help_asks_for_name(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('1', 40);

        $turn = $this->chat($vid, 'I need help');
        $turn['response']->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('meta.lead_capture_pending', true);
        $this->assertStringContainsString('may i start with your name', mb_strtolower((string) $turn['response']->json('message')));
    }

    public function test_name_response_asks_for_contact(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('2', 40);

        $first = $this->chat($vid, 'I need help');
        $cid = $first['conversation_id'];

        $second = $this->chat($vid, 'Haseeb Asif', $cid);
        $second['response']->assertOk()
            ->assertJsonPath('meta.lead_capture_pending', true);
        $message = mb_strtolower((string) $second['response']->json('message'));
        $this->assertStringContainsString('email', $message);
        $this->assertStringContainsString('contact', $message);
    }

    public function test_email_and_phone_parsed_from_same_message(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('3', 40);
        $cid = $this->chat($vid, 'I need help')['conversation_id'];
        $this->chat($vid, 'Haseeb Asif', $cid);

        $contact = $this->chat($vid, '03333333333, x@gmail.com', $cid);
        $contact['response']->assertOk()
            ->assertJsonPath('meta.lead_capture_pending', true);
        $this->assertStringContainsString('contact you', mb_strtolower((string) $contact['response']->json('message')));

        $conversation = AiConversation::query()->where('public_id', $cid)->first();
        $state = $conversation->shopping_state;
        $this->assertSame('x@gmail.com', $state['lead_email']);
        $this->assertSame('03333333333', $state['lead_phone']);
    }

    public function test_phone_and_email_reversed_order_parsed(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('4', 40);
        $cid = $this->chat($vid, 'I need help')['conversation_id'];
        $this->chat($vid, 'Haseeb Asif', $cid);

        $contact = $this->chat($vid, 'x@gmail.com, 03333333333', $cid);
        $contact['response']->assertOk();

        $conversation = AiConversation::query()->where('public_id', $cid)->first();
        $state = $conversation->shopping_state;
        $this->assertSame('x@gmail.com', $state['lead_email']);
        $this->assertSame('03333333333', $state['lead_phone']);
    }

    public function test_only_email_asks_only_phone(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('5', 40);
        $cid = $this->chat($vid, 'I need help')['conversation_id'];
        $this->chat($vid, 'Haseeb Asif', $cid);

        $partial = $this->chat($vid, 'x@gmail.com', $cid);
        $message = mb_strtolower((string) $partial['response']->json('message'));
        $this->assertStringContainsString('contact number', $message);
        $this->assertStringNotContainsString('email address and contact number', $message);
    }

    public function test_only_phone_asks_only_email(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('6', 40);
        $cid = $this->chat($vid, 'I need help')['conversation_id'];
        $this->chat($vid, 'Haseeb Asif', $cid);

        $partial = $this->chat($vid, '03333333333', $cid);
        $message = mb_strtolower((string) $partial['response']->json('message'));
        $this->assertStringContainsString('email', $message);
        $this->assertStringNotContainsString('email address and contact number', $message);
    }

    public function test_invalid_email_gets_conversational_correction(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('7', 40);
        $cid = $this->chat($vid, 'I need help')['conversation_id'];
        $this->chat($vid, 'Haseeb Asif', $cid);

        $invalid = $this->chat($vid, 'not-an-email', $cid);
        $message = mb_strtolower((string) $invalid['response']->json('message'));
        $this->assertTrue(
            str_contains($message, 'email')
                || str_contains($message, 'contact number')
        );
    }

    public function test_invalid_phone_gets_conversational_correction(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('8', 40);
        $cid = $this->chat($vid, 'I need help')['conversation_id'];
        $this->chat($vid, 'Haseeb Asif', $cid);

        $invalid = $this->chat($vid, 'x@gmail.com and 12', $cid);
        $message = mb_strtolower((string) $invalid['response']->json('message'));
        $this->assertStringContainsString('contact number', $message);
    }

    public function test_yes_sure_recognized_as_affirmative_consent(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('a', 40);
        $cid = $this->chat($vid, 'I need help')['conversation_id'];
        $this->chat($vid, 'Haseeb Asif', $cid);
        $this->chat($vid, 'lead@example.com 03001234567', $cid);

        $consent = $this->chat($vid, 'Yes sure', $cid);
        $consent['response']->assertOk();
        $this->assertDatabaseCount('customer_queries', 1);
    }

    public function test_affirmative_consent_creates_customer_query_once(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('9', 40);
        $cid = $this->completeLeadFlow($vid, 'I need help');

        $this->assertDatabaseCount('customer_queries', 1);
        $this->assertDatabaseHas('customer_queries', [
            'email' => 'lead@example.com',
            'contact_consent' => true,
            'consent_source' => 'ask_jetpakistan',
        ]);
    }

    public function test_negative_consent_does_not_create_consented_query(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('a', 40);
        $cid = $this->chat($vid, 'I need help')['conversation_id'];
        $this->chat($vid, 'Haseeb Asif', $cid);
        $this->chat($vid, 'lead@example.com 03001234567', $cid);

        $decline = $this->chat($vid, 'no thanks', $cid);
        $decline['response']->assertOk()
            ->assertJsonPath('meta.lead_capture_pending', false);
        $this->assertStringContainsString("won't save", mb_strtolower((string) $decline['response']->json('message')));
        $this->assertDatabaseCount('customer_queries', 0);
    }

    public function test_pending_flight_request_resumes_after_consent(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('b', 40);

        $first = $this->chat($vid, "I'm looking for a Lahore to Dubai flight");
        $cid = $first['conversation_id'];
        $this->chat($vid, 'Haseeb Asif', $cid);
        $this->chat($vid, 'lead@example.com 03001234567', $cid);

        $consent = $this->chat($vid, 'yes', $cid);
        $consent['response']->assertOk();
        $this->assertNotEmpty($consent['response']->json('recommendations'));
        $this->assertSame('LHE', data_get($consent['response']->json(), 'meta.intent.origin'));
        $this->assertSame('DXB', data_get($consent['response']->json(), 'meta.intent.destination'));
    }

    public function test_intake_prompts_and_replies_stored_as_normal_messages(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('c', 40);
        $cid = $this->chat($vid, 'I need help')['conversation_id'];
        $this->chat($vid, 'Haseeb Asif', $cid);
        $this->chat($vid, 'lead@example.com 03001234567', $cid);
        $this->chat($vid, 'yes', $cid);

        $conversation = AiConversation::query()->where('public_id', $cid)->first();
        $messages = AiMessage::query()
            ->where('ai_conversation_id', $conversation->id)
            ->orderBy('id')
            ->get();

        $this->assertGreaterThanOrEqual(8, $messages->count());
        $this->assertSame('user', $messages[0]->role);
        $this->assertSame('assistant', $messages[1]->role);
        $this->assertSame('Haseeb Asif', $messages[2]->body);
    }

    public function test_authenticated_known_fields_not_re_requested(): void
    {
        $this->enablePublicAi();
        $user = User::factory()->create([
            'name' => 'QA Admin',
            'email' => 'qa-admin@jetpakistan.pk',
        ]);
        UserProfile::query()->create([
            'user_id' => $user->id,
            'phone' => '03001234567',
        ]);

        $vid = str_repeat('d', 40);
        $first = $this->actingAs($user)
            ->withCookie('jp_ai_vid', $vid)
            ->postJson('/api/public/ai/chat', ['message' => 'Find flights Lahore to Dubai']);
        $cid = (string) $first->json('conversation_id');

        $message = mb_strtolower((string) $first->json('message'));
        $this->assertStringContainsString('contact you', $message);
        $this->assertStringNotContainsString('may i start with your name', $message);
        $this->assertStringNotContainsString('email address and contact number', $message);
    }

    public function test_authenticated_missing_phone_asks_only_phone_then_consent(): void
    {
        $this->enablePublicAi();
        $user = User::factory()->create([
            'name' => 'QA Admin',
            'email' => 'qa-admin@jetpakistan.pk',
        ]);

        $vid = str_repeat('e', 40);
        $first = $this->actingAs($user)
            ->withCookie('jp_ai_vid', $vid)
            ->postJson('/api/public/ai/chat', ['message' => 'Find flights Lahore to Dubai']);
        $cid = (string) $first->json('conversation_id');

        $message = mb_strtolower((string) $first->json('message'));
        $this->assertStringContainsString('contact number', $message);
        $this->assertStringNotContainsString('may i start with your name', $message);
    }

    public function test_what_is_jetpakistan_returns_useful_information_without_lead_gate(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('f', 40);

        $response = $this->chat($vid, 'What is JetPakistan?');
        $response['response']->assertOk();
        $this->assertNotTrue($response['response']->json('meta.lead_capture_pending'));
        $body = mb_strtolower((string) $response['response']->json('message'));
        $this->assertTrue(
            str_contains($body, 'jetpakistan')
                || str_contains($body, 'travel')
                || str_contains($body, 'flight')
        );
        $this->assertStringNotContainsString('what should i call you', $body);
        $this->assertStringNotContainsString('share your name', $body);
    }

    public function test_simple_hi_gets_greeting_without_forced_pii(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('g', 40);

        $response = $this->chat($vid, 'Hi');
        $response['response']->assertOk();
        $this->assertNotTrue($response['response']->json('meta.lead_capture_pending'));
        $body = mb_strtolower((string) $response['response']->json('message'));
        $this->assertStringNotContainsString('what should i call you', $body);
    }

    public function test_learning_telemetry_redacts_lead_pii(): void
    {
        $redactor = new LearningEventRedactor;
        $event = $redactor->redact([
            'user_text' => 'My email is lead@example.com and phone 03001234567',
        ]);

        $this->assertTrue($redactor->assertRedacted($event));
        $this->assertStringNotContainsString('lead@example.com', (string) $event['redacted_user_text']);
        $this->assertStringNotContainsString('03001234567', (string) $event['redacted_user_text']);
    }

    public function test_duplicate_customer_query_not_created_on_repeated_consent(): void
    {
        $this->enablePublicAi();
        $vid = str_repeat('h', 40);
        $cid = $this->completeLeadFlow($vid, 'I need help');

        $again = $this->chat($vid, 'Find flights Lahore to Dubai', $cid);
        $again['response']->assertOk();
        $this->assertDatabaseCount('customer_queries', 1);
    }

    private function completeLeadFlow(string $visitorId, string $openingMessage): string
    {
        $cid = $this->chat($visitorId, $openingMessage)['conversation_id'];
        $this->chat($visitorId, 'Haseeb Asif', $cid);
        $this->chat($visitorId, 'lead@example.com 03001234567', $cid);
        $this->chat($visitorId, 'yes', $cid);

        return $cid;
    }
}
