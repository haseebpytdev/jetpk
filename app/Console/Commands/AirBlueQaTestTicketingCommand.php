<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GuardsAirBlueLiveNetwork;
use App\Enums\AccountType;
use App\Models\Booking;
use App\Models\SupplierConnection;
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
        {--temporary-enable-ticketing : Temporarily enable conn23 ticketing for this command and restore the exact prior value in finally}
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

        $temporaryEnable = (bool) $this->option('temporary-enable-ticketing');

        if ($this->option('dry-run') && $temporaryEnable) {
            $this->error('--temporary-enable-ticketing is not permitted with --dry-run.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $scope = $qaScope->evaluate($booking, $actor);
            $this->printScope($scope, $ticketingService, $booking);
            $this->info('DRY_RUN=YES (no TicketingService issueTickets call)');

            return ($scope['allowed'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        if (! $this->guardAirBlueLiveNetwork('airblue:qa-test-ticketing')) {
            return self::FAILURE;
        }

        if (! $temporaryEnable) {
            $scope = $qaScope->evaluate($booking, $actor);
            $this->printScope($scope, $ticketingService, $booking);
            if (! ($scope['allowed'] ?? false)) {
                $this->error('QA ticketing scope denied: '.$scope['reason']);

                return self::FAILURE;
            }

            return $this->issueOnce($ticketingService, $booking, $actor);
        }

        $connection = $this->resolvePinnedConnection($booking);
        if ($connection === null) {
            $this->error('Pinned AirBlue QA connection not found.');

            return self::FAILURE;
        }

        $preEnableScope = $qaScope->evaluateForTemporaryEnablement($booking, $actor, $connection);
        $this->line('pre_enable_scope_allowed='.(($preEnableScope['allowed'] ?? false) ? 'true' : 'false'));
        $this->line('pre_enable_scope_reason='.(string) ($preEnableScope['reason'] ?? 'denied'));
        if (! ($preEnableScope['allowed'] ?? false)) {
            $this->error('QA pre-enable scope denied: '.(string) ($preEnableScope['reason'] ?? 'denied'));

            return self::FAILURE;
        }

        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $originalKeyPresent = array_key_exists('ticketing_enabled', $credentials);
        $originalValue = $credentials['ticketing_enabled'] ?? null;

        $credentials['ticketing_enabled'] = true;
        $connection->forceFill(['credentials' => $credentials])->save();
        $this->line('temporary_ticketing_enabled=true');

        try {
            $freshBooking = $booking->fresh(['latestSupplierBooking', 'passengers']) ?? $booking;
            $freshConnection = $connection->fresh() ?? $connection;
            $scope = $qaScope->evaluate($freshBooking, $actor, $freshConnection);
            $this->printScope($scope, $ticketingService, $freshBooking);
            if (! ($scope['allowed'] ?? false)) {
                $this->error('QA ticketing scope denied after temporary enable: '.(string) ($scope['reason'] ?? 'denied'));

                return self::FAILURE;
            }

            return $this->issueOnce($ticketingService, $freshBooking, $actor);
        } finally {
            $restoreConnection = SupplierConnection::query()->find($connection->id);
            if ($restoreConnection !== null) {
                $restoreCredentials = is_array($restoreConnection->credentials)
                    ? $restoreConnection->credentials
                    : [];
                if ($originalKeyPresent) {
                    $restoreCredentials['ticketing_enabled'] = $originalValue;
                } else {
                    unset($restoreCredentials['ticketing_enabled']);
                }
                $restoreConnection->forceFill(['credentials' => $restoreCredentials])->save();
                $this->line('ticketing_flag_restored=true');
            } else {
                $this->error('ticketing_flag_restored=false');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function printScope(array $scope, TicketingService $ticketingService, Booking $booking): void
    {
        $this->line('qa_scope_allowed='.(($scope['allowed'] ?? false) ? 'true' : 'false'));
        $this->line('qa_scope_reason='.(string) ($scope['reason'] ?? 'denied'));
        $this->line('seat_gate_satisfied='.(($scope['seat_gate_satisfied'] ?? false) ? 'true' : 'false'));
        $this->line('canonical_eligible='.($ticketingService->isBookingEligibleForTicketing($booking) ? 'true' : 'false'));
        $this->line('lifecycle_path=ticketing_service');
    }

    private function issueOnce(TicketingService $ticketingService, Booking $booking, User $actor): int
    {
        $result = $ticketingService->issueTickets(
            $booking,
            $actor,
            false,
            false,
            true, // allowControlledQaAirBlueTicketing
        );

        $this->line('success='.($result->success ? 'true' : 'false'));
        $this->line('status='.$result->status);
        $this->line('error_code='.(string) ($result->error_code ?? ''));
        $this->line('outcome='.(string) ($result->outcome ?? ''));
        $this->line('provider_calls='.(string) ($result->provider_calls ?? 0));
        $this->line('ticket_count='.count($result->tickets));

        return $result->success ? self::SUCCESS : self::FAILURE;
    }

    private function resolvePinnedConnection(Booking $booking): ?SupplierConnection
    {
        $expectedId = (int) config('ota.qa_staff_airblue.connection_id', 23);
        $connectionId = (int) ($booking->latestSupplierBooking?->supplier_connection_id
            ?? data_get($booking->meta, 'supplier_connection_id')
            ?? 0);

        if ($connectionId !== $expectedId) {
            return null;
        }

        return SupplierConnection::query()->find($connectionId);
    }

    protected function resolveActor(): ?User
    {
        $username = trim((string) $this->option('actor'));

        if ($username !== '') {
            $actor = User::query()->where('username', $username)->first();

            return $actor !== null && QaStaffAirBlueContext::actorIsAllowed($actor)
                ? $actor
                : null;
        }

        return User::query()
            ->whereIn('account_type', [AccountType::PlatformAdmin, AccountType::Staff])
            ->orderBy('id')
            ->get()
            ->first(fn (User $u): bool => QaStaffAirBlueContext::actorIsAllowed($u));
    }
}
