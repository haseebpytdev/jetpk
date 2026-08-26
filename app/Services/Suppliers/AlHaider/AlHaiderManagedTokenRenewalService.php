<?php

namespace App\Services\Suppliers\AlHaider;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Support\Suppliers\AlHaiderSupplierConnectionNormalizer;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Strict managed-token auto-renewal for Al-Haider.
 *
 * Authority: SupplierConnection encrypted credentials (DB).
 * Automatic issuance budget is DB-persistent (settings.token_issuance) and survives cache clears.
 * Never renews on page load, Test Connection, readiness, 401-before-expiry, or ambiguous login results.
 */
final class AlHaiderManagedTokenRenewalService
{
    public const MAX_AUTOMATIC_TOKEN_GENERATIONS_PER_365_DAYS = 1;

    public const GENERATION_STATE_IDLE = 'idle';

    public const GENERATION_STATE_AMBIGUOUS = 'AMBIGUOUS_REQUIRES_OWNER_REVIEW';

    public const GENERATION_STATE_BUDGET_EXHAUSTED = 'budget_exhausted';

    public const GENERATION_STATE_DISABLED = 'auto_renew_disabled';

    public const RENEWAL_LOCK_KEY = 'alhaider:managed_token:renewal';

    public function __construct(
        private readonly AlHaiderTokenExpiryResolver $tokenExpiryResolver,
    ) {}

    /**
     * Attempt automatic renewal only when the current DB token is proven expired.
     *
     * @return array{token: string, renewed: bool, reason: string}
     */
    public function renewIfExpired(SupplierConnection $connection): array
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $mode = strtolower(trim((string) ($credentials['auth_mode'] ?? '')));
        if ($mode !== AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANAGED) {
            return ['token' => '', 'renewed' => false, 'reason' => 'not_managed_mode'];
        }

        $lockSeconds = max(10, (int) config('suppliers.al_haider.login_lock_seconds', 15));
        $waitSeconds = max(1, (int) config('suppliers.al_haider.login_lock_wait_seconds', 10));
        $lock = Cache::lock(self::RENEWAL_LOCK_KEY, $lockSeconds);

        try {
            $lock->block($waitSeconds);
        } catch (LockTimeoutException) {
            $fresh = $this->reloadConnection($connection->id);
            $token = trim((string) (($fresh?->credentials['existing_token'] ?? '')));
            if ($token !== '' && ! $this->tokenExpired($fresh?->credentials['token_expires_at'] ?? null)) {
                return ['token' => $token, 'renewed' => false, 'reason' => 'lock_timeout_reused_peer_token'];
            }

            throw new AlHaiderProviderException(
                'supplier_auth_busy',
                503,
                'Al-Haider authentication is busy. Please try again.'
            );
        }

        try {
            return $this->renewInsideLock($connection->id);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{token: string, renewed: bool, reason: string}
     */
    private function renewInsideLock(int $connectionId): array
    {
        $connection = $this->reloadConnection($connectionId);
        if ($connection === null) {
            return ['token' => '', 'renewed' => false, 'reason' => 'connection_missing'];
        }

        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $currentToken = trim((string) ($credentials['existing_token'] ?? ''));
        $expiresAt = $credentials['token_expires_at'] ?? null;

        if ($currentToken !== '' && ! $this->tokenExpired($expiresAt)) {
            return ['token' => $currentToken, 'renewed' => false, 'reason' => 'still_valid'];
        }

        if (! $this->autoRenewEnabled($credentials)) {
            $this->persistIssuanceMeta($connection, [
                'generation_state' => self::GENERATION_STATE_DISABLED,
            ]);

            throw new AlHaiderProviderException(
                'supplier_auth_token_expired',
                401,
                'Token expired / rejected'
            );
        }

        $issuance = $this->issuanceMeta($connection);
        if (($issuance['generation_state'] ?? '') === self::GENERATION_STATE_AMBIGUOUS) {
            throw new AlHaiderProviderException(
                'supplier_auth_ambiguous',
                503,
                'Al-Haider token renewal requires owner review.'
            );
        }

        if (! $this->automaticBudgetAvailable($issuance)) {
            $this->persistIssuanceMeta($connection, [
                'generation_state' => self::GENERATION_STATE_BUDGET_EXHAUSTED,
            ]);

            throw new AlHaiderProviderException(
                'supplier_auth_token_budget',
                503,
                'Al-Haider automatic token generation budget is exhausted.'
            );
        }

        $username = trim((string) ($credentials['username'] ?? ''));
        $password = trim((string) ($credentials['password'] ?? ''));
        if ($username === '' || $password === '') {
            throw new AlHaiderProviderException(
                'supplier_auth_missing',
                401,
                'Al-Haider renewal credentials are not configured.'
            );
        }

        $attemptAt = now()->toIso8601String();
        $this->persistIssuanceMeta($connection, [
            'last_generation_attempt_at' => $attemptAt,
            'generation_state' => 'attempting',
        ]);

        $base = rtrim((string) ($connection->base_url ?: config('suppliers.al_haider.default_base_url')), '/');
        $loginPath = (string) config('suppliers.al_haider.login_path', '/api/login');
        $url = $base.'/'.ltrim($loginPath, '/');

        Log::info('alhaider.auth.managed_renewal_attempted', [
            'supplier' => 'alhaider',
            'connection_id' => $connection->id,
            'endpoint' => $loginPath,
        ]);

        try {
            $response = Http::withHeaders(['Accept' => 'application/json'])
                ->asForm()
                ->timeout((int) config('suppliers.al_haider.timeout_seconds', 20))
                ->connectTimeout((int) config('suppliers.al_haider.connect_timeout_seconds', 10))
                ->post($url, [
                    'email' => $username,
                    'password' => $password,
                ]);
        } catch (ConnectionException $exception) {
            $this->persistIssuanceMeta($connection, [
                'generation_state' => self::GENERATION_STATE_AMBIGUOUS,
                'last_generation_attempt_at' => $attemptAt,
            ]);

            Log::warning('alhaider.auth.managed_renewal_ambiguous', [
                'supplier' => 'alhaider',
                'connection_id' => $connection->id,
                'reason' => 'connection_exception',
            ]);

            throw new AlHaiderProviderException(
                'supplier_auth_ambiguous',
                503,
                'Al-Haider token renewal requires owner review.',
                $exception
            );
        }

        $decoded = $response->json();
        $token = is_array($decoded) ? trim((string) ($decoded['token'] ?? '')) : '';
        if ($token === '') {
            // Definite failure response — do not mark ambiguous; do not retry automatically.
            $this->persistIssuanceMeta($connection, [
                'generation_state' => 'failed',
                'last_generation_attempt_at' => $attemptAt,
            ]);

            throw new AlHaiderProviderException(
                'supplier_auth_failed',
                $response->status() ?: 401,
                'Al-Haider login failed.'
            );
        }

        $issuedAt = time();
        $expiresAtTs = $this->tokenExpiryResolver->resolveExpiresAt($response, $issuedAt);
        $expiresAtIso = gmdate('c', $expiresAtTs);

        DB::transaction(function () use ($connection, $credentials, $token, $expiresAtIso, $attemptAt, $issuance): void {
            $fresh = $this->reloadConnection($connection->id);
            if ($fresh === null) {
                throw new AlHaiderProviderException(
                    'supplier_auth_failed',
                    503,
                    'Al-Haider connection disappeared during renewal.'
                );
            }

            $freshCredentials = is_array($fresh->credentials) ? $fresh->credentials : $credentials;
            // Peer may have already renewed while we held the outer lock path.
            $peerToken = trim((string) ($freshCredentials['existing_token'] ?? ''));
            if ($peerToken !== '' && ! $this->tokenExpired($freshCredentials['token_expires_at'] ?? null)) {
                return;
            }

            $freshCredentials['existing_token'] = $token;
            $freshCredentials['token_expires_at'] = $expiresAtIso;
            $fresh->credentials = $freshCredentials;

            $settings = is_array($fresh->settings) ? $fresh->settings : [];
            $periodStart = $issuance['rolling_period_started_at'] ?? now()->toIso8601String();
            $count = (int) ($issuance['automatic_generation_count'] ?? 0);
            if ($this->periodExpired($issuance)) {
                $periodStart = now()->toIso8601String();
                $count = 0;
            }
            $count++;

            $settings['token_issuance'] = [
                'last_generation_attempt_at' => $attemptAt,
                'last_generation_success_at' => now()->toIso8601String(),
                'generation_state' => self::GENERATION_STATE_IDLE,
                'automatic_generation_count' => $count,
                'rolling_period_started_at' => $periodStart,
                'next_automatic_generation_eligible_at' => now()->addDays(365)->toIso8601String(),
                'max_automatic_per_365_days' => self::MAX_AUTOMATIC_TOKEN_GENERATIONS_PER_365_DAYS,
            ];
            $fresh->settings = $settings;
            $fresh->save();
        });

        $reloaded = $this->reloadConnection($connectionId);
        $finalToken = trim((string) (($reloaded?->credentials['existing_token'] ?? '')));

        Log::info('alhaider.auth.managed_renewal_succeeded', [
            'supplier' => 'alhaider',
            'connection_id' => $connectionId,
            'expires_at' => $expiresAtIso,
        ]);

        return ['token' => $finalToken, 'renewed' => true, 'reason' => 'renewed'];
    }

    /**
     * Safe operational status for UI — never includes secrets.
     *
     * @return array<string, mixed>
     */
    public function operationalStatus(SupplierConnection $connection): array
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $issuance = $this->issuanceMeta($connection);
        $tokenPresent = trim((string) ($credentials['existing_token'] ?? '')) !== '';
        $expiresAt = trim((string) ($credentials['token_expires_at'] ?? ''));

        return [
            'auth_mode' => (string) ($credentials['auth_mode'] ?? ''),
            'token_configured' => $tokenPresent,
            'token_expires_at' => $expiresAt !== '' ? $expiresAt : null,
            'auto_renew_enabled' => $this->autoRenewEnabled($credentials),
            'renewal_username_configured' => trim((string) ($credentials['username'] ?? '')) !== '',
            'renewal_password_configured' => trim((string) ($credentials['password'] ?? '')) !== '',
            'last_renewal_at' => $issuance['last_generation_success_at'] ?? null,
            'next_automatic_renewal_eligible_at' => $issuance['next_automatic_generation_eligible_at'] ?? null,
            'automatic_generations_in_period' => (int) ($issuance['automatic_generation_count'] ?? 0),
            'generation_state' => (string) ($issuance['generation_state'] ?? self::GENERATION_STATE_IDLE),
            'max_automatic_per_365_days' => self::MAX_AUTOMATIC_TOKEN_GENERATIONS_PER_365_DAYS,
            'authority' => 'SupplierConnection_DB',
        ];
    }

    public function tokenExpired(mixed $tokenExpiresAt): bool
    {
        if ($tokenExpiresAt === null || trim((string) $tokenExpiresAt) === '') {
            return false;
        }

        $timestamp = strtotime((string) $tokenExpiresAt);

        return $timestamp !== false && $timestamp <= time();
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function autoRenewEnabled(array $credentials): bool
    {
        $raw = $credentials['auto_renew'] ?? $credentials['auto_renew_enabled'] ?? false;
        if (is_bool($raw)) {
            return $raw;
        }

        return in_array(strtolower(trim((string) $raw)), ['1', 'true', 'yes', 'enabled', 'on'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function issuanceMeta(SupplierConnection $connection): array
    {
        $settings = is_array($connection->settings) ? $connection->settings : [];
        $meta = $settings['token_issuance'] ?? [];

        return is_array($meta) ? $meta : [];
    }

    /**
     * @param  array<string, mixed>  $issuance
     */
    public function automaticBudgetAvailable(array $issuance): bool
    {
        if (($issuance['generation_state'] ?? '') === self::GENERATION_STATE_AMBIGUOUS) {
            return false;
        }

        if ($this->periodExpired($issuance)) {
            return true;
        }

        $count = (int) ($issuance['automatic_generation_count'] ?? 0);

        return $count < self::MAX_AUTOMATIC_TOKEN_GENERATIONS_PER_365_DAYS;
    }

    /**
     * @param  array<string, mixed>  $issuance
     */
    private function periodExpired(array $issuance): bool
    {
        $start = $issuance['rolling_period_started_at'] ?? null;
        if ($start === null || trim((string) $start) === '') {
            return true;
        }

        $ts = strtotime((string) $start);
        if ($ts === false) {
            return true;
        }

        return $ts <= (time() - 365 * 24 * 60 * 60);
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    private function persistIssuanceMeta(SupplierConnection $connection, array $patch): void
    {
        $fresh = $this->reloadConnection($connection->id) ?? $connection;
        $settings = is_array($fresh->settings) ? $fresh->settings : [];
        $current = is_array($settings['token_issuance'] ?? null) ? $settings['token_issuance'] : [];
        $settings['token_issuance'] = array_merge($current, $patch, [
            'max_automatic_per_365_days' => self::MAX_AUTOMATIC_TOKEN_GENERATIONS_PER_365_DAYS,
        ]);
        $fresh->settings = $settings;
        $fresh->save();
    }

    private function reloadConnection(int $id): ?SupplierConnection
    {
        return SupplierConnection::query()
            ->where('provider', SupplierProvider::AlHaider->value)
            ->whereKey($id)
            ->first();
    }
}
