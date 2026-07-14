<?php

namespace App\Services\Suppliers\TicketingAdapters;

use App\Contracts\Suppliers\SupplierTicketingInterface;
use App\Data\TicketingResultData;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\User;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsTicketingReadiness;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsTicketingService;
use App\Services\Suppliers\SupplierDiagnosticLogger;
use App\Support\Platform\PlatformModuleEnforcer;

class SabreSupplierTicketingAdapter implements SupplierTicketingInterface
{
    public function __construct(
        private readonly SabreGdsTicketingService $ticketingService,
        private readonly SabreGdsTicketingReadiness $readiness,
        private readonly PlatformModuleEnforcer $platformModuleEnforcer,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
    ) {}

    public function issueTickets(Booking $booking, SupplierBooking $supplierBooking, User $actor): TicketingResultData
    {
        if ($supplierBooking->provider !== SupplierProvider::Sabre->value) {
            return $this->failure('supplier_provider_mismatch', 'Supplier provider mismatch for Sabre ticketing.');
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $distributionChannel = $this->platformModuleEnforcer->distributionChannelFromBookingMeta($meta);
        if ($this->platformModuleEnforcer->isSabreNdcDistributionChannel($distributionChannel)) {
            return $this->failure('sabre_ndc_channel', 'Use Sabre NDC order services for NDC channel bookings.');
        }

        if (! (bool) config('suppliers.sabre.ticketing_enabled', false)) {
            $this->diagnosticLogger->log(
                connection: $supplierBooking->supplierConnection,
                action: 'ticketing',
                status: 'blocked',
                safeMessage: 'Sabre ticketing is disabled in environment settings.',
            );

            return $this->failure('ticketing_disabled_by_config', 'Sabre ticketing is disabled in environment settings.');
        }

        $connection = $supplierBooking->supplierConnection;
        if ($connection === null) {
            return $this->failure('connection_missing', 'Sabre supplier connection is required for ticketing.');
        }

        $confirm = request()->input('ticketing_confirm');
        if (! SabreGdsTicketingReadiness::confirmPhraseMatches($booking, is_string($confirm) ? $confirm : null)) {
            return $this->failure(
                'exact_confirmation_required',
                'Exact confirmation phrase required: ISSUE-TICKET-FOR-BOOKING-'.$booking->id,
            );
        }

        return $this->ticketingService->issueTickets($booking, $connection, $actor, [
            'confirm' => is_string($confirm) ? $confirm : null,
        ]);
    }

    private function failure(string $code, string $message): TicketingResultData
    {
        return new TicketingResultData(
            success: false,
            status: in_array($code, ['ticketing_disabled_by_config', 'exact_confirmation_required'], true) ? 'blocked' : 'failed',
            provider: SupplierProvider::Sabre->value,
            error_code: $code,
            error_message: $message,
            safe_summary: ['live_supplier_call_attempted' => false],
        );
    }
}
