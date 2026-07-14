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
            'success_url' => route('payments.success'),
            'cancel_url' => route('payments.cancel'),
            'decline_url' => route('payments.decline'),
        ]);

        if (filled($data['merchant_secret_key'] ?? null)) {
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
                    'base_url' => $gateway->base_url,
                ],
            ],
        ]);

        return $gateway->fresh();
    }

    /**
     * @return array{ok: bool, message: string, http_status: int|null}
     */
    public function testConnection(PaymentGateway $gateway): array
    {
        if (! $gateway->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'Merchant ID and secret key are required before testing.',
                'http_status' => null,
            ];
        }

        try {
            $response = Http::baseUrl(rtrim((string) $gateway->base_url, '/'))
                ->acceptJson()
                ->withHeaders(['Authorization' => (string) $gateway->merchant_secret_key])
                ->timeout(15)
                ->get('/orders/by-rrn/OTA-TEST-CONNECTION');

            if ($response->status() === 401 || $response->status() === 403) {
                return [
                    'ok' => false,
                    'message' => 'AbhiPay rejected the credentials (unauthorized).',
                    'http_status' => $response->status(),
                ];
            }

            return [
                'ok' => true,
                'message' => 'AbhiPay API reachable with stored credentials.',
                'http_status' => $response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Could not reach AbhiPay API. Check base URL and network.',
                'http_status' => null,
            ];
        }
    }
}
