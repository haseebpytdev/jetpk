<?php

namespace App\Services\Suppliers\AlHaider;

/**
 * Durable Al-Haider bearer token metadata (never logged or exposed to clients).
 */
final class AlHaiderTokenRecord
{
    public function __construct(
        public readonly string $token,
        public readonly int $issuedAt,
        public readonly int $expiresAt,
        public readonly string $source = 'login',
        public readonly ?int $invalidatedAt = null,
    ) {}

    public function isValid(int $now, int $marginSeconds): bool
    {
        if ($this->invalidatedAt !== null) {
            return false;
        }

        if ($this->token === '') {
            return false;
        }

        return $this->expiresAt > ($now + max(0, $marginSeconds));
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'issued_at' => $this->issuedAt,
            'expires_at' => $this->expiresAt,
            'source' => $this->source,
            'invalidated_at' => $this->invalidatedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): ?self
    {
        $token = trim((string) ($payload['token'] ?? ''));
        if ($token === '') {
            return null;
        }

        $issuedAt = (int) ($payload['issued_at'] ?? 0);
        $expiresAt = (int) ($payload['expires_at'] ?? 0);
        if ($issuedAt <= 0 || $expiresAt <= 0) {
            return null;
        }

        $invalidatedAt = isset($payload['invalidated_at']) ? (int) $payload['invalidated_at'] : null;

        return new self(
            token: $token,
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
            source: trim((string) ($payload['source'] ?? 'login')) ?: 'login',
            invalidatedAt: $invalidatedAt > 0 ? $invalidatedAt : null,
        );
    }

    public function invalidated(int $at): self
    {
        return new self(
            token: $this->token,
            issuedAt: $this->issuedAt,
            expiresAt: $this->expiresAt,
            source: $this->source,
            invalidatedAt: $at,
        );
    }
}
