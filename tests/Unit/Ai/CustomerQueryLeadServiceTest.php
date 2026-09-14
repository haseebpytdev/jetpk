<?php

namespace Tests\Unit\Ai;

use App\Models\AiConversation;
use App\Models\CustomerQuery;
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
}
