<?php

namespace Tests\Feature\Ai;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\Ai\AiAssistantEligibility;
use App\Support\Staff\StaffPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAssistantEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_off_denies_everyone(): void
    {
        config(['ota.ai_assistant.mode' => 'off']);
        $e = app(AiAssistantEligibility::class);
        $this->assertFalse($e->isEligible(null));
        $this->assertSame('off', $e->mode());
    }

    public function test_internal_canary_allows_support_staff_and_platform_admin(): void
    {
        config(['ota.ai_assistant.mode' => 'internal_canary']);
        $e = app(AiAssistantEligibility::class);

        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'meta' => ['staff_permissions' => [StaffPermission::SupportView]],
        ]);
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
        ]);
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
        ]);

        $this->assertTrue($e->isEligible($staff));
        $this->assertTrue($e->isEligible($admin));
        $this->assertFalse($e->isEligible($customer));
        $this->assertFalse($e->isEligible(null));
    }

    public function test_chat_denied_for_anonymous_in_canary_mode(): void
    {
        config(['ota.ai_assistant.mode' => 'internal_canary', 'ota.ai_assistant.enabled' => false]);
        $this->postJson('/api/public/ai/chat', ['message' => 'LHE to DXB tomorrow'])
            ->assertStatus(503)
            ->assertJsonPath('status', 'unavailable');
    }
}
