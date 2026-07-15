<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Enums\MarkupRuleStatus;
use App\Enums\MarkupRuleType;
use App\Enums\MarkupValueType;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\AgencySetting;
use App\Models\MarkupRule;
use App\Models\SupplierConnection;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OtaPrepareDuffelTestCommand extends Command
{
    protected $signature = 'ota:prepare-duffel-test {--agency=asif-travels}';

    protected $description = 'Prepare clean agency baseline for Duffel live/sandbox testing';

    public function handle(): int
    {
        $agencyInput = (string) $this->option('agency');
        $agency = $this->resolveAgency($agencyInput);
        if ($agency === null) {
            $this->error("Agency not found for input '{$agencyInput}'.");

            return self::FAILURE;
        }

        AgencySetting::query()->firstOrCreate(
            ['agency_id' => $agency->id],
            [
                'display_name' => $agency->name,
                'support_phone' => config('ota-brand.support_phone'),
                'support_whatsapp' => config('ota-brand.support_whatsapp'),
                'support_email' => config('ota-brand.support_email'),
                'currency' => 'PKR',
                'timezone' => $agency->timezone ?? 'Asia/Karachi',
                'primary_color' => '#2563eb',
                'secondary_color' => '#1e40af',
                'accent_color' => '#0ea5e9',
            ]
        );

        $admin = User::query()
            ->where('current_agency_id', $agency->id)
            ->where('account_type', AccountType::AgencyAdmin)
            ->first();
        if ($admin === null) {
            $admin = User::query()->create([
                'name' => $agency->name.' Admin',
                'email' => 'admin+'.$agency->slug.'@example.com',
                'password' => 'password',
                'current_agency_id' => $agency->id,
                'account_type' => AccountType::AgencyAdmin,
                'status' => 'active',
            ]);
        }

        $duffelRows = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Duffel)
            ->count();

        $activeGlobalRule = MarkupRule::query()
            ->where('agency_id', $agency->id)
            ->where('status', MarkupRuleStatus::Active->value)
            ->where('rule_type', MarkupRuleType::Global->value)
            ->where('value_type', MarkupValueType::Percentage->value)
            ->first();

        if ($activeGlobalRule === null) {
            MarkupRule::query()
                ->where('agency_id', $agency->id)
                ->where('status', MarkupRuleStatus::Active->value)
                ->update([
                    'status' => MarkupRuleStatus::Inactive->value,
                    'updated_at' => now(),
                ]);

            MarkupRule::query()->create([
                'agency_id' => $agency->id,
                'name' => 'Default global markup',
                'rule_type' => MarkupRuleType::Global,
                'value_type' => MarkupValueType::Percentage,
                'value' => 3.5,
                'priority' => 100,
                'status' => MarkupRuleStatus::Active,
                'is_active' => true,
                'applies_to' => null,
                'meta' => ['bucket' => 'admin_markup'],
            ]);
        }

        DB::table('supplier_connections')
            ->where('agency_id', $agency->id)
            ->whereNull('provider')
            ->update([
                'status' => SupplierConnectionStatus::Inactive->value,
                'is_active' => false,
                'updated_at' => now(),
            ]);

        $this->info('Duffel test baseline prepared.');
        $this->line("Agency: {$agency->name} ({$agency->slug})");
        $this->line("Agency admin: {$admin->email}");
        $this->line("Duffel connections found: {$duffelRows}");
        $this->newLine();
        $this->info('Next steps');
        $this->line('1. Login to admin');
        $this->line('2. Go to API Settings');
        $this->line('3. Create or edit Duffel supplier');
        $this->line('4. Add access_token');
        $this->line('5. Set status to active');
        $this->line('6. Search flights');

        return self::SUCCESS;
    }

    protected function resolveAgency(string $agencyInput): ?Agency
    {
        $agency = Agency::query()
            ->where('slug', $agencyInput)
            ->orWhereRaw('LOWER(name) = ?', [Str::lower($agencyInput)])
            ->first();
        if ($agency !== null) {
            return $agency;
        }

        return Agency::query()->where('slug', config('ota.default_agency_slug'))->first();
    }
}
