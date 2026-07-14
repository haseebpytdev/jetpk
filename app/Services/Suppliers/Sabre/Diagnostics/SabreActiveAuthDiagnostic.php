<?php

namespace App\Services\Suppliers\Sabre\Diagnostics;

use App\Enums\SupplierConnectionStatus;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreEprEncodedCredentials;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Safe Sabre active-connection auth diagnostic — metadata and optional token probe; never prints secrets.
 */
final class SabreActiveAuthDiagnostic
{
    private const LIVE_HOST = 'api.platform.sabre.com';

    /** @var list<string> */
    private const CERT_HOSTS = [
        'api.cert.platform.sabre.com',
        'api-crt.cert.havail.sabre.com',
        'stl.platform.sabre.com',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function diagnoseConnections(
        ?int $connectionId = null,
        bool $send = false,
        ?string $source = null,
        ?string $profile = null,
    ): array {
        if ($source === 'env-profile') {
            return [$this->diagnoseEnvProfile($profile ?? '', $send)];
        }

        $connections = $this->resolveConnections($connectionId);
        $results = [];

        foreach ($connections as $connection) {
            $results[] = $this->diagnoseDbConnection($connection, $send);
        }

        return $results;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function compareSources(int $connectionId, bool $send = false): array
    {
        $connection = SupplierConnection::query()->find($connectionId);
        if ($connection === null) {
            return [['error' => 'connection_not_found', 'connection_id' => $connectionId]];
        }

        $results = [$this->diagnoseDbConnection($connection, $send)];

        $probe = new SabreCertTokenProbe;
        foreach ($probe->configuredProfileKeys() as $profileKey) {
            $results[] = $this->diagnoseEnvProfile($profileKey, $send);
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnoseDbConnection(SupplierConnection $connection, bool $send = false): array
    {
        $cred = is_array($connection->credentials) ? $connection->credentials : [];
        $settings = is_array($connection->settings) ? $connection->settings : [];
        $meta = is_array($connection->meta) ? $connection->meta : [];

        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $baseContext = SabreInspectGate::resolveSabreBaseUrlContext($connection);
        $resolvedHost = (string) ($baseContext['resolved_base_host'] ?? 'unknown');
        $authUrl = $this->buildAuthUrl($connection, $tokenPath);
        $authHost = $this->hostFromUrl($authUrl);
        $authPath = $this->pathFromUrl($authUrl, $tokenPath);

        $clientId = trim((string) ($cred['client_id'] ?? ''));
        $clientSecret = trim((string) ($cred['client_secret'] ?? ''));
        $signIn = trim((string) ($cred['sign_in'] ?? ''));
        $password = trim((string) ($cred['password'] ?? ''));
        $pcc = trim((string) ($cred['pcc'] ?? ''));

        $explicitEpr = $signIn !== '' && $password !== '' && $pcc !== '';
        $oauthPair = $clientId !== '' && $clientSecret !== '';
        $resolvedStrategy = $this->resolvePlannedStrategy($explicitEpr, $oauthPair);

        $cacheKey = $this->tokenCacheKey($connection);
        $warnings = $this->buildWarnings($connection, $resolvedHost);

        $result = [
            'source' => 'db',
            'connection_id' => $connection->id,
            'connection_name' => (string) $connection->name,
            'provider' => $connection->provider->value,
            'environment' => $connection->environment->value,
            'status' => $connection->status->value,
            'is_active' => $connection->isActive(),
            'base_url_host' => $resolvedHost,
            'base_url_source' => (string) ($baseContext['resolved_source'] ?? 'unknown'),
            'auth_endpoint_host' => $authHost,
            'auth_endpoint_path' => $authPath,
            'credential_source' => $this->resolveDbCredentialSource($connection),
            'client_id_present' => $clientId !== '',
            'client_id_len' => strlen($clientId),
            'client_secret_present' => $clientSecret !== '',
            'client_secret_len' => strlen($clientSecret),
            'pcc_present' => $pcc !== '',
            'pcc_len' => strlen($pcc),
            'sign_in_present' => $signIn !== '',
            'sign_in_len' => strlen($signIn),
            'password_present' => $password !== '',
            'password_len' => strlen($password),
            'explicit_epr_triple' => $explicitEpr,
            'oauth_pair_present' => $oauthPair,
            'credential_keys_present' => $this->presentCredentialKeys($cred),
            'credential_keys_missing' => $this->missingCredentialKeys($cred),
            'settings_keys' => array_values(array_keys($settings)),
            'meta_keys' => array_values(array_keys($meta)),
            'auth_strategy_planned' => $resolvedStrategy,
            'authorization_scheme' => $resolvedStrategy === 'sabre_epr_encoded' ? 'Basic' : ($oauthPair ? 'Basic|form' : 'none'),
            'token_cache_key' => $cacheKey,
            'token_cache_present' => Cache::has($cacheKey),
            'token_cache_ttl_config' => 'expires_in-60',
            'resolved_host_class' => $this->classifyHost($resolvedHost),
            'forces_fresh_token_on_public_search' => ! Cache::has($cacheKey),
            'warnings' => $warnings,
            'http_status' => null,
            'oauth_error' => null,
            'oauth_error_description' => null,
            'token_obtained' => null,
        ];

        if ($send) {
            $probe = $this->sendTokenProbe($connection, $resolvedStrategy, $authUrl, $cred);
            $result['http_status'] = $probe['http_status'];
            $result['oauth_error'] = $probe['oauth_error'];
            $result['oauth_error_description'] = $probe['oauth_error_description'];
            $result['token_obtained'] = $probe['token_obtained'];
            $result['auth_strategy_used'] = $probe['auth_strategy_used'];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnoseEnvProfile(string $profile, bool $send = false): array
    {
        $probe = new SabreCertTokenProbe;
        $credentials = $probe->resolveProfileCredentials(trim($profile));

        $authUrl = (string) config('suppliers.sabre.cert_stl.auth_url', '');
        $authHost = $this->hostFromUrl($authUrl);
        $authPath = $this->pathFromUrl($authUrl, '/v2/auth/token');

        $user = trim((string) ($credentials['user'] ?? ''));
        $secret = trim((string) ($credentials['secret'] ?? ''));
        $pcc = trim((string) ($credentials['pcc'] ?? ''));

        $result = [
            'source' => 'env-profile',
            'profile' => trim($profile),
            'connection_id' => null,
            'connection_name' => null,
            'provider' => 'sabre',
            'environment' => 'cert_stl',
            'credential_source' => 'env',
            'auth_endpoint_host' => $authHost,
            'auth_endpoint_path' => $authPath,
            'client_id_present' => false,
            'client_id_len' => 0,
            'client_secret_present' => false,
            'client_secret_len' => 0,
            'pcc_present' => $pcc !== '',
            'pcc_len' => strlen($pcc),
            'sign_in_present' => $user !== '',
            'sign_in_len' => strlen($user),
            'password_present' => $secret !== '',
            'password_len' => strlen($secret),
            'explicit_epr_triple' => $user !== '' && $secret !== '' && $pcc !== '',
            'oauth_pair_present' => false,
            'auth_strategy_planned' => 'sabre_epr_encoded',
            'authorization_scheme' => 'Basic',
            'token_cache_key' => null,
            'token_cache_present' => false,
            'resolved_host_class' => $this->classifyHost($authHost),
            'warnings' => [],
            'http_status' => null,
            'oauth_error' => null,
            'oauth_error_description' => null,
            'token_obtained' => null,
        ];

        if ($send && $credentials !== null && $user !== '' && $secret !== '' && $pcc !== '') {
            $probeResult = $probe->probe(trim($profile));
            $result['http_status'] = $probeResult['http_status'] ?? 0;
            $result['oauth_error'] = $probeResult['error_code'] ?? null;
            $result['oauth_error_description'] = $probeResult['error_message'] ?? null;
            $result['token_obtained'] = (bool) ($probeResult['token_present'] ?? false);
            $result['auth_strategy_used'] = 'sabre_epr_encoded';
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    public function formatLines(array $result): array
    {
        $lines = [];
        foreach ($result as $key => $value) {
            if ($key === 'warnings' && is_array($value)) {
                foreach ($value as $warning) {
                    $lines[] = 'warning='.(string) $warning;
                }

                continue;
            }
            if (is_bool($value)) {
                $lines[] = $key.'='.($value ? 'true' : 'false');
            } elseif ($value === null) {
                $lines[] = $key.'=';
            } elseif (is_array($value)) {
                $lines[] = $key.'='.implode(',', array_map('strval', $value));
            } else {
                $lines[] = $key.'='.(string) $value;
            }
        }

        return $lines;
    }

    /**
     * @return list<SupplierConnection>
     */
    private function resolveConnections(?int $connectionId): array
    {
        if ($connectionId !== null) {
            $connection = SupplierConnection::query()->find($connectionId);

            return $connection !== null ? [$connection] : [];
        }

        return SupplierConnection::query()
            ->where('provider', 'sabre')
            ->where(function ($query): void {
                $query->where('is_active', true)
                    ->orWhere('status', SupplierConnectionStatus::Active->value);
            })
            ->orderBy('id')
            ->get()
            ->all();
    }

    private function tokenCacheKey(SupplierConnection $connection): string
    {
        return 'sabre:token:connection:'.$connection->id;
    }

    private function buildAuthUrl(SupplierConnection $connection, string $tokenPath): string
    {
        $base = rtrim((string) ($connection->base_url ?: config('suppliers.sabre.default_base_url')), '/');

        return $base.$tokenPath;
    }

    private function hostFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'unknown';
    }

    private function pathFromUrl(string $url, string $fallback): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : $fallback;
    }

    private function resolvePlannedStrategy(bool $explicitEpr, bool $oauthPair): string
    {
        if ($explicitEpr) {
            return 'sabre_epr_encoded';
        }

        if ($oauthPair) {
            return 'basic';
        }

        return 'sabre_epr_encoded';
    }

    /**
     * @param  array<string, mixed>  $cred
     * @return list<string>
     */
    private function presentCredentialKeys(array $cred): array
    {
        $keys = ['client_id', 'client_secret', 'pcc', 'sign_in', 'password', 'token'];
        $present = [];
        foreach ($keys as $key) {
            if (trim((string) ($cred[$key] ?? '')) !== '') {
                $present[] = $key;
            }
        }

        return $present;
    }

    /**
     * @param  array<string, mixed>  $cred
     * @return list<string>
     */
    private function missingCredentialKeys(array $cred): array
    {
        $keys = ['client_id', 'client_secret', 'pcc', 'sign_in', 'password'];
        $missing = [];
        foreach ($keys as $key) {
            if (trim((string) ($cred[$key] ?? '')) === '') {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    private function resolveDbCredentialSource(SupplierConnection $connection): string
    {
        $hasDb = is_array($connection->credentials) && $connection->credentials !== [];
        $hasEnvFallback = trim((string) config('suppliers.sabre.default_base_url')) !== ''
          && trim((string) ($connection->base_url ?? '')) === '';

        if ($hasDb && $hasEnvFallback) {
            return 'mixed';
        }

        if ($hasDb) {
            return 'db';
        }

        return 'config';
    }

    /**
     * @return list<string>
     */
    private function buildWarnings(SupplierConnection $connection, string $resolvedHost): array
    {
        $warnings = [];
        $env = $connection->environment->value;

        if ($env === 'live' && in_array($resolvedHost, self::CERT_HOSTS, true)) {
            $warnings[] = 'environment_live_but_base_url_is_cert_host';
        }

        if ($env === 'sandbox' && $resolvedHost === self::LIVE_HOST) {
            $warnings[] = 'environment_sandbox_but_base_url_is_live_host';
        }

        return $warnings;
    }

    private function classifyHost(string $host): string
    {
        if ($host === self::LIVE_HOST) {
            return 'live';
        }

        if (in_array($host, self::CERT_HOSTS, true)) {
            return 'cert';
        }

        return 'unknown';
    }

    /**
     * @param  array<string, mixed>  $cred
     * @return array{
     *     http_status: int,
     *     oauth_error: ?string,
     *     oauth_error_description: ?string,
     *     token_obtained: bool,
     *     auth_strategy_used: string
     * }
     */
    private function sendTokenProbe(
        SupplierConnection $connection,
        string $plannedStrategy,
        string $authUrl,
        array $cred,
    ): array {
        $clientId = trim((string) ($cred['client_id'] ?? ''));
        $clientSecret = trim((string) ($cred['client_secret'] ?? ''));
        $signIn = trim((string) ($cred['sign_in'] ?? ''));
        $password = trim((string) ($cred['password'] ?? ''));
        $pcc = trim((string) ($cred['pcc'] ?? ''));
        $explicitEpr = $signIn !== '' && $password !== '' && $pcc !== '';

        try {
            if ($plannedStrategy === 'sabre_epr_encoded' && $explicitEpr) {
                $domain = (string) config('suppliers.sabre.epr_domain_code', 'AA');
                $payload = SabreEprEncodedCredentials::basicAuthorizationPayload($signIn, $pcc, $password, $domain);
                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => 'Basic '.$payload,
                ])
                    ->asForm()
                    ->timeout((int) config('suppliers.sabre.timeout_seconds', 30))
                    ->connectTimeout((int) config('suppliers.sabre.connect_timeout_seconds', 10))
                    ->post($authUrl, ['grant_type' => 'client_credentials']);

                return $this->probeResultFromResponse($response->status(), $response->json(), 'sabre_epr_encoded');
            }

            if ($clientId !== '' && $clientSecret !== '') {
                $response = Http::withHeaders(['Accept' => 'application/json'])
                    ->withBasicAuth($clientId, $clientSecret)
                    ->asForm()
                    ->timeout((int) config('suppliers.sabre.timeout_seconds', 30))
                    ->connectTimeout((int) config('suppliers.sabre.connect_timeout_seconds', 10))
                    ->post($authUrl, ['grant_type' => 'client_credentials']);

                if (in_array($response->status(), [400, 401], true) && $explicitEpr) {
                    $domain = (string) config('suppliers.sabre.epr_domain_code', 'AA');
                    $payload = SabreEprEncodedCredentials::basicAuthorizationPayload($signIn, $pcc, $password, $domain);
                    $eprResponse = Http::withHeaders([
                        'Accept' => 'application/json',
                        'Authorization' => 'Basic '.$payload,
                    ])
                        ->asForm()
                        ->timeout((int) config('suppliers.sabre.timeout_seconds', 30))
                        ->connectTimeout((int) config('suppliers.sabre.connect_timeout_seconds', 10))
                        ->post($authUrl, ['grant_type' => 'client_credentials']);

                    return $this->probeResultFromResponse($eprResponse->status(), $eprResponse->json(), 'sabre_epr_encoded');
                }

                return $this->probeResultFromResponse($response->status(), $response->json(), 'basic');
            }
        } catch (ConnectionException) {
            return [
                'http_status' => 0,
                'oauth_error' => 'connection_error',
                'oauth_error_description' => 'Token endpoint connection failed.',
                'token_obtained' => false,
                'auth_strategy_used' => $plannedStrategy,
            ];
        }

        return [
            'http_status' => 0,
            'oauth_error' => 'missing_credentials',
            'oauth_error_description' => 'No usable credentials for probe.',
            'token_obtained' => false,
            'auth_strategy_used' => $plannedStrategy,
        ];
    }

    /**
     * @return array{
     *     http_status: int,
     *     oauth_error: ?string,
     *     oauth_error_description: ?string,
     *     token_obtained: bool,
     *     auth_strategy_used: string
     * }
     */
    private function probeResultFromResponse(int $status, mixed $json, string $strategy): array
    {
        $body = is_array($json) ? $json : [];
        $token = $body['access_token'] ?? null;
        $tokenObtained = is_string($token) && $token !== '';

        return [
            'http_status' => $status,
            'oauth_error' => isset($body['error']) ? substr((string) $body['error'], 0, 80) : null,
            'oauth_error_description' => isset($body['error_description'])
              ? substr((string) $body['error_description'], 0, 200)
              : null,
            'token_obtained' => $tokenObtained,
            'auth_strategy_used' => $strategy,
        ];
    }
}
