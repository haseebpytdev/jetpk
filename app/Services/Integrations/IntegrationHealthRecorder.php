<?php

namespace App\Services\Integrations;

use App\Enums\IntegrationHealthStatus;
use App\Models\IntegrationHealthCheck;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Persists sanitized integration health/test results (never secrets or PII).
 */
final class IntegrationHealthRecorder
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(
        string $provider,
        string $testType,
        IntegrationHealthStatus $status,
        ?User $actor = null,
        ?int $latencyMs = null,
        ?int $httpStatus = null,
        ?string $environment = null,
        ?string $errorCode = null,
        ?string $message = null,
        array $meta = [],
    ): IntegrationHealthCheck {
        return IntegrationHealthCheck::query()->create([
            'provider' => $provider,
            'test_type' => $testType,
            'status' => $status,
            'latency_ms' => $latencyMs,
            'http_status' => $httpStatus,
            'environment' => $environment,
            'tested_at' => now(),
            'tested_by' => $actor?->id,
            'sanitized_error_code' => $this->sanitizeCode($errorCode),
            'sanitized_message' => $this->sanitizeMessage($message),
            'meta' => $this->sanitizeMeta($meta),
        ]);
    }

    public function latest(string $provider, ?string $testType = null): ?IntegrationHealthCheck
    {
        $query = IntegrationHealthCheck::query()
            ->where('provider', $provider)
            ->orderByDesc('tested_at')
            ->orderByDesc('id');

        if ($testType !== null) {
            $query->where('test_type', $testType);
        }

        return $query->first();
    }

    /**
     * @return Collection<int, IntegrationHealthCheck>
     */
    public function history(string $provider, int $limit = 20): Collection
    {
        return IntegrationHealthCheck::query()
            ->where('provider', $provider)
            ->orderByDesc('tested_at')
            ->orderByDesc('id')
            ->limit(max(1, min(100, $limit)))
            ->get();
    }

    private function sanitizeCode(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return substr(preg_replace('/[^a-zA-Z0-9._-]/', '', $code) ?? '', 0, 80) ?: null;
    }

    private function sanitizeMessage(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return null;
        }

        $clean = preg_replace('/(Authorization|Bearer|secret|password|token|merchant_secret)[^\s]*/i', '[redacted]', $message) ?? '';
        $clean = preg_replace('/[A-Za-z0-9+\/]{24,}={0,2}/', '[redacted]', $clean) ?? '';

        return substr(trim($clean), 0, 500) ?: null;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function sanitizeMeta(array $meta): array
    {
        $blocked = ['authorization', 'secret', 'password', 'token', 'merchant_secret_key', 'merchant_secret', 'api_key'];
        $out = [];
        foreach ($meta as $key => $value) {
            $lk = strtolower((string) $key);
            foreach ($blocked as $needle) {
                if (str_contains($lk, $needle)) {
                    continue 2;
                }
            }
            if (is_scalar($value) || $value === null) {
                $out[(string) $key] = is_string($value) ? substr($value, 0, 200) : $value;
            }
        }

        return $out;
    }
}
