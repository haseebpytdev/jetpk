<?php

namespace App\Services\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Data\Ai\TravelIntent;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Extract TravelIntent: structured parser first; local model fills gaps only.
 * Application canonicalizes locations/airlines/dates/budgets and owns tools.
 */
final class TravelIntentExtractor
{
    public function __construct(
        private readonly InferenceProvider $provider,
        private readonly StructuredTravelIntentParser $structured,
        private readonly TravelIntentCanonicalizer $canonicalizer,
    ) {}

    /**
     * @param  array<string, mixed>|null  $prior
     */
    public function extract(string $message, ?array $prior = null, bool $preferStructured = false): TravelIntent
    {
        $structured = $this->structured->parse($message, $prior);

        if ($preferStructured || ! $this->provider->isHealthy()) {
            return $this->applyCanonical($structured->toArray(), $prior, 'STRUCTURED_FALLBACK');
        }

        $fromModel = $this->tryModelJson($message, $prior);
        if ($fromModel === null) {
            return $this->applyCanonical($structured->toArray(), $prior, 'STRUCTURED_FALLBACK');
        }

        $merged = $structured->toArray();
        $modelArr = $fromModel;
        foreach ([
            'origin', 'destination', 'origin_text', 'destination_text',
            'depart_date', 'return_date', 'depart_date_text', 'return_date_text',
            'airline', 'airline_name', 'max_stops', 'budget', 'budget_text',
            'time_preference', 'cabin', 'depart_date_delta_days', 'clarification_required',
            'adults', 'children', 'infants',
        ] as $key) {
            if (! array_key_exists($key, $modelArr) || $modelArr[$key] === null || $modelArr[$key] === '') {
                continue;
            }
            // Structured owns resolved route when searchable.
            if (in_array($key, ['origin', 'destination'], true) && $structured->isSearchable()) {
                continue;
            }
            if (($merged[$key] ?? null) === null || in_array($key, [
                'origin_text', 'destination_text', 'depart_date_text', 'return_date_text',
                'airline_name', 'budget_text', 'depart_date_delta_days', 'clarification_required',
            ], true)) {
                $merged[$key] = $modelArr[$key];
            }
        }
        if (($merged['intent'] ?? 'unknown') === 'unknown' && ($modelArr['intent'] ?? 'unknown') !== 'unknown') {
            $merged['intent'] = $modelArr['intent'];
        }
        if ($structured->adults === 1 && (int) ($modelArr['adults'] ?? 1) > 1) {
            $merged['adults'] = (int) $modelArr['adults'];
        }

        $mode = $structured->isSearchable() ? 'STRUCTURED_FALLBACK' : 'AI_FULL';

        return $this->applyCanonical($merged, $prior, $mode);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>|null  $prior
     */
    private function applyCanonical(array $raw, ?array $prior, string $mode): TravelIntent
    {
        $canon = $this->canonicalizer->canonicalize($raw, $prior, Carbon::now());
        $payload = $canon['intent'];
        if ($canon['clarification_required'] && in_array($payload['intent'] ?? '', ['flight_search', 'group_search'], true)
            && (empty($payload['origin']) || empty($payload['destination']))) {
            $payload['intent'] = 'unknown';
        }

        return TravelIntent::fromArray($payload, $mode);
    }

    /**
     * @param  array<string, mixed>|null  $prior
     * @return array<string, mixed>|null
     */
    private function tryModelJson(string $message, ?array $prior): ?array
    {
        $system = $this->systemPrompt();
        $userPayload = json_encode([
            'message' => $message,
            'prior' => $prior,
            'today' => Carbon::now()->toDateString(),
            'instruction' => 'Emit JSON only. Prefer names/phrases over codes. Use depart_date_delta_days for relative follow-ups.',
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

        // Drop Qwen thinking blocks if present.
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
