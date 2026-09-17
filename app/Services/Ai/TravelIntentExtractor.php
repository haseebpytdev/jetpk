<?php

namespace App\Services\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Data\Ai\HybridParseResult;
use App\Data\Ai\TravelIntent;
use App\Services\Ai\Hybrid\ConversationStatePatcher;
use App\Services\Ai\Hybrid\HybridTravelPipeline;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Extract TravelIntent via hybrid model-free pipeline (core).
 * Optional local LLM assist is never required for Flight/Group authority.
 */
final class TravelIntentExtractor
{
    public function __construct(
        private readonly InferenceProvider $provider,
        private readonly StructuredTravelIntentParser $structured,
        private readonly TravelIntentCanonicalizer $canonicalizer,
        private readonly HybridTravelPipeline $hybrid,
        private readonly ConversationStatePatcher $patcher,
    ) {}

    /**
     * @param  array<string, mixed>|null  $prior
     */
    public function extract(string $message, ?array $prior = null, bool $preferStructured = true): TravelIntent
    {
        return $this->extractHybrid($message, $prior)->intent;
    }

    /**
     * @param  array<string, mixed>|null  $prior
     */
    public function extractHybrid(string $message, ?array $prior = null): HybridParseResult
    {
        // Core path is always hybrid. LLM is never required for search authority.
        return $this->hybrid->parse($message, $prior, Carbon::now());
    }

    /**
     * @param  array<string, mixed>  $prior
     * @return array<string, mixed>
     */
    public function patchState(array $prior, TravelIntent $intent): array
    {
        return $this->patcher->merge($prior, $intent);
    }

    /**
     * Reserved optional assist — disabled unless config enables it. Never used for fare/tool authority.
     *
     * @param  array<string, mixed>|null  $prior
     * @return array<string, mixed>|null
     */
    public function tryOptionalModelAssist(string $message, ?array $prior): ?array
    {
        if (! (bool) config('ota.ai_assistant.optional_llm_assist', false)) {
            return null;
        }
        if (! $this->provider->isHealthy()) {
            return null;
        }

        // structured + canonicalizer retained for future optional merge experiments.
        $this->structured->parse($message, $prior);

        $system = $this->systemPrompt();
        $userPayload = json_encode([
            'message' => $message,
            'prior' => $prior,
            'today' => Carbon::now()->toDateString(),
            'instruction' => 'Emit JSON only. Prefer names/phrases over codes.',
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->provider->complete([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => (string) $userPayload],
        ], 180);

        if (! ($result['ok'] ?? false)) {
            return null;
        }

        $raw = $this->decodeJsonObject((string) ($result['content'] ?? ''));
        if ($raw === null) {
            return null;
        }

        unset($raw['tool'], $raw['tools'], $raw['function'], $raw['functions'], $raw['action']);

        return $raw;
    }

    private function systemPrompt(): string
    {
        $path = base_path('ai-assistant/prompts/travel-intent-system.txt');
        if (File::isFile($path)) {
            $text = trim((string) File::get($path));
            if ($text !== '') {
                return $text;
            }
        }

        return 'Return only a JSON object with travel intent fields. Prefer city names over IATA. Never name tools.';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(string $content): ?array
    {
        $content = trim($content);
        if ($content === '') {
            return null;
        }

        if (preg_match('/<\/think>/i', $content) === 1) {
            $parts = preg_split('/<\/think>/i', $content);
            $content = trim((string) end($parts));
        }

        if (preg_match('/\{[\s\S]*\}/', $content, $m) === 1) {
            $content = $m[0];
        }

        try {
            $decoded = json_decode($content, true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
