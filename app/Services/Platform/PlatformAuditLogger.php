<?php

namespace App\Services\Platform;

use App\Models\DeveloperUser;
use App\Models\PlatformAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Append-only Dev CP / platform-owner audit logger.
 */
class PlatformAuditLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        string $action,
        ?Model $subject = null,
        ?DeveloperUser $developer = null,
        ?int $agencyId = null,
        ?Request $request = null,
        array $properties = [],
    ): void {
        try {
            PlatformAuditLog::query()->create([
                'action' => $action,
                'subject_type' => $subject !== null ? $subject->getMorphClass() : null,
                'subject_id' => $subject?->getKey(),
                'developer_user_id' => $developer?->id,
                'agency_id' => $agencyId,
                'properties' => $this->sanitizeProperties($properties),
                'ip_address' => $request?->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('PlatformAuditLogger failed safely.', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function sanitizeProperties(array $properties): array
    {
        $blocked = ['password', 'token', 'secret', 'credential', 'api_key'];

        $sanitized = [];
        foreach ($properties as $key => $value) {
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
                $sanitized[$key] = $this->sanitizeProperties($value);
            }
        }

        return $sanitized;
    }
}
