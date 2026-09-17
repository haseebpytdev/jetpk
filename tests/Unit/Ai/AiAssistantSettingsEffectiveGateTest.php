<?php

namespace Tests\Unit\Ai;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\Ai\AiAssistantSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAssistantSettingsEffectiveGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_triple_and_gate_for_lab_adapter(): void
    {
        config([
            'ota.ai_assistant.hard_allow.master' => true,
            'ai_lab.hard_allow.lab_adapter' => true,
        ]);

        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);
        $service = app(AiAssistantSettingsService::class);

        $service->update($admin, ['master_enabled' => true, 'lab_adapter_enabled' => true]);
        $this->assertTrue($service->effective()['lab_adapter_enabled']);

        $service->update($admin, ['lab_adapter_enabled' => false]);
        $this->assertFalse($service->effective()['lab_adapter_enabled']);

        config(['ai_lab.hard_allow.lab_adapter' => false]);
        $service->update($admin, ['lab_adapter_enabled' => true]);
        $this->assertFalse($service->effective()['lab_adapter_enabled']);
    }

    public function test_hard_locked_writes_are_not_persistable(): void
    {
        $locks = app(AiAssistantSettingsService::class)->hardLockedWriteCapabilities();
        $this->assertNotEmpty($locks);
        foreach ($locks as $cap) {
            $this->assertTrue($cap['locked']);
            $this->assertFalse($cap['activatable']);
        }
    }
}
