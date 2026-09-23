<?php

namespace App\Services\Ai\Embed;

use App\Models\AiEmbedAuditEvent;
use App\Models\AiEmbedTenant;

final class EmbedAuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(?AiEmbedTenant $tenant, string $eventType, bool $success = true, array $context = []): void
    {
        $safe = $this->sanitizeContext($context);

        AiEmbedAuditEvent::query()->create([
            'tenant_public_id' => $tenant?->public_id,
            'event_type' => $eventType,
            'success' => $success,
            'context' => $safe === [] ? null : $safe,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $blocked = [
            'token', 'session_token', 'embed_key', 'raw_key', 'password', 'email', 'phone',
            'message', 'conversation', 'pnr', 'passport',
        ];

        $out = [];
        foreach ($context as $key => $value) {
            if (in_array(strtolower((string) $key), $blocked, true)) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $out[(string) $key] = $value;
            }
        }

        return $out;
    }
}
