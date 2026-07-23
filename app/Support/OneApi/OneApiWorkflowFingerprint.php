<?php

namespace App\Support\OneApi;

final class OneApiWorkflowFingerprint
{
    /**
     * @param  array<string, mixed>  $signedOfferPayload
     */
    public static function signedOffer(array $signedOfferPayload): string
    {
        return hash('sha256', json_encode(self::normalize($signedOfferPayload), JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $passengerProfile
     */
    public static function passengerProfile(array $passengerProfile): string
    {
        return hash('sha256', json_encode(self::normalize($passengerProfile), JSON_THROW_ON_ERROR));
    }

    public static function session(string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalize(array $data): array
    {
        ksort($data);

        return $data;
    }
}
