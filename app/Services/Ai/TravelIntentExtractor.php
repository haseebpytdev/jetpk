<?php

namespace App\Services\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Data\Ai\TravelIntent;
use Illuminate\Support\Facades\File;

/**
 * Extract TravelIntent: structured parser is authoritative for routes;
 * healthy local model may fill gaps only. Application owns tools.
 */
final class TravelIntentExtractor
{
    public function __construct(
        private readonly InferenceProvider $provider,
        private readonly StructuredTravelIntentParser $structured,
    ) {}

    /**
     * @param  array<string, mixed>|null  $prior
     */
    public function extract(string $message, ?array $prior = null, bool $preferStructured = false): TravelIntent
    {
        $structured = $this->structured->parse($message, $prior);

        if ($preferStructured || ! $this->provider->isHealthy()) {
            return $structured;
        }

        $fromModel = $this->tryModelJson($message, $prior);
        if ($fromModel === null) {
            return $structured;
        }

        $merged = $structured->toArray();
        $modelArr = $fromModel->toArray();
        foreach (['origin', 'destination', 'depart_date', 'return_date', 'airline', 'max_stops', 'budget', 'time_preference', 'cabin'] as $key) {
            if (($merged[$key] ?? null) === null && ($modelArr[$key] ?? null) !== null) {
                $merged[$key] = $modelArr[$key];
            }
        }
        if (($merged['intent'] ?? 'unknown') === 'unknown' && ($modelArr['intent'] ?? 'unknown') !== 'unknown') {
            $merged['intent'] = $modelArr['intent'];
        }
        if ($structured->adults === 1 && (int) ($modelArr['adults'] ?? 1) > 1 && preg_match('/\d+\s*adult/i', $message) === 1) {
            $merged['adults'] = (int) $modelArr['adults'];
        }

        // Structured owns the mode when it already understood the route.
        $mode = $structured->isSearchable() ? 'STRUCTURED_FALLBACK' : 'AI_FULL';

        return TravelIntent::fromArray($merged, $mode);
    }

    /**
     * @param  array<string, mixed>|null  $prior
     */
    private function tryModelJson(string $message, ?array $prior): ?TravelIntent
    {
        $system = $this->systemPrompt();
        $userPayload = json_encode([
            'message' => $message,
            'prior' => $prior,
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->provider->complete([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => (string) $userPayload],
        ], 220);

        if (! ($result['ok'] ?? false)) {
            return null;
        }

        $raw = $this->decodeJsonObject((string) ($result['content'] ?? ''));
        if ($raw === null) {
            return null;
        }

        unset($raw['tool'], $raw['tools'], $raw['function'], $raw['functions'], $raw['action']);

        try {
            return TravelIntent::fromArray($raw, 'AI_FULL');
        } catch (\Throwable) {
            return null;
        }
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

        return 'Return only a JSON object with travel intent fields. Never name tools or functions.';
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
