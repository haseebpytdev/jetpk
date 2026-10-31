<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GuardsAirBlueLiveNetwork;
use App\Enums\AccountType;
use App\Models\Booking;
use App\Models\User;
use App\Services\Suppliers\TicketingService;
use App\Support\Bookings\AirBlueQaTicketingScope;
use App\Support\FlightSearch\QaStaffAirBlueContext;
use Illuminate\Console\Command;

/**
 * Canonical QA ticketing entry: TicketingService only (never AirBlueTicketingService).
 *
 * --allow-live acknowledges outbound intent; sandbox/conn/protocol still enforced by
 * {@see AirBlueQaTicketingScope}.
 */
class AirBlueQaTestTicketingCommand extends Command
{
    use GuardsAirBlueLiveNetwork;

    protected $signature = 'airblue:qa-test-ticketing
        {booking : OTA booking ID}
        {--actor= : Platform admin / staff username}
        {--dry-run : Evaluate QA scope + eligibility only; no TicketingService issue}
        {--allow-live : Explicit operator acknowledgement for a live Zapways ticket call}';

    protected $description = 'Issue AirBlue sandbox QA tickets via canonical TicketingService (live-gated)';

    public function handle(TicketingService $ticketingService, AirBlueQaTicketingScope $qaScope): int
    {
        $booking = Booking::query()->with(['latestSupplierBooking', 'passengers'])->find((int) $this->argument('booking'));
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        $actor = $this->resolveActor();
        if ($actor === null) {
            $this->error('Authorized QA actor not found (platform admin or staff).');

            return self::FAILURE;
        }

        $scope = $qaScope->evaluate($booking, $actor);
        $this->line('qa_scope_allowed='.($scope['allowed'] ? 'true' : 'false'));
        $this->line('qa_scope_reason='.$scope['reason']);
        $this->line('seat_gate_satisfied='.(($scope['seat_gate_satisfied'] ?? false) ? 'true' : 'false'));
        $this->line('canonical_eligible='.($ticketingService->isBookingEligibleForTicketing($booking) ? 'true' : 'false'));
        $this->line('lifecycle_path=ticketing_service');

        if ($this->option('dry-run')) {
            $this->info('DRY_RUN=YES (no TicketingService issueTickets call)');

            return ($scope['allowed'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        if (! ($scope['allowed'] ?? false)) {
            $this->error('QA ticketing scope denied: '.$scope['reason']);

            return self::FAILURE;
        }

        if (! $this->guardAirBlueLiveNetwork('airblue:qa-test-ticketing')) {
            return self::FAILURE;
        }

        $result = $ticketingService->issueTickets(
            $booking->fresh(['latestSupplierBooking', 'passengers']),
            $actor,
            false,
            false,
            true, // allowControlledQaAirBlueTicketing
        );

        $this->line('success='.($result->success ? 'true' : 'false'));
        $this->line('status='.$result->status);
        $this->line('error_code='.(string) ($result->error_code ?? ''));

        return $result->success ? self::SUCCESS : self::FAILURE;
    }

    protected function resolveActor(): ?User
    {
        $username = trim((string) $this->option('actor'));
        $query = User::query()->orderBy('id');
        if ($username !== '') {
            $query->where('username', $username);
        }

        $actor = $query->first();
        if ($actor === null || ! QaStaffAirBlueContext::actorIsAllowed($actor)) {
            return User::query()
                ->whereIn('account_type', [AccountType::PlatformAdmin, AccountType::Staff])
                ->orderBy('id')
                ->get()
                ->first(fn (User $u): bool => QaStaffAirBlueContext::actorIsAllowed($u));
        }

        return $actor;
    }
}
