<?php

namespace Tests\Feature\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Services\Ai\NullInferenceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Ai\ScriptedInferenceProvider;
use Tests\TestCase;

/**
 * CQ28: when Qwen is down / disabled, CQ27 hybrid safety path remains functional.
 */
class QwenSemanticBrain28FallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
    }

    private function enableHybridOnly(): void
    {
        config([
            'ota.ai_assistant.mode' => 'public',
            'ota.ai_assistant.enabled' => true,
            'ota.ai_assistant.hard_allow.master' => true,
            'ota.ai_assistant.hard_allow.public' => true,
            'ota.ai_assistant.hard_allow.lab_adapter' => false,
            'ota.ai_assistant.hard_allow.human_handoff' => true,
            'ota.ai_assistant.flight_search_enabled' => true,
            'ota.ai_assistant.conversational_enabled' => false,
            'ota.ai_assistant.semantic_planner_enabled' => true,
            'ota.ai_assistant.optional_llm_assist' => false,
            'ota.ai_assistant.knowledge_enabled' => true,
            'ota.ai_assistant.human_handoff_enabled' => true,
            'ota.ai_assistant.anonymous_per_minute' => 60,
            'ai_lab.enabled' => false,
        ]);
        \App\Models\AiAssistantSetting::query()->delete();
        app(\App\Services\Ai\AiAssistantSettingsService::class)->get();
        $this->app->instance(InferenceProvider::class, new NullInferenceProvider);
    }

    public function test_qwen_unavailable_open_jaw_still_works(): void
    {
        $this->enableHybridOnly();
        $response = $this->withCookie('jp_ai_vid', str_repeat('f1', 20))
            ->postJson('/api/public/ai/chat', [
                'message' => 'I want to go from Lahore to Jeddah and then come back from Medina to Lahore',
            ]);
        $response->assertOk();
        $this->assertSame('YES', $response->json('meta.OPEN_JAW_DETECTED'));
        $this->assertSame(0, (int) $response->json('meta.AI_FLIGHT_SEARCH_READ_CALLS'));
    }

    public function test_unhealthy_provider_confirmation_still_works(): void
    {
        config([
            'ota.ai_assistant.mode' => 'public',
            'ota.ai_assistant.enabled' => true,
            'ota.ai_assistant.hard_allow.master' => true,
            'ota.ai_assistant.hard_allow.public' => true,
            'ota.ai_assistant.hard_allow.lab_adapter' => false,
            'ota.ai_assistant.flight_search_enabled' => true,
            'ota.ai_assistant.conversational_enabled' => true,
            'ota.ai_assistant.semantic_planner_enabled' => true,
            'ota.ai_assistant.anonymous_per_minute' => 60,
            'ai_lab.enabled' => false,
        ]);
        \App\Models\AiAssistantSetting::query()->delete();
        app(\App\Services\Ai\AiAssistantSettingsService::class)->get();
        $this->app->instance(InferenceProvider::class, new ScriptedInferenceProvider('{}', healthy: false));

        $response = $this->withCookie('jp_ai_vid', str_repeat('f2', 20))
            ->postJson('/api/public/ai/chat', [
                'message' => 'Lahore to Doha on 12 November 2026 for 1 adult',
            ]);
        $response->assertOk();
        $this->assertSame('confirm', $response->json('status'));
        $this->assertSame(0, (int) $response->json('meta.AI_FLIGHT_SEARCH_READ_CALLS'));
    }

    public function test_handoff_still_works_without_qwen(): void
    {
        $this->enableHybridOnly();
        $response = $this->withCookie('jp_ai_vid', str_repeat('f3', 20))
            ->postJson('/api/public/ai/chat', ['message' => 'Talk to support']);
        $response->assertOk();
        $this->assertSame('WAITING_FOR_HUMAN', $response->json('state'));
    }
}
