<?php

namespace Tests\Feature\Ai;

use App\Enums\AccountType;
use App\Models\AiAssistantSetting;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Ai\AiAssistantSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAssistantSettingsAdminTest extends TestCase
{
    use RefreshDatabase;

    private function platformAdmin(): User
    {
        return User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
        ]);
    }

    private function enableHardAllows(): void
    {
        config([
            'ota.ai_assistant.hard_allow.master' => true,
            'ota.ai_assistant.hard_allow.internal_canary' => true,
            'ota.ai_assistant.hard_allow.human_handoff' => true,
            'ota.ai_assistant.hard_allow.flight_search_read_only' => true,
            'ota.ai_assistant.hard_allow.public' => false,
            'ai_lab.hard_allow.lab_adapter' => true,
            'ai_lab.hard_allow.rag' => true,
            'ai_lab.hard_allow.learning_queue' => true,
        ]);
    }

    public function test_platform_admin_can_view_settings_page(): void
    {
        $this->enableHardAllows();
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get('/admin/settings/ai-assistant')
            ->assertOk()
            ->assertSee('Ask JetPakistan')
            ->assertSee('Safe controls');
    }

    public function test_non_admin_cannot_update_settings(): void
    {
        $this->enableHardAllows();
        $customer = User::factory()->create(['account_type' => AccountType::Customer]);

        $this->actingAs($customer)
            ->patch('/admin/settings/ai-assistant', ['master_enabled' => true])
            ->assertForbidden();
    }

    public function test_admin_toggle_persists_and_audits(): void
    {
        $this->enableHardAllows();
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->patch('/admin/settings/ai-assistant', [
                'master_enabled' => true,
                'lab_adapter_enabled' => true,
                'internal_canary_enabled' => true,
                'rag_enabled' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $settings = app(AiAssistantSettingsService::class)->get();
        $this->assertTrue($settings->master_enabled);
        $this->assertTrue($settings->lab_adapter_enabled);
        $this->assertTrue($settings->internal_canary_enabled);
        $this->assertTrue($settings->rag_enabled);

        $this->assertGreaterThan(0, AuditLog::query()
            ->where('action', 'ai_assistant.settings_updated')
            ->count());

        $this->actingAs($admin)
            ->get('/admin/settings/ai-assistant')
            ->assertOk()
            ->assertSee('Configured:')
            ->assertSee('ON');
    }

    public function test_effective_respects_env_hard_ceiling(): void
    {
        config([
            'ota.ai_assistant.hard_allow.master' => false,
            'ota.ai_assistant.hard_allow.internal_canary' => false,
        ]);

        $admin = $this->platformAdmin();
        app(AiAssistantSettingsService::class)->update($admin, [
            'master_enabled' => true,
            'internal_canary_enabled' => true,
        ]);

        $effective = app(AiAssistantSettingsService::class)->effective();
        $this->assertFalse($effective['master_enabled']);
        $this->assertSame('off', $effective['audience_mode']);
    }

    public function test_admin_can_disable_toggle_after_enable(): void
    {
        $this->enableHardAllows();
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->patch('/admin/settings/ai-assistant', ['master_enabled' => true, 'rag_enabled' => true])
            ->assertRedirect();

        $settings = app(AiAssistantSettingsService::class)->get();
        $this->assertTrue($settings->master_enabled);
        $this->assertTrue($settings->rag_enabled);

        $this->actingAs($admin)
            ->patch('/admin/settings/ai-assistant', [])
            ->assertRedirect();

        $settings->refresh();
        $this->assertFalse($settings->master_enabled);
        $this->assertFalse($settings->rag_enabled);
        $this->assertFalse(app(AiAssistantSettingsService::class)->effective()['rag_enabled']);
    }

    public function test_public_rollout_rejected_without_hard_allow(): void
    {
        $this->enableHardAllows();
        config(['ota.ai_assistant.hard_allow.public' => false]);
        $admin = $this->platformAdmin();

        $this->expectException(\InvalidArgumentException::class);
        app(AiAssistantSettingsService::class)->update($admin, [
            'audience_mode' => AiAssistantSetting::AUDIENCE_PUBLIC,
        ]);
    }

    public function test_public_rollout_allowed_when_hard_allow(): void
    {
        $this->enableHardAllows();
        config(['ota.ai_assistant.hard_allow.public' => true]);
        $admin = $this->platformAdmin();

        app(AiAssistantSettingsService::class)->update($admin, [
            'master_enabled' => true,
            'audience_mode' => AiAssistantSetting::AUDIENCE_PUBLIC,
        ]);

        $settings = app(AiAssistantSettingsService::class)->get();
        $this->assertSame(AiAssistantSetting::AUDIENCE_PUBLIC, $settings->audience_mode);
        $effective = app(AiAssistantSettingsService::class)->effective();
        $this->assertSame(AiAssistantSetting::AUDIENCE_PUBLIC, $effective['audience_mode']);
    }
}
