<?php

namespace App\Services\Suppliers\EjmiralAerost;

/**
 * HMAC-signed opaque AEROST offer context (NLD/acceptance never exposed for client tampering).
 */
class EjmiralAerostOfferTokenSigner
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function sign(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $json, $this->signingKey());

        return base64_encode($json).'|'.$signature;
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $token): array
    {
        $parts = explode('|', $token, 2);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Invalid AEROST offer token format.');
        }

        $json = base64_decode($parts[0], true);
        if ($json === false) {
            throw new \InvalidArgumentException('Invalid AEROST offer token payload.');
        }

        $expected = hash_hmac('sha256', $json, $this->signingKey());
        if (! hash_equals($expected, $parts[1])) {
            throw new \InvalidArgumentException('AEROST offer token signature mismatch.');
        }

        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw new \InvalidArgumentException('Invalid AEROST offer token JSON.');
        }

        $expiresAt = (int) ($data['expires_at'] ?? 0);
        if ($expiresAt > 0 && $expiresAt < time()) {
            throw new \InvalidArgumentException('AEROST offer token expired.');
        }

        return $data;
    }

    private function signingKey(): string
    {
        return hash('sha256', (string) config('app.key').'|ejmiral_aerost_offer');
    }
}
