<?php

namespace App\Services\Suppliers\AlHaider;

use Illuminate\Http\Client\Response;

/**
 * Derives Al-Haider token expiry from supplier response metadata or business contract.
 */
final class AlHaiderTokenExpiryResolver
{
    public function resolveExpiresAt(Response $response, int $issuedAt): int
    {
        $decoded = $response->json();
        if (! is_array($decoded)) {
            return $this->businessDefaultExpiresAt($issuedAt);
        }

        foreach (['expires_at', 'expiry', 'valid_until'] as $key) {
            $value = $decoded[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $parsed = strtotime(trim($value));
                if ($parsed !== false && $parsed > $issuedAt) {
                    return $parsed;
                }
            }
            if (is_numeric($value) && (int) $value > $issuedAt) {
                return (int) $value;
            }
        }

        $expiresIn = $decoded['expires_in'] ?? $decoded['expiresIn'] ?? null;
        if (is_numeric($expiresIn) && (int) $expiresIn > 0) {
            return $issuedAt + (int) $expiresIn;
        }

        $token = trim((string) ($decoded['token'] ?? ''));
        if ($token !== '' && substr_count($token, '.') === 2) {
            $jwtExp = $this->jwtExpiry($token);
            if ($jwtExp > $issuedAt) {
                return $jwtExp;
            }
        }

        return $this->businessDefaultExpiresAt($issuedAt);
    }

    public function businessDefaultExpiresAt(int $issuedAt): int
    {
        $validitySeconds = max(
            3600,
            (int) config('suppliers.al_haider.token_validity_seconds', 31_536_000),
        );

        return $issuedAt + $validitySeconds;
    }

    private function jwtExpiry(string $jwt): int
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return 0;
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')) ?: '', true);
        if (! is_array($payload)) {
            return 0;
        }

        $exp = (int) ($payload['exp'] ?? 0);

        return $exp > 0 ? $exp : 0;
    }
}
