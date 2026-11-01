<?php

namespace App\Support\Bookings;

use App\Enums\AirBlueZapwaysProtocolVersion;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingTicket;
use App\Models\SupplierConnection;
use App\Models\TicketingAttempt;
use App\Models\User;
use App\Services\Suppliers\AirBlue\AirBlueConfigResolver;
use App\Services\Suppliers\AirBlue\AirBlueSeatSelectionGate;
use App\Support\FlightSearch\AirBlueQaSearchScope;
use App\Support\FlightSearch\QaStaffAirBlueContext;
use App\Support\Platform\PlatformModuleEnforcer;

/**
 * Fail-closed QA corridor for AirBlue Zapways sandbox ticketing via TicketingService.
 *
 * Payment eligibility may be bypassed only when this scope allows. Never treats
 * CLI --allow-live as environment proof.
 */
final class AirBlueQaTicketingScope
{
    public const SCOPE_TYPE = AirBlueQaSearchScope::SCOPE_TYPE;

    /**
     * @var list<string>
     */
    private const APPROVED_TEST_HOSTS = [
        'otatest4.zapways.com',
    ];

    public function __construct(
        private readonly AirBlueConfigResolver $configResolver,
        private readonly PlatformModuleEnforcer $platformModuleEnforcer,
        private readonly AirBlueSeatSelectionGate $seatSelectionGate,
    ) {}

    /**
     * @return array{
     *     allowed: bool,
     *     payment_bypass_only: bool,
     *     reason: string,
     *     connection_id: int|null,
     *     provider: string|null,
     *     environment: string|null,
     *     protocol: string|null,
     *     test_host: string|null,
     *     service_target: string|null,
     *     seat_selection_required: bool,
     *     seat_gate_satisfied: bool,
     *     lifecycle_path: string
     * }
     */
    public function evaluate(Booking $booking, User $actor, ?SupplierConnection $connection = null): array
    {
        return $this->evaluateInternal($booking, $actor, $connection, true);
    }

    /**
     * Pre-enable validation used only by the QA CLI before temporarily setting
     * ticketing_enabled=true. Every other sandbox/booking/actor/seat gate still applies.
     *
     * @return array<string, mixed>
     */
    public function evaluateForTemporaryEnablement(
        Booking $booking,
        User $actor,
        ?SupplierConnection $connection = null,
    ): array {
        return $this->evaluateInternal($booking, $actor, $connection, false);
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluateInternal(
        Booking $booking,
        User $actor,
        ?SupplierConnection $connection,
        bool $requireTicketingFeature,
    ): array {
        $expectedId = (int) config('ota.qa_staff_airblue.connection_id', 23);
        $booking->loadMissing(['latestSupplierBooking']);

        if (! QaStaffAirBlueContext::actorIsAllowed($actor)) {
            return $this->deny($expectedId, 'unauthorized_actor');
        }

        if (! $this->platformModuleEnforcer->effectiveModuleEnabled('ticketing')) {
            return $this->deny($expectedId, 'ticketing_module_disabled');
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $supplier = (string) ($booking->supplier ?? $booking->latestSupplierBooking?->provider ?? '');
        if (! QaFixtureBookingScope::isQaFixture($meta, $supplier)
            && ($meta['qa_scope_type'] ?? null) !== self::SCOPE_TYPE
            && ($meta['jp_airblue_test_airbook_01'] ?? false) !== true
            && ($booking->source_channel ?? '') !== QaStaffAirBlueContext::SOURCE_CHANNEL) {
            return $this->deny($expectedId, 'qa_fixture_missing');
        }

        $provider = strtolower(trim((string) ($booking->latestSupplierBooking?->provider ?? $meta['supplier_provider'] ?? $supplier)));
        if ($provider !== SupplierProvider::Airblue->value) {
            return $this->deny($expectedId, 'provider_not_airblue');
        }

        $connection ??= $this->resolveConnection($booking, $expectedId);
        if ($connection === null) {
            return $this->deny($expectedId, 'connection_missing');
        }
        if ((int) $connection->id !== $expectedId) {
            return $this->deny($expectedId, 'connection_id_mismatch');
        }
        if (($connection->provider instanceof SupplierProvider
            ? $connection->provider
            : SupplierProvider::tryFrom((string) $connection->provider)) !== SupplierProvider::Airblue) {
            return $this->deny($expectedId, 'provider_not_airblue');
        }

        $statusValue = $connection->status instanceof \BackedEnum
            ? $connection->status->value
            : (string) $connection->status;
        if (! $connection->is_active || $statusValue !== 'active') {
            return $this->deny($expectedId, 'connection_inactive');
        }

        if (! $this->configResolver->isTestEnvironment($connection)) {
            return $this->deny($expectedId, 'environment_not_test');
        }

        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $protocol = AirBlueZapwaysProtocolVersion::fromCredentials($credentials);
        if (! $protocol->isV3()) {
            return $this->deny($expectedId, 'protocol_not_v3');
        }

        $host = $this->resolveHost($connection, $credentials);
        if ($host === '' || ! in_array($host, self::APPROVED_TEST_HOSTS, true)) {
            return $this->deny($expectedId, 'host_not_approved_test');
        }

        $serviceTarget = $this->configResolver->resolveServiceTarget($connection, $credentials);
        if (strcasecmp(trim($serviceTarget), 'Test') !== 0) {
            return $this->deny($expectedId, 'service_target_not_test');
        }

        if ($requireTicketingFeature
            && ! filter_var($credentials['ticketing_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return $this->deny($expectedId, 'ticketing_feature_disabled', [
                'environment' => $connection->environment instanceof SupplierEnvironment
                    ? $connection->environment->value
                    : (string) $connection->environment,
                'protocol' => $protocol->value,
                'test_host' => $host,
                'service_target' => $serviceTarget,
            ]);
        }

        $supplierBooking = $booking->latestSupplierBooking;
        if ($supplierBooking === null || ! in_array((string) $supplierBooking->status, ['pending_ticketing', 'created'], true)) {
            return $this->deny($expectedId, 'supplier_booking_not_pending_ticketing');
        }

        $pnr = trim((string) ($booking->pnr ?? $booking->supplier_reference ?? ''));
        if ($pnr === '') {
            return $this->deny($expectedId, 'pnr_missing');
        }

        if (BookingTicket::query()->where('booking_id', $booking->id)->exists()) {
            return $this->deny($expectedId, 'booking_tickets_present');
        }

        $hasBlockingAttempt = TicketingAttempt::query()
            ->where('booking_id', $booking->id)
            ->whereIn('status', ['success', 'processing', 'uncertain', 'pending_reconciliation'])
            ->exists();
        if ($hasBlockingAttempt) {
            return $this->deny($expectedId, 'ticketing_attempt_blocking');
        }

        $ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        if (($ctx['ticketing_outcome'] ?? '') === 'uncertain' || ($ctx['ticketing_status'] ?? '') === 'ticketed') {
            return $this->deny($expectedId, 'airblue_ticketing_state_blocks');
        }
        if (is_array($ctx['ticket_numbers'] ?? null) && $ctx['ticket_numbers'] !== []) {
            return $this->deny($expectedId, 'ticket_numbers_present');
        }

        $config = $this->configResolver->resolveOta($connection);
        $seatRequired = (bool) ($ctx['seat_selection_required'] ?? true);
        $seatSatisfied = true;
        try {
            $this->seatSelectionGate->assertSeatSelectionSatisfied($config, $ctx, [
                'seats' => is_array($ctx['seats'] ?? null) ? $ctx['seats'] : [],
                'seat_selection_required' => $seatRequired,
            ]);
        } catch (\Throwable) {
            $seatSatisfied = false;
        }

        if (! $seatSatisfied) {
            return $this->deny($expectedId, 'seat_selection_required', [
                'environment' => $connection->environment instanceof SupplierEnvironment
                    ? $connection->environment->value
                    : (string) $connection->environment,
                'protocol' => $protocol->value,
                'test_host' => $host,
                'service_target' => $serviceTarget,
                'seat_selection_required' => true,
                'seat_gate_satisfied' => false,
            ]);
        }

        $env = $connection->environment instanceof SupplierEnvironment
            ? $connection->environment->value
            : (string) $connection->environment;

        return [
            'allowed' => true,
            'payment_bypass_only' => true,
            'reason' => 'ok',
            'connection_id' => (int) $connection->id,
            'provider' => SupplierProvider::Airblue->value,
            'environment' => $env,
            'protocol' => $protocol->value,
            'test_host' => $host,
            'service_target' => $serviceTarget,
            'seat_selection_required' => $seatRequired,
            'seat_gate_satisfied' => true,
            'lifecycle_path' => 'ticketing_service',
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{
     *     allowed: bool,
     *     payment_bypass_only: bool,
     *     reason: string,
     *     connection_id: int|null,
     *     provider: string|null,
     *     environment: string|null,
     *     protocol: string|null,
     *     test_host: string|null,
     *     service_target: string|null,
     *     seat_selection_required: bool,
     *     seat_gate_satisfied: bool,
     *     lifecycle_path: string
     * }
     */
    private function deny(int $expectedId, string $reason, array $extra = []): array
    {
        return array_merge([
            'allowed' => false,
            'payment_bypass_only' => false,
            'reason' => $reason,
            'connection_id' => $expectedId,
            'provider' => null,
            'environment' => null,
            'protocol' => null,
            'test_host' => null,
            'service_target' => null,
            'seat_selection_required' => (bool) ($extra['seat_selection_required'] ?? true),
            'seat_gate_satisfied' => (bool) ($extra['seat_gate_satisfied'] ?? false),
            'lifecycle_path' => 'ticketing_service',
        ], $extra);
    }

    private function resolveConnection(Booking $booking, int $expectedId): ?SupplierConnection
    {
        $id = (int) ($booking->latestSupplierBooking?->supplier_connection_id
            ?? data_get($booking->meta, 'supplier_connection_id')
            ?? $expectedId);

        return SupplierConnection::query()->find($id);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function resolveHost(SupplierConnection $connection, array $credentials): string
    {
        $candidates = [
            (string) ($connection->base_url ?? ''),
            (string) ($credentials['host'] ?? ''),
            (string) ($credentials['base_url'] ?? ''),
            (string) ($this->configResolver->resolveOta($connection)['endpoint_url'] ?? ''),
        ];
        foreach ($candidates as $raw) {
            $raw = trim($raw);
            if ($raw === '') {
                continue;
            }
            $host = strtolower((string) (parse_url($raw, PHP_URL_HOST) ?: ''));
            if ($host === '' && ! str_contains($raw, '/')) {
                $host = strtolower($raw);
            }
            if ($host !== '') {
                return $host;
            }
        }

        return '';
    }
}
