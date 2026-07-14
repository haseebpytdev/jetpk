<?php

namespace App\Services\Security;

use App\Models\SecurityEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Safe, append-only security event recorder (never stores passwords/tokens).
 */
class SecurityEventLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $eventType,
        string $outcome,
        ?Model $actor = null,
        ?int $agencyId = null,
        ?Request $request = null,
        array $metadata = [],
    ): void {
        try {
            SecurityEvent::query()->create([
                'event_type' => $eventType,
                'outcome' => $outcome,
                'actor_type' => $actor !== null ? $actor->getMorphClass() : null,
                'actor_id' => $actor?->getKey(),
                'agency_id' => $agencyId,
                'ip_address' => $request?->ip(),
                'user_agent' => $request !== null ? substr((string) $request->userAgent(), 0, 250) : null,
                'metadata' => $this->sanitizeMetadata($metadata),
            ]);
        } catch (\Throwable $e) {
            Log::warning('SecurityEventLogger failed safely.', [
                'event_type' => $eventType,
                'outcome' => $outcome,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $metadata): array
    {
        $blocked = ['password', 'token', 'secret', 'credential', 'api_key', 'authorization'];

        $sanitized = [];
        foreach ($metadata as $key => $value) {
            $lowerKey = strtolower((string) $key);
            $blockedKey = false;
            foreach ($blocked as $needle) {
                if (str_contains($lowerKey, $needle)) {
                    $blockedKey = true;
                    break;
                }
            }
            if ($blockedKey) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $sanitized[$key] = $value;
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeMetadata($value);
            }
        }

        return $sanitized;
    }
}
