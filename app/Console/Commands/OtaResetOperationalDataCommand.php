<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OtaResetOperationalDataCommand extends Command
{
    protected $signature = 'ota:reset-operational-data {--force : Skip confirmation prompt}';

    protected $description = 'Clear operational/test data while preserving reference/configuration data';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will delete operational booking data. Continue?')) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        $tables = [
            'booking_passengers',
            'booking_contacts',
            'booking_fare_breakdowns',
            'booking_status_logs',
            'booking_notes',
            'supplier_booking_attempts',
            'supplier_bookings',
            'booking_payments',
            'booking_tickets',
            'ticketing_attempts',
            'booking_documents',
            'guest_booking_access_tokens',
            'agent_wallet_transactions',
            'agent_deposit_requests',
            'agent_wallets',
            'agent_commission_entries',
            'agent_commission_statements',
            'booking_cancellation_requests',
            'booking_refunds',
            'communication_logs',
            'audit_logs',
            'supplier_diagnostic_logs',
            'bookings',
        ];

        $deleted = [];
        $skipped = [];

        Schema::disableForeignKeyConstraints();
        try {
            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    $skipped[] = $table;

                    continue;
                }

                $count = DB::table($table)->count();
                DB::table($table)->delete();
                $deleted[$table] = $count;
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->newLine();
        $this->info('Operational reset summary');
        foreach ($deleted as $table => $count) {
            $this->line("{$table}: {$count} rows deleted");
        }
        if ($skipped !== []) {
            $this->line('Skipped missing tables: '.implode(', ', $skipped));
        }
        $this->line('Preserved: airports, airlines, users, agencies, agency settings/media/homepage, supplier connections, markup rules.');

        return self::SUCCESS;
    }
}
