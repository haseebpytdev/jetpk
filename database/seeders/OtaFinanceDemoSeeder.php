<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Finance\OtaFinanceDemoScenario;
use Illuminate\Database\Seeder;

/**
 * Local finance demo dataset for manual QA (3 agencies, wallets, bookings, payments).
 *
 * Run: php artisan db:seed --class=OtaFinanceDemoSeeder
 *
 * Uses dedicated @finance-demo.test emails; safe alongside OtaFoundationSeeder on dev DBs.
 */
class OtaFinanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->where('email', 'platform-admin@finance-demo.test')->exists()) {
            $this->command?->warn('OtaFinanceDemoSeeder: finance demo users already exist — skipping.');

            return;
        }

        $scenario = (new OtaFinanceDemoScenario)->build();

        $this->command?->info('OtaFinanceDemoSeeder: created finance demo scenario.');
        $this->command?->line('  Platform admin: platform-admin@finance-demo.test / password');
        $this->command?->line('  Staff (finance): staff-finance@finance-demo.test / password');
        $this->command?->line('  Staff (ops): staff-ops@finance-demo.test / password');
        $this->command?->line('  ET owner: et-owner@finance-demo.test / password');
        $this->command?->line('  JP owner: jp-owner@finance-demo.test / password');
        $this->command?->line('  DT owner: dt-owner@finance-demo.test / password');
        $this->command?->line('  Expected platform wallet exposure: Rs '.number_format(OtaFinanceDemoScenario::PLATFORM_LEDGER['agency_wallet_exposure'], 2));
    }
}
