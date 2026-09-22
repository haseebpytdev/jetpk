<?php

namespace Tests\Unit\Ai;

use App\Models\AiConversation;
use App\Enums\CustomerQueryStatus;
use App\Models\CustomerQuery;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Ai\AiCommercialIntentClassifier;
use App\Services\Ai\CustomerQueryLeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerQueryLeadServiceTest extends TestCase
{
    use RefreshDatabase;

    private CustomerQueryLeadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CustomerQueryLeadService(new AiCommercialIntentClassifier);
    }

    public function test_commercial_intent_requires_lead_capture_for_guest(): void
    {
        $conversation = AiConversation::query()->create([
            'channel' => 'web',
            'visitor_token_hash' => hash('sha256', 'visitor-1'),
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => [],
        ]);

        $this->assertTrue($this->service->needsLeadCapture($conversation, 'I need a flight Lahore to Jeddah'));
        $this->assertFalse($this->service->needsLeadCapture($conversation, 'What is JetPakistan'));
    }

    public function test_lead_payload_validation_and_creation(): void
    {
        $conversation = AiConversation::query()->create([
            'channel' => 'web',
            'visitor_token_hash' => hash('sha256', 'visitor-2'),
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => ['lead_pending_message' => 'Lahore to Jeddah'],
        ]);

        $invalid = $this->service->createFromPayload($conversation, [
            'name' => 'A',
            'email' => 'bad',
            'phone' => '',
            'contact_consent' => false,
        ], $conversation->visitor_token_hash);

        $this->assertFalse($invalid['ok']);

        $valid = $this->service->createFromPayload($conversation, [
            'name' => 'علی خان',
            'email' => 'Ali@Example.com',
            'phone' => '03001234567',
            'contact_consent' => true,
        ], $conversation->visitor_token_hash);

        $this->assertTrue($valid['ok']);
        $this->assertDatabaseHas('customer_queries', [
            'email' => 'ali@example.com',
            'contact_consent' => true,
            'source' => 'ask_jetpakistan',
        ]);
        $this->assertInstanceOf(CustomerQuery::class, $valid['query']);
    }

    public function test_authenticated_user_with_profile_requires_consent_only(): void
    {
        $user = User::factory()->create([
            'name' => 'QA Admin',
            'email' => 'qa-admin@jetpakistan.pk',
        ]);
        UserProfile::query()->create([
            'user_id' => $user->id,
            'phone' => '03001234567',
        ]);

        $conversation = AiConversation::query()->create([
            'channel' => 'web',
            'visitor_token_hash' => hash('sha256', 'visitor-auth-1'),
            'user_id' => $user->id,
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => [],
        ]);

        $fields = $this->service->requiredLeadFields($user);
        $this->assertSame(['contact_consent'], $fields);

        $prompt = $this->service->leadCapturePromptPayload($conversation, 'Find flights Lahore to Dubai', $user);
        $this->assertIsArray($prompt);
        $this->assertSame('ok', $prompt['status']);
        $this->assertTrue($prompt['meta']['lead_capture_pending']);
        $this->assertStringContainsString('contact you', mb_strtolower((string) $prompt['message']));
    }

    public function test_authenticated_user_missing_phone_requires_phone_and_consent(): void
    {
        $user = User::factory()->create([
            'name' => 'QA Admin',
            'email' => 'qa-admin@jetpakistan.pk',
        ]);

        $fields = $this->service->requiredLeadFields($user);
        $this->assertContains('phone', $fields);
        $this->assertContains('contact_consent', $fields);
        $this->assertNotContains('name', $fields);
        $this->assertNotContains('email', $fields);
    }

    public function test_authenticated_consent_only_submission_merges_profile_contact(): void
    {
        $user = User::factory()->create([
            'name' => 'QA Admin',
            'email' => 'qa-admin@jetpakistan.pk',
        ]);
        UserProfile::query()->create([
            'user_id' => $user->id,
            'phone' => '03001234567',
        ]);

        $conversation = AiConversation::query()->create([
            'channel' => 'web',
            'visitor_token_hash' => hash('sha256', 'visitor-auth-2'),
            'user_id' => $user->id,
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => ['lead_pending_message' => 'Lahore to Dubai'],
        ]);

        $valid = $this->service->createFromPayload(
            $conversation,
            ['contact_consent' => true],
            $conversation->visitor_token_hash,
            $user,
        );

        $this->assertTrue($valid['ok']);
        $this->assertDatabaseHas('customer_queries', [
            'user_id' => $user->id,
            'email' => 'qa-admin@jetpakistan.pk',
            'contact_consent' => true,
            'consent_source' => 'ask_jetpakistan',
        ]);
    }

    public function test_recent_consented_query_skips_lead_capture(): void
    {
        $user = User::factory()->create([
            'name' => 'QA Admin',
            'email' => 'qa-admin@jetpakistan.pk',
        ]);
        UserProfile::query()->create([
            'user_id' => $user->id,
            'phone' => '03001234567',
        ]);

        $conversation = AiConversation::query()->create([
            'channel' => 'web',
            'visitor_token_hash' => hash('sha256', 'visitor-auth-3'),
            'user_id' => $user->id,
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => [],
        ]);

        CustomerQuery::query()->create([
            'visitor_token_hash' => $conversation->visitor_token_hash,
            'user_id' => $user->id,
            'ai_conversation_id' => $conversation->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_raw' => '03001234567',
            'contact_consent' => true,
            'consent_timestamp' => now(),
            'consent_source' => CustomerQueryLeadService::CONSENT_SOURCE,
            'source' => CustomerQueryLeadService::CONSENT_SOURCE,
            'status' => CustomerQueryStatus::New,
            'last_activity_at' => now(),
        ]);

        $this->assertFalse($this->service->needsLeadCapture($conversation, 'Find flights Lahore to Dubai', $user));
    }

    public function test_uat_harness_synthetic_lead_name_is_validator_accepted(): void
    {
        $conversation = AiConversation::query()->create([
            'channel' => 'web',
            'visitor_token_hash' => hash('sha256', 'visitor-uat-name'),
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => [],
        ]);

        $valid = $this->service->createFromPayload($conversation, [
            'name' => 'UAT Lead QA',
            'email' => 'uat-lead-qa@jetpakistan.pk',
            'phone' => '03001234567',
            'contact_consent' => true,
        ], $conversation->visitor_token_hash);

        $this->assertTrue($valid['ok']);
    }
}
