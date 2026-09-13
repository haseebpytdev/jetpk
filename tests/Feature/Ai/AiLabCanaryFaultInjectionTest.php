<?php

namespace Tests\Feature\Ai;

use App\Models\User;
use App\Services\Ai\Lab\AiLabFaultInjectionContext;
use App\Services\Ai\Lab\AiLabGatewayUrlResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiLabCanaryFaultInjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthorized_fault_header_does_not_set_mode(): void
    {
        config([
            'ota.ai_assistant.enabled' => true,
            'ota.ai_assistant.mode' => 'internal_canary',
            'ai_lab.canary_only' => true,
            'ai_lab.canary_fault_token' => 'secret-token',
        ]);

        $this->postJson('/api/public/ai/chat', ['message' => 'hello'], [
            'X-JP-AI-Canary-Fault-Mode' => 'SIMULATE_GATEWAY_DOWN',
            'X-JP-AI-Canary-Fault-Token' => 'wrong-token',
        ]);

        $this->assertSame(AiLabFaultInjectionContext::MODE_NORMAL, AiLabFaultInjectionContext::mode());
    }

    public function test_empty_fault_token_disables_http_injection(): void
    {
        config([
            'ota.ai_assistant.enabled' => true,
            'ota.ai_assistant.mode' => 'internal_canary',
            'ai_lab.canary_only' => true,
            'ai_lab.canary_fault_token' => '',
        ]);

        $this->postJson('/api/public/ai/chat', ['message' => 'hello'], [
            'X-JP-AI-Canary-Fault-Mode' => 'SIMULATE_GATEWAY_DOWN',
            'X-JP-AI-Canary-Fault-Token' => 'anything',
        ]);

        $this->assertSame(AiLabFaultInjectionContext::MODE_NORMAL, AiLabFaultInjectionContext::mode());
    }

    public function test_gateway_down_mode_resolves_unreachable_port_only_in_scope(): void
    {
        config(['ai_lab.gateway_url' => 'http://127.0.0.1:8765']);
        $resolver = app(AiLabGatewayUrlResolver::class);

        $this->assertSame('http://127.0.0.1:8765', $resolver->resolve());

        AiLabFaultInjectionContext::using(
            AiLabFaultInjectionContext::MODE_SIMULATE_GATEWAY_DOWN,
            function () use ($resolver): void {
                $this->assertSame('http://127.0.0.1:1', $resolver->resolve());
            }
        );

        $this->assertSame(AiLabFaultInjectionContext::MODE_NORMAL, AiLabFaultInjectionContext::mode());
        $this->assertSame('http://127.0.0.1:8765', $resolver->resolve());
    }

    public function test_canary_admin_fault_header_clears_after_request(): void
    {
        config([
            'ota.ai_assistant.enabled' => true,
            'ota.ai_assistant.mode' => 'internal_canary',
            'ai_lab.canary_only' => true,
            'ai_lab.canary_fault_token' => 'secret-token',
        ]);

        $admin = User::factory()->create([
            'email' => 'jp-dash-03-qa-admin@jetpakistan.pk',
            'account_type' => 'platform_admin',
        ]);

        $this->actingAs($admin)->postJson('/api/public/ai/chat', ['message' => 'hello'], [
            'X-JP-AI-Canary-Fault-Mode' => 'SIMULATE_GATEWAY_DOWN',
            'X-JP-AI-Canary-Fault-Token' => 'secret-token',
        ]);

        $this->assertSame(AiLabFaultInjectionContext::MODE_NORMAL, AiLabFaultInjectionContext::mode());
    }
}
