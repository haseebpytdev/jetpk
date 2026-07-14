<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Models\User;
use Illuminate\Console\Command;

class OtaPrepareE2eCommand extends Command
{
    protected $signature = 'ota:prepare-e2e';

    protected $description = 'Validate existing local/testing OTA E2E prerequisites without changing data.';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('ota:prepare-e2e is restricted to local/testing environments.');

            return self::FAILURE;
        }

        $agency = Agency::query()->where('slug', 'asif-travels')->first();
        $admin = User::query()->where('email', 'admin@ota.demo')->first();

        if ($agency === null || $admin === null) {
            $this->error('E2E prerequisites missing. Command is read-only and will not seed/create data.');
            $this->line('Required existing records: agency slug "asif-travels" and user "admin@ota.demo".');

            return self::FAILURE;
        }

        $duffel = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Duffel)
            ->first();
        if ($duffel === null || ! $duffel->isActive()) {
            $this->error('Duffel connection is missing or inactive. Command is read-only and will not auto-configure suppliers.');

            return self::FAILURE;
        }

        $credentials = is_array($duffel->credentials ?? null) ? $duffel->credentials : [];
        if (trim((string) ($credentials['access_token'] ?? '')) === '') {
            $this->error('Duffel access token is missing. Command is read-only and will not write credentials.');

            return self::FAILURE;
        }

        $this->info('E2E prerequisites found (Duffel active). No data changes were made.');

        return self::SUCCESS;
    }
}
