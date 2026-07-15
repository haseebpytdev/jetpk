<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\MarkupRuleStatus;
use App\Enums\MarkupRuleType;
use App\Enums\MarkupValueType;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\MarkupRule;
use App\Models\StaffProfile;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Support\Suppliers\LegacySupplierProviderDataRepair;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Foundation demo data for local/testing. Supplier API credentials are admin-managed —
 * {@see seedSupplierConnectionPlaceholders()} runs only in local/testing when explicitly enabled.
 */
class OtaFoundationSeeder extends Seeder
{
    /** When false, never creates Duffel/Sabre/PIA/Airline Direct placeholder rows (route health audits). */
    public bool $seedSupplierConnectionPlaceholders = true;

    public function run(): void
    {
        LegacySupplierProviderDataRepair::repairPiaProviderRows();

        $this->call(AirportAirlineReferenceSeeder::class);

        $platformName = (string) config('ota-brand.name', config('app.name'));

        $agency = Agency::query()->updateOrCreate(
            ['slug' => 'asif-travels'],
            [
                'name' => $platformName,
                'timezone' => 'Asia/Karachi',
                'settings' => [
                    'domain' => 'ota.haseebasif.com',
                ],
            ],
        );

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@ota.demo'],
            [
                'name' => 'Asif Admin',
                'username' => 'admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'current_agency_id' => $agency->id,
                // Legacy account type — blocked from /admin; tests needing platform admin must use platform_admin.
                'account_type' => AccountType::AgencyAdmin,
            ],
        );

        $staffUser = User::query()->updateOrCreate(
            ['email' => 'staff@ota.demo'],
            [
                'name' => 'Asif Staff',
                'username' => 'staff',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'current_agency_id' => $agency->id,
                'account_type' => AccountType::Staff,
            ],
        );

        $agentUser = User::query()->updateOrCreate(
            ['email' => 'agent@ota.demo'],
            [
                'name' => 'Asif Agent',
                'username' => 'agent',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'current_agency_id' => $agency->id,
                'account_type' => AccountType::Agent,
            ],
        );

        $customerUser = User::query()->updateOrCreate(
            ['email' => 'customer@ota.demo'],
            [
                'name' => 'Asif Customer',
                'username' => 'customer',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'current_agency_id' => $agency->id,
                'account_type' => AccountType::Customer,
            ],
        );

        $admin->agencies()->syncWithoutDetaching([
            $agency->id => ['role' => 'agency_admin'],
        ]);
        $staffUser->agencies()->syncWithoutDetaching([
            $agency->id => ['role' => 'staff'],
        ]);
        $agentUser->agencies()->syncWithoutDetaching([
            $agency->id => ['role' => 'agent'],
        ]);
        $customerUser->agencies()->syncWithoutDetaching([
            $agency->id => ['role' => 'customer'],
        ]);

        StaffProfile::query()->updateOrCreate(
            [
                'agency_id' => $agency->id,
                'user_id' => $staffUser->id,
            ],
            [
                'job_title' => 'Operations Lead',
                'department' => 'Operations',
                'is_active' => true,
            ],
        );

        Agent::query()->updateOrCreate(
            [
                'agency_id' => $agency->id,
                'user_id' => $agentUser->id,
            ],
            [
                'code' => 'AGT-ASIF-001',
                'commission_percent' => 7.5,
                'is_active' => true,
                'meta' => ['tier' => 'gold'],
            ],
        );

        $this->seedDemoAgents($agency);
        if ($this->shouldSeedSupplierConnectionPlaceholders()) {
            $this->seedSupplierConnectionPlaceholders($agency);
        }
        $this->seedMarkupRules($agency);
    }

    protected function shouldSeedSupplierConnectionPlaceholders(): bool
    {
        if (! $this->seedSupplierConnectionPlaceholders) {
            return false;
        }

        return app()->environment(['local', 'testing']);
    }

    protected function seedDemoAgents(Agency $agency): void
    {
        $demoAgents = [
            [
                'name' => 'Sana Travels',
                'username' => 'agent.sana',
                'email' => 'agent.sana@ota.demo',
                'code' => 'AGT-SANA-002',
                'city' => 'Lahore',
                'phone' => '+92 300 110 2201',
                'commission_percent' => 6.5,
                'is_active' => true,
                'tier' => 'silver',
                'notes' => 'Strong Lahore leisure traffic; prefers monthly payout statements.',
            ],
            [
                'name' => 'Karachi Corporate Tours',
                'username' => 'agent.kct',
                'email' => 'agent.kct@ota.demo',
                'code' => 'AGT-KCT-003',
                'city' => 'Karachi',
                'phone' => '+92 300 110 2202',
                'commission_percent' => 8.0,
                'is_active' => true,
                'tier' => 'gold',
                'notes' => 'Corporate account with frequent GCC routes.',
            ],
            [
                'name' => 'Islamabad Air Desk',
                'username' => 'agent.iad',
                'email' => 'agent.iad@ota.demo',
                'code' => 'AGT-IAD-004',
                'city' => 'Islamabad',
                'phone' => '+92 300 110 2203',
                'commission_percent' => 5.0,
                'is_active' => true,
                'tier' => 'standard',
                'notes' => 'Newer partner; monitor first payment cycle.',
            ],
            [
                'name' => 'Multan Family Travel',
                'username' => 'agent.mft',
                'email' => 'agent.mft@ota.demo',
                'code' => 'AGT-MFT-005',
                'city' => 'Multan',
                'phone' => '+92 300 110 2204',
                'commission_percent' => 4.5,
                'is_active' => false,
                'tier' => 'watchlist',
                'notes' => 'Inactive demo agent retained for status and filter testing.',
            ],
            [
                'name' => 'Peshawar Umrah Services',
                'username' => 'agent.pus',
                'email' => 'agent.pus@ota.demo',
                'code' => 'AGT-PUS-006',
                'city' => 'Peshawar',
                'phone' => '+92 300 110 2205',
                'commission_percent' => 7.25,
                'is_active' => true,
                'tier' => 'gold',
                'notes' => 'Umrah-focused agent with pending commission balance.',
            ],
        ];

        foreach ($demoAgents as $index => $definition) {
            $user = User::query()->updateOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'username' => $definition['username'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'current_agency_id' => $agency->id,
                    'account_type' => AccountType::Agent,
                    'meta' => ['phone' => $definition['phone']],
                ],
            );

            $user->agencies()->syncWithoutDetaching([
                $agency->id => ['role' => 'agent'],
            ]);

            $agent = Agent::query()->updateOrCreate(
                [
                    'agency_id' => $agency->id,
                    'user_id' => $user->id,
                ],
                [
                    'code' => $definition['code'],
                    'commission_percent' => $definition['commission_percent'],
                    'is_active' => $definition['is_active'],
                    'meta' => [
                        'city' => $definition['city'],
                        'tier' => $definition['tier'],
                        'notes' => $definition['notes'],
                        'commission_plan' => number_format((float) $definition['commission_percent'], 2).'%',
                    ],
                    'created_at' => now()->subDays(20 - $index),
                ],
            );
        }
    }

    /**
     * Legacy local/testing placeholders only — never run on production or route health audits.
     */
    protected function seedSupplierConnectionPlaceholders(Agency $agency): void
    {
        $definitions = [
            [
                'provider' => SupplierProvider::Duffel,
                'name' => 'Duffel',
                'environment' => SupplierEnvironment::Sandbox,
                'status' => SupplierConnectionStatus::Inactive,
                'is_active' => false,
                'settings' => [],
            ],
            [
                'provider' => SupplierProvider::Sabre,
                'name' => 'Sabre',
                'environment' => SupplierEnvironment::Sandbox,
                'status' => SupplierConnectionStatus::Inactive,
                'is_active' => false,
                'settings' => [],
            ],
            [
                'provider' => SupplierProvider::PiaNdc,
                'name' => 'PIA NDC',
                'environment' => SupplierEnvironment::Sandbox,
                'status' => SupplierConnectionStatus::Inactive,
                'is_active' => false,
                'settings' => [],
            ],
            [
                'provider' => SupplierProvider::AirlineDirect,
                'name' => 'Airline Direct API',
                'environment' => SupplierEnvironment::Sandbox,
                'status' => SupplierConnectionStatus::Inactive,
                'is_active' => false,
                'settings' => [],
            ],
        ];

        foreach ($definitions as $row) {
            SupplierConnection::query()->updateOrCreate(
                [
                    'agency_id' => $agency->id,
                    'provider' => $row['provider'],
                    'name' => $row['name'],
                ],
                [
                    'display_name' => $row['name'],
                    'name' => $row['name'],
                    'environment' => $row['environment'],
                    'status' => $row['status'],
                    'base_url' => null,
                    'credentials' => null,
                    'is_active' => $row['is_active'],
                    'last_tested_at' => null,
                    'last_test_status' => null,
                    'last_error' => null,
                    'settings' => $row['settings'],
                    'meta' => null,
                ],
            );
        }
    }

    protected function seedMarkupRules(Agency $agency): void
    {
        MarkupRule::query()->updateOrCreate(
            [
                'agency_id' => $agency->id,
                'name' => 'Global markup 5%',
            ],
            [
                'rule_type' => MarkupRuleType::Global,
                'value' => 5.0,
                'value_type' => MarkupValueType::Percentage,
                'applies_to' => null,
                'priority' => 100,
                'status' => MarkupRuleStatus::Active,
                'meta' => ['notes' => 'Default markup for all channels.'],
                'is_active' => true,
                'config' => null,
            ],
        );

        MarkupRule::query()->updateOrCreate(
            [
                'agency_id' => $agency->id,
                'name' => 'LHE-DXB fixed markup',
            ],
            [
                'rule_type' => MarkupRuleType::Route,
                'value' => 1200,
                'value_type' => MarkupValueType::Fixed,
                'applies_to' => ['route' => 'LHE-DXB'],
                'priority' => 20,
                'status' => MarkupRuleStatus::Active,
                'meta' => ['notes' => 'Popular route uplift.'],
                'is_active' => true,
                'config' => null,
            ],
        );

        MarkupRule::query()->updateOrCreate(
            [
                'agency_id' => $agency->id,
                'name' => 'PIA airline markup',
            ],
            [
                'rule_type' => MarkupRuleType::Airline,
                'value' => 2.5,
                'value_type' => MarkupValueType::Percentage,
                'applies_to' => ['airline' => 'pk'],
                'priority' => 30,
                'status' => MarkupRuleStatus::Active,
                'meta' => ['notes' => 'Carrier-specific adjustment.'],
                'is_active' => true,
                'config' => null,
            ],
        );

        MarkupRule::query()->updateOrCreate(
            [
                'agency_id' => $agency->id,
                'name' => 'TestAir (fixture) airline markup',
            ],
            [
                'rule_type' => MarkupRuleType::Airline,
                'value' => 2.5,
                'value_type' => MarkupValueType::Percentage,
                'applies_to' => ['airline' => 'ta'],
                'priority' => 30,
                'status' => MarkupRuleStatus::Active,
                'meta' => ['notes' => 'Fixture airline (TA) for integration tests.'],
                'is_active' => true,
                'config' => null,
            ],
        );

        MarkupRule::query()->updateOrCreate(
            [
                'agency_id' => $agency->id,
                'name' => 'Agent portal service fee',
            ],
            [
                'rule_type' => MarkupRuleType::Agent,
                'value' => 800,
                'value_type' => MarkupValueType::Fixed,
                'applies_to' => ['source_channel' => 'agent_portal'],
                'priority' => 40,
                'status' => MarkupRuleStatus::Active,
                'meta' => ['bucket' => 'service_fee', 'notes' => 'Agent channel service fee'],
                'is_active' => true,
                'config' => null,
            ],
        );
    }
}
