<?php

namespace App\Services\Suppliers;

use App\Contracts\Suppliers\SupplierTicketingInterface;
use App\Data\TicketingResultData;
use App\Enums\BookingStatus;
use App\Enums\OtaNotificationEvent;
use App\Enums\SupplierProvider;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Models\BookingTicket;
use App\Models\SupplierBooking;
use App\Models\TicketingAttempt;
use App\Models\User;
use App\Services\Agents\AgentCommissionService;
use App\Services\Booking\BookingOperationalPrecheckService;
use App\Services\Bookings\PiaNdcEticketDeliveryService;
use App\Services\Communication\BookingCommunicationService;
use App\Services\Finance\Ledger\LedgerEventRecorder;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsTicketingReadiness;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsTicketingService;
use App\Services\Suppliers\TicketingAdapters\AirBlueSupplierTicketingAdapter;
use App\Services\Suppliers\TicketingAdapters\AirlineDirectSupplierTicketingAdapter;
use App\Services\Suppliers\TicketingAdapters\DuffelSupplierTicketingAdapter;
use App\Services\Suppliers\TicketingAdapters\IatiSupplierTicketingAdapter;
use App\Services\Suppliers\TicketingAdapters\OneApiSupplierTicketingAdapter;
use App\Services\Suppliers\TicketingAdapters\PiaNdcSupplierTicketingAdapter;
use App\Services\Suppliers\TicketingAdapters\SabreSupplierTicketingAdapter;
use App\Support\Bookings\AdminBookingSupplierActionAuditor;
use App\Support\Bookings\AdminBookingSupplierActionGate;
use App\Support\Platform\PlatformModuleEnforcer;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketingService
{
    public function __construct(
        protected AgentCommissionService $agentCommissionService,
        protected BookingOperationalPrecheckService $operationalPrecheckService,
        protected BookingCommunicationService $communicationService,
        protected SabreSupplierTicketingAdapter $sabreAdapter,
        protected PiaNdcSupplierTicketingAdapter $piaNdcAdapter,
        protected AirBlueSupplierTicketingAdapter $airBlueAdapter,
        protected AirlineDirectSupplierTicketingAdapter $airlineDirectAdapter,
        protected DuffelSupplierTicketingAdapter $duffelAdapter,
        protected IatiSupplierTicketingAdapter $iatiAdapter,
        protected OneApiSupplierTicketingAdapter $oneApiAdapter,
        protected PiaNdcEticketDeliveryService $piaNdcEticketDeliveryService,
        protected PlatformModuleEnforcer $platformModuleEnforcer,
        protected AdminBookingSupplierActionGate $adminBookingSupplierActionGate,
        protected AdminBookingSupplierActionAuditor $adminBookingSupplierActionAuditor,
        protected SabreGdsTicketingReadiness $sabreGdsTicketingReadiness,
    ) {}

    public function isBookingEligibleForTicketing(Booking $booking): bool
    {
        if ($booking->status === BookingStatus::Ticketed || ($booking->ticketing_status ?? '') === 'ticketed') {
            return false;
        }

        if (($booking->payment_status ?? '') !== 'paid') {
            return false;
        }

        if (! in_array($booking->status, [BookingStatus::Paid, BookingStatus::TicketingPending], true)) {
            return false;
        }

        $supplierBooking = $booking->latestSupplierBooking;
        if ($supplierBooking === null) {
            return false;
        }

        if (! in_array($supplierBooking->status, ['pending_ticketing', 'created'], true)) {
            return false;
        }

        if (! (($booking->pnr ?? '') !== '' || ($booking->supplier_api_booking_id ?? '') !== '' || ($booking->supplier_reference ?? '') !== '')) {
            return false;
        }

        return $this->operationalPrecheckService->validatePassengerReadiness($booking) === [];
    }

    public function issueTickets(Booking $booking, User $actor, bool $adminManualOverride = false, bool $sabreGdsAdminIssue = false): TicketingResultData
    {
        $booking->loadMissing(['passengers', 'latestSupplierBooking']);

        $provider = strtolower(trim((string) ($booking->latestSupplierBooking?->provider ?? $booking->supplier ?? '')));
        $preTicketingStatus = (string) ($booking->ticketing_status ?? 'not_started');

        if ($booking->status === BookingStatus::Ticketed || ($booking->ticketing_status ?? '') === 'ticketed') {
            $this->logTicketingEvent('ticketing.already_ticketed', $booking, $actor, [
                'provider' => $provider !== '' ? $provider : 'unknown',
            ]);

            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: $provider !== '' ? $provider : 'unknown',
                error_code: 'already_ticketed',
                error_message: 'Tickets have already been issued for this booking.',
            );
        }

        if ($provider === SupplierProvider::Sabre->value && ! (bool) config('suppliers.sabre.ticketing_enabled', false)) {
            $this->logTicketingEvent('ticketing.disabled_by_env', $booking, $actor, [
                'provider' => $provider,
            ]);

            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: $provider,
                error_code: 'ticketing_disabled_by_config',
                error_message: 'Sabre ticketing is disabled in environment settings.',
            );
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $distributionChannel = $this->platformModuleEnforcer->distributionChannelFromBookingMeta($meta);
        $moduleBlock = $this->platformModuleEnforcer->ticketingBlockedMessage($provider !== '' ? $provider : null, $distributionChannel);
        if ($moduleBlock !== null) {
            $this->logTicketingEvent('ticketing.disabled_by_module', $booking, $actor, [
                'provider' => $provider !== '' ? $provider : 'unknown',
            ]);

            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: $provider !== '' ? $provider : 'unknown',
                error_code: 'platform_module_disabled',
                error_message: 'Ticketing is disabled for this deployment.',
            );
        }

        $allowManualPiaNdc = false;
        if ($provider === SupplierProvider::PiaNdc->value && $adminManualOverride) {
            $manual = $this->adminBookingSupplierActionGate->piaNdcManualTicketing(
                $booking,
                $this->isBookingEligibleForTicketing($booking),
            );
            $allowManualPiaNdc = (bool) ($manual['can_manual_issue'] ?? false);
        }

        $allowSabreGdsAdmin = false;
        if ($provider === SupplierProvider::Sabre->value && $sabreGdsAdminIssue) {
            $gdsReadiness = $this->sabreGdsTicketingReadiness->evaluate($booking, [
                'require_confirmation' => true,
                'confirmation_provided' => SabreGdsTicketingReadiness::confirmPhraseMatches(
                    $booking,
                    is_string(request()->input('ticketing_confirm')) ? request()->input('ticketing_confirm') : null,
                ),
            ]);
            $allowSabreGdsAdmin = ($gdsReadiness['action_state'] ?? '') === SabreGdsTicketingReadiness::ACTION_ISSUE_TICKET
                && ($gdsReadiness['can_execute'] ?? false)
                && ($gdsReadiness['live_supplier_call_allowed'] ?? false);
        }

        if (! $allowManualPiaNdc && ! $allowSabreGdsAdmin && ! $this->isBookingEligibleForTicketing($booking)) {
            $precheckErrors = $this->operationalPrecheckService->validatePassengerReadiness($booking);
            $this->logTicketingEvent('ticketing.not_ready', $booking, $actor, [
                'provider' => (string) ($booking->supplier ?? 'unknown'),
                'reason_code' => $precheckErrors !== [] ? 'passenger_readiness' : 'booking_not_eligible',
            ]);

            $pnrMissing = ! (($booking->pnr ?? '') !== '' || ($booking->supplier_api_booking_id ?? '') !== '' || ($booking->supplier_reference ?? '') !== '');

            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: (string) ($booking->supplier ?? 'unknown'),
                error_code: $pnrMissing ? 'pnr_missing' : 'booking_not_eligible',
                error_message: $pnrMissing
                    ? 'Create or attach PNR before ticketing.'
                    : ($precheckErrors !== []
                        ? 'Booking is not eligible for ticketing: '.$precheckErrors[0]
                        : 'Booking is not eligible for ticketing.'),
                warnings: $precheckErrors,
            );
        }

        $supplierBooking = $booking->latestSupplierBooking;
        if ($supplierBooking === null) {
            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: (string) ($booking->supplier ?? 'unknown'),
                error_code: 'supplier_booking_missing',
                error_message: 'Supplier booking is required before ticketing.',
            );
        }

        $attempt = TicketingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_booking_id' => $supplierBooking->id,
            'provider' => $supplierBooking->provider,
            'status' => 'processing',
            'attempted_by' => $actor->id,
            'attempted_at' => now(),
        ]);

        $result = $this->resolveAdapter($supplierBooking)->issueTickets($booking, $supplierBooking, $actor);

        $result = DB::transaction(function () use ($booking, $supplierBooking, $attempt, $result, $actor): TicketingResultData {
            if (! $result->success) {
                $attempt->forceFill([
                    'status' => $result->status === 'not_supported' ? 'not_supported' : 'failed',
                    'request_payload' => SensitiveDataRedactor::redact($result->request_payload),
                    'response_payload' => SensitiveDataRedactor::redact($result->response_payload),
                    'safe_summary' => SensitiveDataRedactor::redact($result->safe_summary),
                    'error_code' => $result->error_code,
                    'error_message' => $result->error_message ?: ($result->warnings[0] ?? 'Ticketing failed.'),
                    'completed_at' => now(),
                ])->save();

                $this->writeAudit($booking, $actor, 'booking.ticketing_failed', [
                    'attempt_id' => $attempt->id,
                    'provider' => $result->provider,
                    'status' => $result->status,
                ]);
                $failureType = $result->status === 'not_supported'
                    ? OtaNotificationEvent::TicketingNotSupported->value
                    : OtaNotificationEvent::TicketingFailed->value;
                $this->communicationService->notifyTicketingFailure(
                    $booking,
                    $failureType,
                    $actor,
                    [
                        'ticketing_attempt_id' => $attempt->id,
                        'provider' => $result->provider,
                        'error_code' => $result->error_code,
                        'failure_reason' => $result->error_message,
                        'supplier_booking_status' => (string) ($booking->supplier_booking_status ?? ''),
                        'ticketing_status' => (string) ($booking->ticketing_status ?? ''),
                    ],
                );

                return $result;
            }

            foreach ($result->tickets as $ticketData) {
                $ticket = BookingTicket::query()->create([
                    'agency_id' => $booking->agency_id,
                    'booking_id' => $booking->id,
                    'supplier_booking_id' => $supplierBooking->id,
                    'passenger_id' => $ticketData['passenger_id'] ?? null,
                    'ticket_number' => $ticketData['ticket_number'] ?? null,
                    'pnr' => $ticketData['pnr'] ?? ($booking->pnr ?? null),
                    'provider' => $result->provider,
                    'airline_code' => $ticketData['airline_code'] ?? null,
                    'status' => 'issued',
                    'issued_by' => $actor->id,
                    'issued_at' => isset($ticketData['issued_at']) ? $ticketData['issued_at'] : now(),
                    'raw_summary' => $ticketData,
                    'meta' => SensitiveDataRedactor::redact(['passenger_name' => $ticketData['passenger_name'] ?? null]),
                ]);

                if ($booking->agent_id !== null) {
                    $this->agentCommissionService->generateCommissionForTicket($ticket);
                }
            }

            $attempt->forceFill([
                'status' => 'success',
                'request_payload' => SensitiveDataRedactor::redact($result->request_payload),
                'response_payload' => SensitiveDataRedactor::redact($result->response_payload),
                'safe_summary' => SensitiveDataRedactor::redact($result->safe_summary),
                'completed_at' => now(),
            ])->save();

            $previousStatus = $booking->status;
            $issuedTicketCount = count($result->tickets);
            $resolvedTicketingStatus = $result->provider === SupplierProvider::PiaNdc->value && $issuedTicketCount === 0
                ? 'ticketing_requires_review'
                : 'ticketed';
            $booking->forceFill([
                'status' => $resolvedTicketingStatus === 'ticketed' ? BookingStatus::Ticketed : $booking->status,
                'ticketing_status' => $resolvedTicketingStatus,
                'ticketed_at' => $resolvedTicketingStatus === 'ticketed' ? now() : $booking->ticketed_at,
                'supplier_booking_status' => $resolvedTicketingStatus === 'ticketed' ? 'ticketed' : $booking->supplier_booking_status,
            ])->save();

            $supplierBooking->forceFill([
                'status' => $resolvedTicketingStatus === 'ticketed' ? 'ticketed' : $supplierBooking->status,
            ])->save();

            BookingStatusLog::query()->create([
                'booking_id' => $booking->id,
                'from_status' => $previousStatus->value,
                'to_status' => BookingStatus::Ticketed->value,
                'user_id' => $actor->id,
                'note' => 'Tickets issued',
                'context' => [
                    'source' => 'ticketing_service',
                    'supplier_booking_id' => $supplierBooking->id,
                ],
            ]);

            $this->writeAudit($booking, $actor, 'booking.tickets_issued', [
                'attempt_id' => $attempt->id,
                'provider' => $result->provider,
                'tickets_count' => count($result->tickets),
            ]);

            if ($result->provider === SupplierProvider::Sabre->value && $resolvedTicketingStatus === 'ticketed') {
                app(SabreGdsTicketingService::class)->finalizeSuccessfulIssue(
                    $booking->fresh() ?? $booking,
                    $actor,
                    [
                        'tickets' => $result->tickets,
                        'safe_summary' => is_array($result->safe_summary) ? $result->safe_summary : [],
                    ],
                    [
                        'supplier_connection_id' => $supplierBooking->supplier_connection_id,
                    ],
                );
            }

            if ($result->provider === SupplierProvider::PiaNdc->value) {
                $this->piaNdcEticketDeliveryService->deliverAfterSuccessfulTicketing($booking->fresh(), $actor);
            } else {
                $this->communicationService->sendTicketIssued($booking->fresh());
            }

            return $result;
        });

        if ($result->success) {
            app(LedgerEventRecorder::class)->recordMarkupRevenueForBooking($booking->fresh(), $actor);
        }

        if ($adminManualOverride && $provider === SupplierProvider::PiaNdc->value) {
            $fresh = $booking->fresh() ?? $booking;
            $this->adminBookingSupplierActionAuditor->log(
                $fresh,
                $actor,
                'issue_ticket',
                true,
                $preTicketingStatus,
                (string) ($fresh->ticketing_status ?? $preTicketingStatus),
                true,
                $result->success ? 'success' : (string) ($result->status ?? 'failed'),
                $result->success ? null : ($result->error_message ?: null),
                ['admin_manual_override' => true],
            );
        }

        return $result;
    }

    protected function resolveAdapter(SupplierBooking $supplierBooking): SupplierTicketingInterface
    {
        $provider = SupplierProvider::tryFrom($supplierBooking->provider);

        return match ($provider) {
            SupplierProvider::Sabre => $this->sabreAdapter,
            SupplierProvider::Duffel => $this->duffelAdapter,
            SupplierProvider::PiaNdc => $this->piaNdcAdapter,
            SupplierProvider::Airblue => $this->airBlueAdapter,
            SupplierProvider::AirlineDirect => $this->airlineDirectAdapter,
            SupplierProvider::Iati => $this->iatiAdapter,
            SupplierProvider::OneApi => $this->oneApiAdapter,
            default => throw new \InvalidArgumentException('Ticketing adapter not configured for provider: '.($provider?->value ?? 'unknown')),
        };
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    /**
     * @param  array<string, mixed>  $context
     */
    protected function logTicketingEvent(string $event, Booking $booking, User $actor, array $context = []): void
    {
        Log::info($event, array_merge([
            'booking_id' => $booking->id,
            'agency_id' => $booking->agency_id,
            'actor_id' => $actor->id,
            'booking_status' => $booking->status->value,
            'payment_status' => (string) ($booking->payment_status ?? ''),
            'ticketing_status' => (string) ($booking->ticketing_status ?? ''),
        ], $context));
    }

    protected function writeAudit(Booking $booking, User $actor, string $action, array $newValues): void
    {
        AuditLog::query()->create([
            'agency_id' => $booking->agency_id,
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => Booking::class,
            'auditable_id' => $booking->id,
            'properties' => [
                'old_values' => [],
                'new_values' => $newValues,
            ],
        ]);
    }
}
