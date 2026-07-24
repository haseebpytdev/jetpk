<?php

namespace App\Console\Commands;

use App\Support\Sabre\Scenario\SabreGdsQrUnticketedBookAndRetrieveLifecycle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * QR unticketed book-and-retrieve lifecycle — plan (default, zero supplier calls) or controlled live execution.
 */
class SabreGdsQrUnticketedBookAndRetrieveCommand extends Command
{
    protected $signature = 'sabre:gds-qr-unticketed-book-and-retrieve
                            {--connection=1 : Sabre supplier connection ID}
                            {--origin=LHE : Origin IATA}
                            {--destination=JED : Destination IATA}
                            {--departure-date= : Departure date YYYY-MM-DD (required)}
                            {--preset=qr-connecting : Must remain qr-connecting}
                            {--candidate-index=0 : Eligible offer index}
                            {--passenger-json= : Absolute path to private passenger JSON (required)}
                            {--lifecycle-run-id= : Optional lifecycle id for idempotency inspection}
                            {--plan : Plan mode (default when --send omitted)}
                            {--send : Execute live search, revalidation, one PNR create, one retrieve}
                            {--confirm-production= : Production send: APPROVE-LIVE-SABRE-GDS-UNTICKETED-BOOK-AND-RETRIEVE}
                            {--confirm-pnr-create= : Send: LIVE-SABRE-GDS-CREATE-ONE-UNTICKETED-PNR}
                            {--confirm-no-ticketing= : Send: CONFIRM-SABRE-TICKETING-DISABLED}';

    protected $description = '[operator] QR unticketed Sabre GDS book-and-retrieve — plan default; live requires explicit production confirmations';

    public function handle(SabreGdsQrUnticketedBookAndRetrieveLifecycle $lifecycle): int
    {
        $send = $this->option('send') === true;
        $departureDate = trim((string) ($this->option('departure-date') ?? ''));
        if ($departureDate === '') {
            $this->components->error('--departure-date=YYYY-MM-DD is required.');

            return self::FAILURE;
        }

        $lock = Cache::lock('sabre_gds_qr_unticketed_book_and_retrieve_command', 30);
        if (! $lock->get()) {
            $this->components->error('Command duplicate protection lock active.');

            return self::FAILURE;
        }

        try {
            $result = $lifecycle->run([
                'send' => $send,
                'connection_id' => (int) $this->option('connection'),
                'origin' => (string) $this->option('origin'),
                'destination' => (string) $this->option('destination'),
                'departure_date' => $departureDate,
                'preset' => strtolower(trim((string) $this->option('preset'))),
                'candidate_index' => (int) $this->option('candidate-index'),
                'passenger_json' => trim((string) ($this->option('passenger-json') ?? '')),
                'lifecycle_run_id' => trim((string) ($this->option('lifecycle-run-id') ?? '')),
                'confirm_production' => trim((string) ($this->option('confirm-production') ?? '')),
                'confirm_pnr_create' => trim((string) ($this->option('confirm-pnr-create') ?? '')),
                'confirm_no_ticketing' => trim((string) ($this->option('confirm-no-ticketing') ?? '')),
            ]);

            $this->printResult($result, $send);

            return isset($result['error']) ? self::FAILURE : self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function printResult(array $result, bool $send): void
    {
        $this->line('lifecycle_run_id='.($result['lifecycle_run_id'] ?? ''));
        $this->line('command_mode='.SabreGdsQrUnticketedBookAndRetrieveLifecycle::MODE);
        $this->line('probe_mode='.($send ? 'send' : 'plan'));
        $this->line('artifact_path='.($result['artifact_path'] ?? ''));
        $this->line('ticketing_attempted=false');
        $this->line('airticket_attempted=false');
        $this->line('cancellation_planned=false');

        if (isset($result['operation_plan']) && is_array($result['operation_plan'])) {
            foreach ($result['operation_plan'] as $key => $value) {
                if (is_bool($value)) {
                    $this->line($key.'='.($value ? 'true' : 'false'));
                } else {
                    $this->line($key.'='.$value);
                }
            }
        }

        if (isset($result['error'])) {
            $this->components->error('Lifecycle blocked: '.(string) $result['error']);
            if (is_array($result['gate']['reasons'] ?? null)) {
                foreach ($result['gate']['reasons'] as $reason) {
                    $this->line('gate_reason='.$reason);
                }
            }
        }
    }
}
