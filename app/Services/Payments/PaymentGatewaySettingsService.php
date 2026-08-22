<?php

namespace App\Services\Payments;

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Admin-managed AbhiPay gateway settings (encrypted credentials, audit on save).
 */
class PaymentGatewaySettingsService
{
    public function findOrNewAbhiPay(?int $agencyId): PaymentGateway
    {
        return PaymentGateway::query()->firstOrNew([
            'agency_id' => $agencyId,
            'code' => PaymentGateway::CODE_ABHIPAY,
        ], [
            'name' => 'AbhiPay',
            'environment' => 'test',
            'base_url' => PaymentGateway::DEFAULT_BASE_URL,
            'is_active' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveAbhiPay(Agency $agency, User $actor, array $data): PaymentGateway
    {
        $gateway = $this->findOrNewAbhiPay($agency->id);
        $wasActive = (bool) $gateway->is_active;
        $secretReplaced = filled($data['merchant_secret_key'] ?? null);

        $gateway->fill([
            'name' => 'AbhiPay',
            'environment' => in_array(($data['environment'] ?? 'test'), ['test', 'live'], true)
                ? (string) $data['environment']
                : 'test',
            'is_active' => (bool) ($data['is_active'] ?? false),
            'merchant_id' => filled($data['merchant_id'] ?? null) && ! str_contains((string) $data['merchant_id'], '•')
                ? (string) $data['merchant_id']
                : $gateway->merchant_id,
            'base_url' => filled($data['base_url'] ?? null)
                ? rtrim((string) $data['base_url'], '/')
                : PaymentGateway::DEFAULT_BASE_URL,
            'callback_url' => route('payments.abhipay.callback'),
            'success_url' => filled($data['success_url'] ?? null)
                ? (string) $data['success_url']
                : route('payments.success'),
            'cancel_url' => filled($data['cancel_url'] ?? null)
                ? (string) $data['cancel_url']
                : route('payments.cancel'),
            'decline_url' => filled($data['decline_url'] ?? null)
                ? (string) $data['decline_url']
                : route('payments.decline'),
        ]);

        if ($secretReplaced) {
            $gateway->merchant_secret_key = (string) $data['merchant_secret_key'];
        }

        $gateway->save();

        AuditLog::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $actor->id,
            'action' => 'payment_gateway.abhipay.updated',
            'auditable_type' => PaymentGateway::class,
            'auditable_id' => $gateway->id,
            'properties' => [
                'old_values' => [],
                'new_values' => [
                    'environment' => $gateway->environment,
                    'is_active' => $gateway->is_active,
                    'was_active' => $wasActive,
                    'merchant_id_configured' => filled($gateway->merchant_id),
                    'merchant_secret_configured' => $gateway->hasMerchantSecretKey(),
                    'secret_replaced' => $secretReplaced,
                    'base_url' => $gateway->base_url,
                ],
            ],
        ]);

        if ($secretReplaced) {
            AuditLog::query()->create([
                'agency_id' => $agency->id,
                'user_id' => $actor->id,
                'action' => 'payment_gateway.abhipay.secret_replaced',
                'auditable_type' => PaymentGateway::class,
                'auditable_id' => $gateway->id,
                'properties' => [
                    'new_values' => [
                        'environment' => $gateway->environment,
                        'secret_replaced' => true,
                        'merchant_secret_configured' => true,
                        'base_url' => $gateway->base_url,
                    ],
                ],
            ]);
        }

        return $gateway->fresh() ?? $gateway;
    }

    // Alias kept for older call sites that used hasMerchantSecretKey naming.

    /**
     * Safe non-commercial connection probe (no order creation).
     *
     * @return array{
     *   ok: bool,
     *   status: string,
     *   message: string,
     *   http_status: int|null,
     *   latency_ms: int|null,
     *   tested_at: string
     * }
     */
    public function testConnection(PaymentGateway $gateway): array
    {
        $testedAt = now()->toIso8601String();

        if (! $gateway->isConfigured()) {
            return [
                'ok' => false,
                'status' => 'CONFIGURATION_INCOMPLETE',
                'message' => 'Merchant ID and secret key are required before testing.',
                'http_status' => null,
                'latency_ms' => null,
                'tested_at' => $testedAt,
            ];
        }

        $started = microtime(true);

        try {
            $response = Http::baseUrl(rtrim((string) $gateway->base_url, '/'))
                ->acceptJson()
                ->withHeaders(['Authorization' => (string) $gateway->merchant_secret_key])
                ->timeout(15)
                ->get('/orders/by-rrn/OTA-TEST-CONNECTION');

            $latencyMs = (int) round((microtime(true) - $started) * 1000);

            if ($response->status() === 401 || $response->status() === 403) {
                return [
                    'ok' => false,
                    'status' => 'AUTHENTICATION_FAILED',
                    'message' => 'AbhiPay rejected the credentials (unauthorized).',
                    'http_status' => $response->status(),
                    'latency_ms' => $latencyMs,
                    'tested_at' => $testedAt,
                ];
            }

            return [
                'ok' => true,
                'status' => 'CONNECTED',
                'message' => 'AbhiPay API reachable with stored credentials.',
                'http_status' => $response->status(),
                'latency_ms' => $latencyMs,
                'tested_at' => $testedAt,
            ];
        } catch (\Throwable) {
            return [
                'ok' => false,
                'status' => 'NETWORK_ERROR',
                'message' => 'Could not reach AbhiPay API. Check base URL and network.',
                'http_status' => null,
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'tested_at' => $testedAt,
            ];
        }
    }

    /**
     * Sanitized admin presentation — never includes plaintext secrets.
     *
     * @return array<string, mixed>
     */
    public function presentAbhiPay(PaymentGateway $gateway): array
    {
        return [
            'code' => PaymentGateway::CODE_ABHIPAY,
            'name' => $gateway->name ?: 'AbhiPay',
            'environment' => $gateway->environment ?: 'test',
            'is_active' => (bool) $gateway->is_active,
            'api_version' => 'V3',
            'base_url' => $gateway->base_url ?: PaymentGateway::DEFAULT_BASE_URL,
            'merchant_id_masked' => $gateway->maskedMerchantId(),
            'merchant_secret_configured' => $gateway->hasMerchantSecretKey(),
            'merchant_secret_masked' => $gateway->hasMerchantSecretKey() ? '•••••••••• configured' : null,
            'callback_url' => $gateway->callback_url ?: route('payments.abhipay.callback'),
            'success_url' => $gateway->success_url,
            'cancel_url' => $gateway->cancel_url,
            'decline_url' => $gateway->decline_url,
            'credentials_configured' => $gateway->isConfigured(),
            'checkout_available' => $gateway->isAvailableForCheckout(),
            'readiness' => $gateway->exists
                ? $gateway->checkoutReadinessFlags()
                : [
                    'ABHIPAY_RECORD_PRESENT' => 'NO',
                    'ABHIPAY_ACTIVE' => 'NO',
                    'ABHIPAY_CONFIGURED' => 'NO',
                    'ABHIPAY_CHECKOUT_AVAILABLE' => 'NO',
                    'ABHIPAY_BASE_URL_IS_V3' => 'NO',
                    'ABHIPAY_CALLBACK_CONFIGURED' => 'NO',
                ],
        ];
    }
}
