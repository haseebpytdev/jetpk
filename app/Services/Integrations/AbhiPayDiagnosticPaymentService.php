<?php

namespace App\Services\Integrations;

use App\Enums\IntegrationHealthStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payments\Gateways\AbhiPayGateway;
use App\Services\Payments\PaymentGatewaySettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Controlled AbhiPay test-mode diagnostic payment (no booking / PNR).
 */
final class AbhiPayDiagnosticPaymentService
{
    public const PURPOSE = 'integration_test';

    public const DEFAULT_AMOUNT = '1.00';

    public function __construct(
        private readonly PaymentGatewaySettingsService $settings,
        private readonly AbhiPayGateway $gateway,
        private readonly IntegrationHealthRecorder $healthRecorder,
        private readonly IntegrationTestThrottle $throttle,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function create(User $actor, array $options = [], ?int $agencyId = null): array
    {
        $this->throttle->assertAllowed($actor, PaymentGateway::CODE_ABHIPAY, 'test_payment');

        $agency = $this->resolveAgency($actor, $agencyId);
        $gateway = $this->settings->findOrNewAbhiPay($agency->id);

        if (! $gateway->exists || ! $gateway->isConfigured()) {
            throw new InvalidArgumentException('AbhiPay must be configured before creating a diagnostic payment.');
        }

        if (strtolower((string) $gateway->environment) !== 'test') {
            throw new RuntimeException('Diagnostic payments are only available in the Test environment.');
        }

        if (! $gateway->is_active && empty($options['force_diagnostic'])) {
            throw new RuntimeException('Enable AbhiPay (or pass an explicit diagnostic override) before Test Payment.');
        }

        $amount = isset($options['amount']) ? number_format((float) $options['amount'], 2, '.', '') : self::DEFAULT_AMOUNT;
        if ((float) $amount < 1.0) {
            // Provider docs do not prove sub-1.00 minima; keep safe default floor.
            throw new InvalidArgumentException('Diagnostic payment amount must be at least PKR 1.00.');
        }

        $latestHealth = $this->healthRecorder->latest(PaymentGateway::CODE_ABHIPAY, 'connection');
        if ($latestHealth === null || $latestHealth->status !== IntegrationHealthStatus::Healthy) {
            throw new RuntimeException('Run a successful Test Connection before creating a diagnostic payment.');
        }

        $transaction = DB::transaction(function () use ($actor, $gateway, $amount): PaymentTransaction {
            $txn = PaymentTransaction::query()->create([
                'booking_id' => null,
                'group_booking_id' => null,
                'user_id' => $actor->id,
                'gateway' => PaymentGateway::CODE_ABHIPAY,
                'purpose' => self::PURPOSE,
                'environment' => 'test',
                'amount' => $amount,
                'currency' => 'PKR',
                'client_transaction_id' => 'INT'.Str::upper(Str::random(14)),
                'status' => PaymentTransactionStatus::Pending,
            ]);

            $result = $this->gateway->createPayment($txn);
            $txn->response_payload_json = [
                'diagnostic' => true,
                'purpose' => self::PURPOSE,
                'gateway_code' => $result->gatewayCode,
                'gateway_message' => $result->gatewayMessage,
            ];

            if (! $result->success) {
                $txn->status = PaymentTransactionStatus::Failed;
                $txn->gateway_code = $result->gatewayCode;
                $txn->gateway_message = $result->gatewayMessage ?: $result->errorMessage;
                $txn->failed_at = now();
                $txn->save();

                return $txn;
            }

            $txn->gateway_order_id = $result->gatewayOrderId;
            $txn->gateway_payment_url = $result->redirectUrl;
            $txn->gateway_code = $result->gatewayCode;
            $txn->gateway_message = $result->gatewayMessage;
            $txn->status = PaymentTransactionStatus::Pending;
            $txn->save();

            return $txn;
        });

        $this->throttle->mark($actor, PaymentGateway::CODE_ABHIPAY, 'test_payment');

        $ok = $transaction->status !== PaymentTransactionStatus::Failed;
        $this->healthRecorder->record(
            provider: PaymentGateway::CODE_ABHIPAY,
            testType: 'test_payment',
            status: $ok ? IntegrationHealthStatus::Healthy : IntegrationHealthStatus::ProviderError,
            actor: $actor,
            environment: 'test',
            errorCode: $ok ? null : ($transaction->gateway_code ?: 'test_payment_failed'),
            message: $ok
                ? 'Diagnostic test payment created (test mode).'
                : ($transaction->gateway_message ?: 'Diagnostic test payment failed.'),
            meta: [
                'purpose' => self::PURPOSE,
                'amount' => $amount,
                'currency' => 'PKR',
                'payment_transaction_id' => $transaction->id,
            ],
        );

        AuditLog::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $actor->id,
            'action' => 'integration.abhipay.test_payment',
            'auditable_type' => PaymentTransaction::class,
            'auditable_id' => $transaction->id,
            'properties' => [
                'attributes' => [
                    'purpose' => self::PURPOSE,
                    'environment' => 'test',
                    'amount' => $amount,
                    'currency' => 'PKR',
                    'status' => $transaction->status->value,
                    'ok' => $ok,
                ],
            ],
        ]);

        return [
            'ok' => $ok,
            'status' => $ok ? 'TEST_PAYMENT_CREATED' : 'FAILED',
            'purpose' => self::PURPOSE,
            'amount' => $amount,
            'currency' => 'PKR',
            'payment_url' => $transaction->gateway_payment_url,
            'client_transaction_id' => $transaction->client_transaction_id,
            'payment_transaction_id' => $transaction->id,
            'message' => $ok
                ? 'Test payment created. Complete checkout in the AbhiPay test environment.'
                : ($transaction->gateway_message ?: 'Test payment failed.'),
        ];
    }

    private function resolveAgency(User $actor, ?int $agencyId): Agency
    {
        if ($agencyId !== null) {
            return Agency::query()->findOrFail($agencyId);
        }

        if ($actor->current_agency_id) {
            return Agency::query()->findOrFail($actor->current_agency_id);
        }

        $agency = Agency::query()->orderBy('id')->first();
        abort_if($agency === null, 422, 'No agency available.');

        return $agency;
    }
}
