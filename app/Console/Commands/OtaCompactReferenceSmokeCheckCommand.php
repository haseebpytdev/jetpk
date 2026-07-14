<?php

namespace App\Console\Commands;

use App\Support\References\CompactReferenceGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OtaCompactReferenceSmokeCheckCommand extends Command
{
    protected $signature = 'ota:compact-reference-smoke-check';

    protected $description = 'Read-only smoke check for compact public reference generation (no records saved).';

    /** @var list<array{label: string, table: string, column: string, length: int, startsWith: string|null}> */
    private const TARGETS = [
        ['label' => 'booking', 'table' => 'bookings', 'column' => 'booking_reference', 'length' => 8, 'startsWith' => null],
        ['label' => 'group_booking', 'table' => 'group_bookings', 'column' => 'reference', 'length' => 8, 'startsWith' => null],
        ['label' => 'agent', 'table' => 'agents', 'column' => 'code', 'length' => 7, 'startsWith' => null],
        ['label' => 'payment', 'table' => 'booking_payments', 'column' => 'payment_reference', 'length' => 8, 'startsWith' => 'P'],
        ['label' => 'refund', 'table' => 'booking_refunds', 'column' => 'reference', 'length' => 8, 'startsWith' => 'R'],
        ['label' => 'support', 'table' => 'support_tickets', 'column' => 'ticket_reference', 'length' => 8, 'startsWith' => 'S'],
        ['label' => 'wallet', 'table' => 'agent_wallet_transactions', 'column' => 'reference', 'length' => 9, 'startsWith' => 'W'],
        ['label' => 'ledger', 'table' => 'ledger_transactions', 'column' => 'transaction_ref', 'length' => 10, 'startsWith' => 'L'],
    ];

    public function handle(CompactReferenceGenerator $generator): int
    {
        $failed = false;

        foreach (self::TARGETS as $target) {
            if (! Schema::hasTable($target['table'])) {
                $this->error("Missing table: {$target['table']}");
                $failed = true;

                continue;
            }

            if (! Schema::hasColumn($target['table'], $target['column'])) {
                $this->error("Missing column: {$target['table']}.{$target['column']}");
                $failed = true;

                continue;
            }

            $sample = $generator->generate($target['length'], $target['startsWith']);

            if (! $this->validateSample($sample, $target['length'])) {
                $this->error("Invalid sample for {$target['label']}: {$sample}");
                $failed = true;

                continue;
            }

            $this->line(sprintf(
                '%s: %s (length=%d)',
                $target['label'],
                $sample,
                $target['length'],
            ));
        }

        if ($failed) {
            return self::FAILURE;
        }

        if (! $this->verifyUniqueLookup($generator)) {
            return self::FAILURE;
        }

        $this->info('Compact reference smoke check passed.');

        return self::SUCCESS;
    }

    private function validateSample(string $sample, int $length): bool
    {
        if (str_contains($sample, '-') || str_contains($sample, '.') || str_contains($sample, ' ')) {
            return false;
        }

        if ($sample !== strtoupper($sample)) {
            return false;
        }

        if (preg_match('/^[A-Z2-9]{'.$length.'}$/', $sample) !== 1) {
            return false;
        }

        for ($i = 0; $i < strlen($sample); $i++) {
            if (strpos(CompactReferenceGenerator::ALPHABET, $sample[$i]) === false) {
                return false;
            }
        }

        return true;
    }

    private function verifyUniqueLookup(CompactReferenceGenerator $generator): bool
    {
        try {
            DB::beginTransaction();

            $ref = $generator->generateUnique('bookings', 'booking_reference', 8);

            if (! $generator->matchesCompactFormat($ref, 8)) {
                $this->error('generateUnique returned invalid format: '.$ref);
                DB::rollBack();

                return false;
            }

            DB::rollBack();
            $this->line('Uniqueness lookup: OK (rolled back, no row saved)');

            return true;
        } catch (Throwable $exception) {
            DB::rollBack();
            $this->error('Uniqueness lookup failed: '.$exception->getMessage());

            return false;
        }
    }
}
