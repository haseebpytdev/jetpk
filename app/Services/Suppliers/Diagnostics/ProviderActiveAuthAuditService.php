<?php

namespace App\Services\Suppliers\Diagnostics;

use App\Enums\SupplierConnectionStatus;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AlHaider\AlHaiderClient;
use App\Services\Suppliers\Sabre\Diagnostics\SabreActiveAuthDiagnostic;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only provider auth audit — safe metadata for Al-Haider (env) and Sabre (DB connections).
 */
final class ProviderActiveAuthAuditService
{
    public function __construct(
        private readonly AlHaiderClient $alHaiderClient,
        private readonly SabreActiveAuthDiagnostic $sabreDiagnostic,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function audit(
        string $provider,
        ?int $connectionId = null,
        bool $send = false,
    ): array {
        $sections = [];

        if (in_array($provider, ['all', 'alhaider'], true)) {
            $sections[] = $this->auditAlHaider($send);
        }

        if (in_array($provider, ['all', 'sabre'], true)) {
            foreach ($this->sabreDiagnostic->diagnoseConnections($connectionId, $send) as $result) {
                $sections[] = $result;
            }
        }

        return $sections;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditAlHaider(bool $send = false): array
    {
        $username = trim((string) config('suppliers.al_haider.username'));
        $password = trim((string) config('suppliers.al_haider.password'));
        $staticToken = trim((string) config('suppliers.al_haider.token'));
        $baseUrl = rtrim((string) config('suppliers.al_haider.default_base_url'), '/');
        $loginPath = (string) config('suppliers.al_haider.login_path', '/api/login');
        $tokenCacheKey = 'alhaider:auth_token';
        $limitBlockKey = 'alhaider:auth_token:limit_blocked';

        $credentialSource = 'config';
        if ($username !== '' || $password !== '') {
            $credentialSource = 'env';
        }
        if ($staticToken !== '') {
            $credentialSource = 'env';
        }

        $result = [
            'provider' => 'alhaider',
            'source' => $credentialSource,
            'enabled' => (bool) config('suppliers.al_haider.enabled'),
            'configured' => $this->alHaiderClient->isConfigured(),
            'db_connection_rows' => 0,
            'credential_source' => $credentialSource,
            'username_present' => $username !== '',
            'username_len' => strlen($username),
            'password_present' => $password !== '',
            'password_len' => strlen($password),
            'static_token_configured' => $staticToken !== '',
            'static_token_len' => $staticToken !== '' ? strlen($staticToken) : 0,
            'base_url_host' => $this->hostFromUrl($baseUrl),
            'auth_endpoint_path' => $loginPath,
            'auth_strategy' => $staticToken !== '' ? 'static_env_token' : 'dynamic_login',
            'token_cache_key' => $tokenCacheKey,
            'token_cache_present' => Cache::has($tokenCacheKey),
            'token_limit_blocked' => Cache::has($limitBlockKey),
            'token_cache_ttl_seconds' => max(60, (int) config('suppliers.al_haider.token_cache_ttl_seconds', 82800)),
            'login_lock_seconds' => (int) config('suppliers.al_haider.login_lock_seconds', 15),
            'forces_fresh_token_on_public_search' => false,
            'forces_fresh_data_on_public_search' => true,
            'http_status' => null,
            'reason_code' => null,
            'token_obtained' => null,
        ];

        if ($send) {
            $probe = $this->alHaiderClient->probeAuthentication();
            $result['http_status'] = $probe['http_status'];
            $result['reason_code'] = $probe['reason_code'];
            $result['token_obtained'] = $probe['token_obtained'];
        }

        return $result;
    }

    /**
     * @return list<SupplierConnection>
     */
    public function activeSabreConnections(?int $connectionId = null): array
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

    /**
     * @return list<string>
     */
    public function formatLines(array $section): array
    {
        if (($section['provider'] ?? '') === 'sabre' || ($section['source'] ?? '') === 'db') {
            return $this->sabreDiagnostic->formatLines($section);
        }

        $lines = [];
        foreach ($section as $key => $value) {
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

    private function hostFromUrl(string $url): string
    {
        $host = parse_url(str_contains($url, '://') ? $url : 'https://'.$url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'unknown';
    }
}
