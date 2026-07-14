<?php

namespace App\Services\Suppliers\Sabre\Diagnostics;

use App\Services\Suppliers\Sabre\Core\SabreEprEncodedCredentials;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Safe Sabre CERT/STL OAuth token probe — env-only credentials, no booking/cancel/ticketing.
 * Never logs or returns access tokens, Basic payloads, or raw secrets.
 */
final class SabreCertTokenProbe
{
    /**
     * @return array{
     *     profile: string,
     *     auth_host: string,
     *     auth_path: string,
     *     encoding_style: string,
     *     http_status: int,
     *     token_present: bool,
     *     token_type: string|null,
     *     expires_in: int|null,
     *     error_code: string|null,
     *     error_message: string|null,
     *     pcc_present: bool,
     *     domain_present: bool,
     * }
     */
    public function probe(
        string $profile,
        ?string $authUrlOverride = null,
        string $encodingStyle = SabreEprEncodedCredentials::ENCODING_SABRE_EPR_ENCODED_CURRENT,
    ): array {
        $profile = trim($profile);
        $encodingStyle = trim($encodingStyle);

        if (! SabreEprEncodedCredentials::isValidEncodingStyle($encodingStyle)) {
            return $this->baseResult(
                profile: $profile,
                authHost: '',
                authPath: '',
                encodingStyle: $encodingStyle !== '' ? $encodingStyle : 'unknown',
                httpStatus: 0,
                tokenPresent: false,
                pccPresent: false,
                domainPresent: false,
                errorCode: 'invalid_encoding_style',
                errorMessage: 'Encoding style is not supported.',
            );
        }

        $credentials = $this->resolveProfileCredentials($profile);

        if ($credentials === null) {
            return $this->baseResult(
                profile: $profile,
                authHost: '',
                authPath: '',
                encodingStyle: $encodingStyle,
                httpStatus: 0,
                tokenPresent: false,
                pccPresent: false,
                domainPresent: false,
                errorCode: 'unknown_profile',
                errorMessage: 'Credential profile is not configured.',
            );
        }

        $authUrl = is_string($authUrlOverride) && trim($authUrlOverride) !== ''
            ? trim($authUrlOverride)
            : (string) config('suppliers.sabre.cert_stl.auth_url', '');
        $authHost = $this->authHostFromUrl($authUrl);
        $authPath = $this->authPathFromUrl($authUrl);

        $user = trim((string) ($credentials['user'] ?? ''));
        $secret = trim((string) ($credentials['secret'] ?? ''));
        $pcc = trim((string) ($credentials['pcc'] ?? ''));
        $domain = trim((string) ($credentials['domain'] ?? ''));

        $pccPresent = $pcc !== '';
        $domainPresent = $domain !== '';

        if ($user === '' || $secret === '' || ! $pccPresent) {
            return $this->baseResult(
                profile: $profile,
                authHost: $authHost,
                authPath: $authPath,
                encodingStyle: $encodingStyle,
                httpStatus: 0,
                tokenPresent: false,
                pccPresent: $pccPresent,
                domainPresent: $domainPresent,
                errorCode: 'missing_credentials',
                errorMessage: 'Profile env is missing user, secret, or PCC.',
            );
        }

        if ($domain === '') {
            $domain = (string) config('suppliers.sabre.epr_domain_code', 'AA');
            $domainPresent = $domain !== '';
        }

        $basicPayload = SabreEprEncodedCredentials::basicAuthorizationPayloadForStyle(
            $encodingStyle,
            $user,
            $pcc,
            $secret,
            $domain,
        );

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Basic '.$basicPayload,
            ])
                ->asForm()
                ->timeout((int) config('suppliers.sabre.timeout_seconds', 30))
                ->connectTimeout((int) config('suppliers.sabre.connect_timeout_seconds', 10))
                ->post($authUrl, ['grant_type' => 'client_credentials']);
        } catch (ConnectionException) {
            return $this->baseResult(
                profile: $profile,
                authHost: $authHost,
                authPath: $authPath,
                encodingStyle: $encodingStyle,
                httpStatus: 0,
                tokenPresent: false,
                pccPresent: $pccPresent,
                domainPresent: $domainPresent,
                errorCode: 'connection_error',
                errorMessage: 'Token endpoint connection failed.',
            );
        } catch (Throwable) {
            return $this->baseResult(
                profile: $profile,
                authHost: $authHost,
                authPath: $authPath,
                encodingStyle: $encodingStyle,
                httpStatus: 0,
                tokenPresent: false,
                pccPresent: $pccPresent,
                domainPresent: $domainPresent,
                errorCode: 'request_failed',
                errorMessage: 'Token request failed.',
            );
        }

        $body = $response->json();
        $body = is_array($body) ? $body : [];

        $accessToken = $body['access_token'] ?? null;
        $tokenPresent = is_string($accessToken) && $accessToken !== '';

        $tokenType = isset($body['token_type']) && is_scalar($body['token_type'])
            ? $this->safeScalar((string) $body['token_type'], 40)
            : null;

        $expiresIn = isset($body['expires_in']) && is_numeric($body['expires_in'])
            ? (int) $body['expires_in']
            : null;

        [$errorCode, $errorMessage] = $this->extractOAuthError($body, $response->status(), $tokenPresent);

        return $this->baseResult(
            profile: $profile,
            authHost: $authHost,
            authPath: $authPath,
            encodingStyle: $encodingStyle,
            httpStatus: $response->status(),
            tokenPresent: $tokenPresent,
            pccPresent: $pccPresent,
            domainPresent: $domainPresent,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            tokenType: $tokenType,
            expiresIn: $expiresIn,
        );
    }

    /**
     * @return array{user?: string, secret?: string, pcc?: string, domain?: string}|null
     */
    public function resolveProfileCredentials(string $profile): ?array
    {
        $profiles = config('suppliers.sabre.cert_stl.profiles', []);

        if (! is_array($profiles)) {
            return null;
        }

        $entry = $profiles[$profile] ?? null;

        return is_array($entry) ? $entry : null;
    }

    /**
     * @return list<string>
     */
    public function configuredProfileKeys(): array
    {
        $profiles = config('suppliers.sabre.cert_stl.profiles', []);

        if (! is_array($profiles)) {
            return [];
        }

        return array_values(array_map('strval', array_keys($profiles)));
    }

    private function authHostFromUrl(string $authUrl): string
    {
        $host = parse_url($authUrl, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'unknown';
    }

    private function authPathFromUrl(string $authUrl): string
    {
        $path = parse_url($authUrl, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : 'unknown';
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: string|null, 1: string|null}
     */
    private function extractOAuthError(array $body, int $httpStatus, bool $tokenPresent): array
    {
        if ($tokenPresent) {
            return [null, null];
        }

        $code = null;
        $message = null;

        foreach (['error', 'code', 'errorCode'] as $key) {
            if (isset($body[$key]) && is_scalar($body[$key])) {
                $code = $this->safeScalar((string) $body[$key], 80);
                break;
            }
        }

        foreach (['error_description', 'message', 'errorMessage', 'description'] as $key) {
            if (isset($body[$key]) && is_scalar($body[$key])) {
                $message = $this->safeScalar((string) $body[$key], 240);
                break;
            }
        }

        if ($code === null && $httpStatus >= 400) {
            $code = 'http_'.$httpStatus;
        }

        if ($message === null && $httpStatus >= 400 && ! $tokenPresent) {
            $message = 'Token endpoint returned a non-success status.';
        }

        return [$code, $message];
    }

    /**
     * @return array{
     *     profile: string,
     *     auth_host: string,
     *     auth_path: string,
     *     encoding_style: string,
     *     http_status: int,
     *     token_present: bool,
     *     token_type: string|null,
     *     expires_in: int|null,
     *     error_code: string|null,
     *     error_message: string|null,
     *     pcc_present: bool,
     *     domain_present: bool,
     * }
     */
    private function baseResult(
        string $profile,
        string $authHost,
        string $authPath,
        string $encodingStyle,
        int $httpStatus,
        bool $tokenPresent,
        bool $pccPresent,
        bool $domainPresent,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?string $tokenType = null,
        ?int $expiresIn = null,
    ): array {
        return [
            'profile' => $profile,
            'auth_host' => $authHost !== '' ? $authHost : 'unknown',
            'auth_path' => $authPath !== '' ? $authPath : 'unknown',
            'encoding_style' => $encodingStyle !== '' ? $encodingStyle : 'unknown',
            'http_status' => $httpStatus,
            'token_present' => $tokenPresent,
            'token_type' => $tokenType,
            'expires_in' => $expiresIn,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'pcc_present' => $pccPresent,
            'domain_present' => $domainPresent,
        ];
    }

    private function safeScalar(string $value, int $max): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        if ($value === '') {
            return '';
        }

        if (strlen($value) > $max) {
            $value = substr($value, 0, $max);
        }

        return $value;
    }
}
