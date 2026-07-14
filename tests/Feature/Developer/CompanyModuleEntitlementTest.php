<?php

namespace Tests\Feature\Developer;

use App\Models\Agency;
use App\Models\CompanyModuleEntitlement;
use App\Models\DeveloperUser;
use App\Models\PlatformModuleSetting;
use App\Services\Platform\CompanyModuleEntitlementService;
use App\Services\Platform\PlatformModuleSettingsService;
use App\Support\Platform\PlatformModuleEnforcer;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyModuleEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_per_agency_override_disables_module_when_global_enabled(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->firstOrFail();

        $entitlements = app(CompanyModuleEntitlementService::class);
        $developer = DeveloperUser::query()->create([
            'name' => 'Dev',
            'email' => 'dev@test.com',
            'password' => 'secret',
            'is_active' => true,
        ]);

        $entitlements->setModuleEntitlement(
            agencyId: $agency->id,
            moduleKey: 'agent_portal',
            enabled: false,
            expiresAt: null,
            actor: $developer,
            request: request(),
        );

        $enforcer = app(PlatformModuleEnforcer::class);
        $this->assertTrue($enforcer->effectiveModuleEnabled('agent_portal'));
        $this->assertFalse($enforcer->effectiveModuleEnabledForAgency('agent_portal', $agency->id));
    }

    public function test_expired_entitlement_inherits_global_state(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->firstOrFail();

        PlatformModuleSetting::query()->create([
            'module_key' => 'agent_portal',
            'enabled' => false,
            'locked' => false,
        ]);
        app(PlatformModuleSettingsService::class)->forgetCache();

        CompanyModuleEntitlement::query()->create([
            'agency_id' => $agency->id,
            'module_key' => 'agent_portal',
            'enabled' => true,
            'expires_at' => now()->subDay(),
            'source' => 'test',
        ]);
        app(CompanyModuleEntitlementService::class)->forgetAgencyCache($agency->id);

        $enforcer = app(PlatformModuleEnforcer::class);
        $this->assertFalse($enforcer->effectiveModuleEnabledForAgency('agent_portal', $agency->id));
    }
}
