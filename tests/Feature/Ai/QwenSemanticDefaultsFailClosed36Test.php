<?php

namespace Tests\Feature\Ai;

use App\Services\Ai\Semantic\QwenSemanticPlanner;
use App\Services\Ai\Semantic\SemanticResponseComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CQ28-36: semantic planner/composer must be fail-closed by default.
 */
class QwenSemanticDefaultsFailClosed36Test extends TestCase
{
    use RefreshDatabase;

    public function test_semantic_planner_and_composer_default_off(): void
    {
        // Simulate unset env: config defaults must be false.
        config([
            'ota.ai_assistant.conversational_enabled' => true,
            'ota.ai_assistant.semantic_planner_enabled' => (bool) filter_var(
                env('OTA_AI_SEMANTIC_PLANNER_ENABLED', false),
                FILTER_VALIDATE_BOOL
            ),
            'ota.ai_assistant.semantic_composer_enabled' => (bool) filter_var(
                env('OTA_AI_SEMANTIC_COMPOSER_ENABLED', false),
                FILTER_VALIDATE_BOOL
            ),
        ]);

        // When env vars are not set in testing, defaults are false.
        $this->assertFalse((bool) config('ota.ai_assistant.semantic_planner_enabled'));
        $this->assertFalse((bool) config('ota.ai_assistant.semantic_composer_enabled'));

        $planner = app(QwenSemanticPlanner::class);
        $composer = app(SemanticResponseComposer::class);
        $this->assertFalse($planner->isEnabled());
        $this->assertFalse($composer->isEnabled());
    }

    public function test_config_file_defaults_are_false(): void
    {
        $ota = include config_path('ota.php');
        $assistant = $ota['ai_assistant'] ?? [];
        // Re-evaluate defaults as written: env(..., false)
        $this->assertArrayHasKey('semantic_planner_enabled', $assistant);
        $this->assertArrayHasKey('semantic_composer_enabled', $assistant);
        $this->assertFalse((bool) config('ota.ai_assistant.semantic_planner_enabled', false));
        $this->assertFalse((bool) config('ota.ai_assistant.semantic_composer_enabled', false));
    }
}
