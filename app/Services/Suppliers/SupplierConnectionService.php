<?php

namespace App\Services\Suppliers;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Support\Suppliers\AlHaiderSupplierConnectionNormalizer;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Integrations\GoogleOauthConfigResolver;
use App\Support\Suppliers\SabreSupplierChannelConfig;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;

class SupplierConnectionService
{
    public function __construct(
        protected SupplierDiagnosticLogger $diagnosticLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeConnection(Agency $agency, array $data): SupplierConnection
    {
        return DB::transaction(function () use ($agency, $data): SupplierConnection {
            $connection = SupplierConnection::query()->create($data + [
                'agency_id' => $agency->id,
            ]);

            $this->writeAudit(
                $connection,
                auth()->user(),
                'supplier.connection_created',
                [],
                $this->auditPayload($connection)
            );

            return $connection->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateConnection(SupplierConnection $connection, array $data): SupplierConnection
    {
        return DB::transaction(function () use ($connection, $data): SupplierConnection {
            $old = $this->auditPayload($connection);
            $oldSabreChannels = $this->sabreChannelAuditSnapshot($connection);
            $credentials = Arr::get($data, 'credentials');
            if (! is_array($credentials) || $credentials === []) {
                unset($data['credentials']);
            }

            $connection->fill($data);
            if ($connection->status === SupplierConnectionStatus::Active) {
                $connection->is_active = true;
            }
            $connection->save();

            $newSabreChannels = $this->sabreChannelAuditSnapshot($connection);
            if ($oldSabreChannels !== null && $newSabreChannels !== null && $oldSabreChannels !== $newSabreChannels) {
                $this->writeAudit(
                    $connection,
                    auth()->user(),
                    'supplier.sabre_channel_config_updated',
                    $oldSabreChannels,
                    $newSabreChannels,
                );
            }

            $this->writeAudit(
                $connection,
                auth()->user(),
                'supplier.connection_updated',
                $old,
                $this->auditPayload($connection)
            );

            return $connection->fresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function testConnection(SupplierConnection $connection, User $actor): array
    {
        return DB::transaction(function () use ($connection, $actor): array {
            $credentials = $connection->credentials ?? [];
            $hasCreds = is_array($credentials) && count(array_filter($credentials, fn ($value): bool => trim((string) $value) !== '')) > 0;

            $old = $this->auditPayload($connection);
            $lastError = null;
            $lastTestStatus = null;

            if ($connection->provider === SupplierProvider::AlHaider) {
                return $this->testAlHaiderConnection($connection, $actor, $old);
            }

            if ($connection->provider === SupplierProvider::GoogleOauth) {
                return $this->testGoogleOauthConnection($connection, $actor, $old);
            }

            if ($hasCreds && $this->hasRequiredCredentialKeys($connection->provider, $credentials)) {
                $lastTestStatus = 'ready_for_review';
            } else {
                $lastTestStatus = 'missing_credentials';
                $lastError = 'Required credentials are missing for readiness check.';
                $connection->status = SupplierConnectionStatus::Error;
                $connection->is_active = false;
            }

            $connection->last_tested_at = now();
            $connection->last_test_status = $lastTestStatus;
            $connection->last_error = $lastError;
            $connection->save();

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'readiness_check',
                status: $lastError === null ? 'success' : 'failed',
                safeMessage: $lastError,
                meta: [
                    'last_test_status' => $lastTestStatus,
                ],
            );

            $this->writeAudit(
                $connection,
                $actor,
                'supplier.connection_tested',
                $old,
                $this->auditPayload($connection)
            );

            return [
                'status' => $connection->status->value,
                'last_test_status' => $connection->last_test_status,
                'last_error' => $connection->last_error,
            ];
        });
    }

    /**
     * Al-Haider Test Connection: existing token only, read-only inventory probe, never login.
     *
     * @param  array<string, mixed>  $old
     * @return array<string, mixed>
     */
    protected function testAlHaiderConnection(SupplierConnection $connection, User $actor, array $old): array
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $token = trim((string) ($credentials['existing_token'] ?? ''));
        $mode = strtolower(trim((string) ($credentials['auth_mode'] ?? AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL)));

        if ($token === '' || ! in_array($mode, [
            AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL,
            AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANAGED,
        ], true)) {
            $connection->last_tested_at = now();
            $connection->last_test_status = 'missing_credentials';
            $connection->last_error = 'Existing Al-Haider token is required for Test Connection.';
            $connection->save();

            $this->writeAudit($connection, $actor, 'supplier.connection_tested', $old, $this->auditPayload($connection));

            return [
                'status' => $connection->status->value,
                'last_test_status' => $connection->last_test_status,
                'last_error' => $connection->last_error,
                'token_generation_calls' => 0,
            ];
        }

        $base = rtrim((string) ($connection->base_url ?: config('suppliers.al_haider.default_base_url')), '/');
        $path = (string) config('suppliers.al_haider.groups_path', '/api/available/groups');
        $url = $base.'/'.ltrim($path, '/');

        $httpStatus = 0;
        $groupCount = null;
        $lastError = null;
        $lastTestStatus = 'failed';

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ])
                ->timeout((int) config('suppliers.al_haider.timeout_seconds', 20))
                ->connectTimeout((int) config('suppliers.al_haider.connect_timeout_seconds', 10))
                ->get($url);

            $httpStatus = $response->status();
            if ($httpStatus >= 200 && $httpStatus < 300) {
                $decoded = $response->json();
                $groups = is_array($decoded) ? ($decoded['groups'] ?? null) : null;
                if (is_array($groups)) {
                    $groupCount = count($groups);
                    $lastTestStatus = 'ok';
                    $connection->last_error = null;
                    if ($connection->status === SupplierConnectionStatus::Error) {
                        $connection->status = SupplierConnectionStatus::Active;
                    }
                } else {
                    $lastTestStatus = 'invalid_schema';
                    $lastError = 'Al-Haider response schema was unexpected.';
                }
            } elseif ($httpStatus === 401) {
                $lastTestStatus = 'token_rejected';
                $lastError = 'Al-Haider rejected the current token (401). No new token was generated.';
            } else {
                $lastTestStatus = 'http_error';
                $lastError = 'Al-Haider returned HTTP '.$httpStatus.'.';
            }
        } catch (\Throwable $e) {
            $lastTestStatus = 'transport_error';
            $lastError = 'Al-Haider is temporarily unreachable.';
            \Illuminate\Support\Facades\Log::warning('alhaider.test_connection_failed', [
                'connection_id' => $connection->id,
                'message' => $e->getMessage(),
            ]);
        }

        $connection->last_tested_at = now();
        $connection->last_test_status = $lastTestStatus;
        $connection->last_error = $lastError;
        $connection->save();

        $this->diagnosticLogger->log(
            connection: $connection,
            action: 'alhaider_read_only_test',
            status: $lastError === null ? 'success' : 'failed',
            safeMessage: $lastError,
            meta: [
                'last_test_status' => $lastTestStatus,
                'http_status' => $httpStatus,
                'group_count' => $groupCount,
                'token_generation_calls' => 0,
            ],
        );

        $this->writeAudit($connection, $actor, 'supplier.connection_tested', $old, $this->auditPayload($connection));

        return [
            'status' => $connection->status->value,
            'last_test_status' => $connection->last_test_status,
            'last_error' => $connection->last_error,
            'http_status' => $httpStatus,
            'group_count' => $groupCount,
            'token_generation_calls' => 0,
        ];
    }

    /**
     * Google OAuth Test Configuration: completeness + redirect shape + Socialite driver resolve only.
     * Never performs a token exchange or live Google call.
     *
     * @param  array<string, mixed>  $old
     * @return array<string, mixed>
     */
    protected function testGoogleOauthConnection(SupplierConnection $connection, User $actor, array $old): array
    {
        $resolver = app(GoogleOauthConfigResolver::class);
        $reason = $resolver->usabilityFailureReason($connection);
        $lastError = null;
        $lastTestStatus = 'ready_for_review';

        if ($reason !== null) {
            $lastTestStatus = 'config_incomplete';
            $lastError = match ($reason) {
                'missing_client_id' => 'Client ID is required.',
                'missing_client_secret' => 'Client secret is required.',
                'missing_redirect_uri' => 'Redirect URI could not be resolved.',
                'invalid_redirect_uri' => 'Redirect URI must be a valid http(s) URL with host and path.',
                'redirect_must_be_https' => 'Production redirect URI must use HTTPS.',
                default => 'Google OAuth configuration is incomplete.',
            };
        } else {
            $credentials = is_array($connection->credentials) ? $connection->credentials : [];
            $previous = [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect' => config('services.google.redirect'),
            ];

            try {
                \Illuminate\Support\Facades\Config::set('services.google.client_id', trim((string) ($credentials['client_id'] ?? '')));
                \Illuminate\Support\Facades\Config::set('services.google.client_secret', trim((string) ($credentials['client_secret'] ?? '')));
                \Illuminate\Support\Facades\Config::set(
                    'services.google.redirect',
                    \App\Support\Suppliers\GoogleOauthSupplierConnectionNormalizer::resolvedRedirectUri($connection)
                );
                // Resolve driver only — no redirect/user/token exchange.
                Socialite::driver('google');
            } catch (\Throwable $e) {
                $lastTestStatus = 'driver_unavailable';
                $lastError = 'Socialite Google driver could not be resolved.';
            } finally {
                \Illuminate\Support\Facades\Config::set('services.google.client_id', $previous['client_id']);
                \Illuminate\Support\Facades\Config::set('services.google.client_secret', $previous['client_secret']);
                \Illuminate\Support\Facades\Config::set('services.google.redirect', $previous['redirect']);
            }
        }

        $connection->last_tested_at = now();
        $connection->last_test_status = $lastTestStatus;
        $connection->last_error = $lastError;
        $connection->save();

        $this->diagnosticLogger->log(
            connection: $connection,
            action: 'google_oauth_config_check',
            status: $lastError === null ? 'success' : 'failed',
            safeMessage: $lastError,
            meta: [
                'last_test_status' => $lastTestStatus,
                'token_exchange' => false,
            ],
        );

        $this->writeAudit($connection, $actor, 'supplier.connection_tested', $old, $this->auditPayload($connection));

        return [
            'status' => $connection->status->value,
            'last_test_status' => $connection->last_test_status,
            'last_error' => $connection->last_error,
            'token_exchange' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, string>
     */
    public function maskCredentials(array $credentials): array
    {
        $secretKeys = [
            'existing_token',
            'password',
            'client_secret',
            'access_token',
            'token',
            'agent_password',
            'auth_code',
        ];

        $masked = [];
        foreach ($credentials as $key => $value) {
            $text = trim((string) $value);
            if (in_array(strtolower((string) $key), $secretKeys, true) && $text !== '') {
                $masked[$key] = 'Configured (masked)';

                continue;
            }

            $tail = strlen($text) > 4 ? substr($text, -4) : '';
            $masked[$key] = $tail !== '' ? '••••'.$tail : '••••••••';
        }

        return $masked;
    }

    /**
     * Safe yes/no check for required credential keys — never exposes values.
     */
    public function credentialKeysPresent(SupplierConnection $connection): bool
    {
        if ($connection->provider === null) {
            return false;
        }

        $credentials = $connection->credentials ?? [];
        if (! is_array($credentials) || $credentials === []) {
            return false;
        }

        $hasNonEmpty = count(array_filter($credentials, fn ($value): bool => trim((string) $value) !== '')) > 0;
        if (! $hasNonEmpty) {
            return false;
        }

        return $this->hasRequiredCredentialKeys($connection->provider, $credentials);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    protected function hasRequiredCredentialKeys(?SupplierProvider $provider, array $credentials): bool
    {
        if ($provider === null) {
            return false;
        }

        $keys = array_map('strtolower', array_keys($credentials));

        return match ($provider) {
            SupplierProvider::Sabre => (in_array('client_id', $keys, true) && in_array('client_secret', $keys, true))
                || (in_array('sign_in', $keys, true) && in_array('password', $keys, true)),
            SupplierProvider::Duffel => in_array('access_token', $keys, true),
            SupplierProvider::Iati => in_array('auth_code', $keys, true)
                && in_array('organization_id', $keys, true),
            SupplierProvider::PiaNdc => in_array('username', $keys, true)
                && in_array('password', $keys, true)
                && in_array('agency_id', $keys, true)
                && in_array('agency_name', $keys, true)
                && in_array('owner_code', $keys, true),
            SupplierProvider::Airblue => (
                (in_array('username', $keys, true) && in_array('password', $keys, true) && in_array('agency_id', $keys, true))
                || (in_array('client_id', $keys, true) && in_array('client_key', $keys, true) && in_array('agent_id', $keys, true))
            ),
            SupplierProvider::AirlineDirect => in_array('api_key', $keys, true) || in_array('token', $keys, true) || (in_array('username', $keys, true) && in_array('password', $keys, true)),
            SupplierProvider::OneApi => in_array('username', $keys, true)
                && in_array('password', $keys, true)
                && in_array('agent_code', $keys, true)
                && in_array('rest_auth_url', $keys, true)
                && in_array('rest_search_url', $keys, true),
            SupplierProvider::AlHaider => self::alHaiderCredentialKeysComplete($credentials),
            SupplierProvider::Smtp => trim((string) ($credentials['host'] ?? '')) !== ''
                && trim((string) ($credentials['from_address'] ?? '')) !== '',
            SupplierProvider::GoogleOauth => trim((string) ($credentials['client_id'] ?? '')) !== ''
                && trim((string) ($credentials['client_secret'] ?? '')) !== '',
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    protected static function alHaiderCredentialKeysComplete(array $credentials): bool
    {
        $mode = strtolower(trim((string) ($credentials['auth_mode'] ?? AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL)));
        if ($mode === AlHaiderSupplierConnectionNormalizer::AUTH_MODE_AUTO) {
            return trim((string) ($credentials['username'] ?? '')) !== ''
                && trim((string) ($credentials['password'] ?? '')) !== '';
        }

        if ($mode === AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANAGED) {
            return trim((string) ($credentials['existing_token'] ?? '')) !== '';
        }

        return trim((string) ($credentials['existing_token'] ?? '')) !== '';
    }

    protected function writeAudit(SupplierConnection $connection, ?User $actor, string $action, array $oldValues, array $newValues): void
    {
        AuditLog::query()->create([
            'agency_id' => $connection->agency_id,
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => SupplierConnection::class,
            'auditable_id' => $connection->id,
            'properties' => [
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ],
        ]);
    }

    /**
     * Record successful live AirShopping (or equivalent) — refreshes health signals without requiring a separate health CLI pass.
     */
    public function recordSupplierSearchSuccess(SupplierConnection $connection, string $source, int $offerCount): void
    {
        $healthPayload = [
            'healthy' => true,
            'source' => $source,
            'offer_count' => $offerCount,
            'checked_at' => now()->toIso8601String(),
        ];

        $updates = [
            'last_tested_at' => now(),
            'last_test_status' => 'air_shopping_success',
            'last_error' => null,
        ];

        if (SupplierConnection::hasHealthyDatabaseColumn()) {
            $updates['healthy'] = true;
        }

        if (SupplierConnection::hasMetaDatabaseColumn()) {
            $meta = is_array($connection->meta) ? $connection->meta : [];
            $meta['supplier_health'] = $healthPayload;
            $updates['meta'] = $meta;
        } elseif (SupplierConnection::hasSettingsDatabaseColumn()) {
            $settings = is_array($connection->settings) ? $connection->settings : [];
            $settings['supplier_health'] = $healthPayload;
            $updates['settings'] = $settings;
        }

        $connection->forceFill($updates)->save();
    }

    /**
     * @return array<string, mixed>
     */
    protected function auditPayload(SupplierConnection $connection): array
    {
        $payload = [
            'provider' => $connection->provider->value,
            'name' => $connection->name,
            'environment' => $connection->environment->value,
            'status' => $connection->status->value,
            'base_url' => $connection->base_url,
            'last_tested_at' => $connection->last_tested_at?->toIso8601String(),
            'last_test_status' => $connection->last_test_status,
            'last_error' => $connection->last_error,
            'credentials' => $this->maskCredentials($connection->credentials ?? []),
        ];

        if ($connection->provider === SupplierProvider::Sabre) {
            $channels = $this->sabreChannelAuditSnapshot($connection);
            if ($channels !== null) {
                $payload = array_merge($payload, $channels);
            }
        }

        return $payload;
    }

    /**
     * @return array{sabre_gds_enabled: bool, sabre_ndc_enabled: bool}|null
     */
    protected function sabreChannelAuditSnapshot(SupplierConnection $connection): ?array
    {
        if ($connection->provider !== SupplierProvider::Sabre) {
            return null;
        }

        $config = SabreSupplierChannelConfig::fromConnection($connection);

        return [
            'sabre_gds_enabled' => $config->gdsEnabled,
            'sabre_ndc_enabled' => $config->ndcEnabled,
        ];
    }
}
