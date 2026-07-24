<?php

namespace App\Services\Suppliers\OneApi\Normalization;

/**
 * HMAC-signed supplier offer tokens (non-secret itinerary fingerprint).
 */
class OneApiOfferTokenSigner
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
            throw new \InvalidArgumentException('Invalid offer token format.');
        }

        $json = base64_decode($parts[0], true);
        if ($json === false) {
            throw new \InvalidArgumentException('Invalid offer token payload.');
        }

        $expected = hash_hmac('sha256', $json, $this->signingKey());
        $provided = $parts[1];
        if (strlen($expected) !== strlen($provided) || ! hash_equals($expected, $provided)) {
            throw new \InvalidArgumentException('Offer token signature mismatch.');
        }

        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw new \InvalidArgumentException('Invalid offer token JSON.');
        }

        $expiresAt = (int) ($data['expires_at'] ?? 0);
        if ($expiresAt > 0 && $expiresAt < time()) {
            throw new \InvalidArgumentException('Offer token expired.');
        }

        return $data;
    }

    private function signingKey(): string
    {
        return hash('sha256', (string) config('app.key').'|one_api_offer');
    }
}
