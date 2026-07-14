<?php

namespace App\Services\Suppliers\Sabre\Core;

/**
 * Sabre REST V2 OAuth — encoded Basic credential (EPR + PCC), per Sabre guidance:
 * Base64(userId) : Base64(password) → Base64 combined, sent as Authorization: Basic …
 * userId format: V1:{EPR}:{PCC}:{domain}.
 *
 * @see https://stackoverflow.com/questions/77706339/how-to-encode-correctly-for-sabre-oauth-api
 */
final class SabreEprEncodedCredentials
{
    public const ENCODING_SABRE_EPR_ENCODED_CURRENT = 'sabre_epr_encoded_current';

    public const ENCODING_RAW_BASIC = 'raw_basic';

    public const ENCODING_ENCODED_EPR_RAW_SECRET = 'encoded_epr_raw_secret';

    public const ENCODING_RAW_EPR_ENCODED_SECRET = 'raw_epr_encoded_secret';

    /**
     * @return list<string>
     */
    public static function encodingStyles(): array
    {
        return [
            self::ENCODING_SABRE_EPR_ENCODED_CURRENT,
            self::ENCODING_RAW_BASIC,
            self::ENCODING_ENCODED_EPR_RAW_SECRET,
            self::ENCODING_RAW_EPR_ENCODED_SECRET,
        ];
    }

    public static function isValidEncodingStyle(string $style): bool
    {
        return in_array($style, self::encodingStyles(), true);
    }

    /**
     * Returns the credential string placed after "Basic " (never log this value).
     */
    public static function basicAuthorizationPayload(string $epr, string $pcc, string $password, string $domainCode = 'AA'): string
    {
        return self::basicAuthorizationPayloadForStyle(
            self::ENCODING_SABRE_EPR_ENCODED_CURRENT,
            $epr,
            $pcc,
            $password,
            $domainCode,
        );
    }

    /**
     * Returns the credential string placed after "Basic " (never log this value).
     */
    public static function basicAuthorizationPayloadForStyle(
        string $style,
        string $epr,
        string $pcc,
        string $password,
        string $domainCode = 'AA',
    ): string {
        $userId = 'V1:'.$epr.':'.$pcc.':'.$domainCode;

        return match ($style) {
            self::ENCODING_SABRE_EPR_ENCODED_CURRENT => base64_encode(
                base64_encode($userId).':'.base64_encode($password)
            ),
            self::ENCODING_RAW_BASIC => base64_encode($userId.':'.$password),
            self::ENCODING_ENCODED_EPR_RAW_SECRET => base64_encode(
                base64_encode($userId).':'.$password
            ),
            self::ENCODING_RAW_EPR_ENCODED_SECRET => base64_encode(
                $userId.':'.base64_encode($password)
            ),
            default => throw new \InvalidArgumentException('Unknown Sabre auth encoding style.'),
        };
    }
}
