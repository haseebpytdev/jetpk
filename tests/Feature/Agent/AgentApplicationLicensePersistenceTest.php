<?php

namespace Tests\Feature\Agent;

use App\Models\AgentApplication;
use App\Services\Dashboard\Api\DashboardAgentApplicationsReadService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AgentApplicationLicensePersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_public_agent_registration_persists_license_number(): void
    {
        $payload = [
            'company_name' => 'Licensed Travels',
            'city' => 'Karachi',
            'business_type' => 'travel_agency',
            'license_number' => 'DTTA-LIC-7788',
            'first_name' => 'Sara',
            'email' => 'sara-licensed-'.str()->random(6).'@example.test',
            'mobile_country_code' => '+92',
            'mobile' => '3001234567',
            'terms' => '1',
        ];

        $response = $this->postJson(route('agent.register.store'), $payload);
        $response->assertOk()->assertJsonPath('ok', true);

        $application = AgentApplication::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($payload['email'])])
            ->first();
        $this->assertNotNull($application, $response->json('message') ?? 'application not persisted');
        $this->assertSame('DTTA-LIC-7788', $application->license_number);
    }

    public function test_dashboard_read_service_exposes_license_number(): void
    {
        $admin = \App\Models\User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        if ($admin->account_type !== \App\Enums\AccountType::PlatformAdmin) {
            $admin->forceFill(['account_type' => \App\Enums\AccountType::PlatformAdmin])->save();
        }

        $application = AgentApplication::query()->create([
            'first_name' => 'Applicant',
            'last_name' => 'Owner',
            'email' => 'license-read-'.str()->random(6).'@example.test',
            'mobile' => '+923001112233',
            'company_name' => 'License Read Travels',
            'business_type' => 'travel_agency',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'office_address' => 'Test office address',
            'license_number' => 'DTTA-LIC-READ-01',
            'status' => 'pending',
        ]);

        $admin = \App\Models\User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $this->actingAs($admin);

        $page = app(DashboardAgentApplicationsReadService::class)->paginate($admin, new Request([
            'status' => 'pending',
        ]));

        $row = collect($page['items'])->firstWhere('id', (string) $application->id);
        $this->assertIsArray($row);
        $this->assertSame('DTTA-LIC-READ-01', $row['licenseNumber'] ?? null);
    }
}
