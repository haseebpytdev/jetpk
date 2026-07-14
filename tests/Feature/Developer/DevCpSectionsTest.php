<?php

namespace Tests\Feature\Developer;

use App\Models\DeveloperUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DevCpSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('ota-developer.enabled', true);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function devCpRoutesProvider(): array
    {
        return [
            'overview' => ['dev.cp.index'],
            'users' => ['dev.cp.users.index'],
            'clients' => ['dev.cp.clients.index'],
            'modules' => ['dev.cp.modules.index'],
            'security events' => ['dev.cp.security-events.index'],
            'health' => ['dev.cp.health'],
            'sabre' => ['dev.cp.sabre'],
            'group ticketing' => ['dev.cp.group-ticketing'],
            'dashboards' => ['dev.cp.dashboards'],
            'deployment' => ['dev.cp.deployment'],
        ];
    }

    public function test_sabre_status_page_does_not_expose_secrets(): void
    {
        $developer = DeveloperUser::query()->create([
            'name' => 'Dev',
            'email' => 'sabre-safe@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $response = $this->withSession(['dev_cp_user_id' => $developer->id])
            ->get(route('dev.cp.sabre'));

        $response->assertOk();
        $response->assertSee('Sabre status', false);
        $response->assertSee('Provider mutation policy', false);
        $response->assertDontSee('client_secret', false);
        $response->assertDontSee('Bearer eyJ', false);
    }

    #[DataProvider('devCpRoutesProvider')]
    public function test_authenticated_developer_can_access_section(string $routeName): void
    {
        $developer = DeveloperUser::query()->create([
            'name' => 'Dev',
            'email' => 'sections@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->get(route($routeName))
            ->assertOk();
    }

    public function test_companies_legacy_route_redirects_to_platform_admins(): void
    {
        $developer = DeveloperUser::query()->create([
            'name' => 'Dev',
            'email' => 'legacy@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->get(route('dev.cp.companies.index'))
            ->assertRedirect(route('dev.cp.users.index'))
            ->assertSessionHas('status');
    }

    public function test_dev_cp_disabled_returns_404(): void
    {
        Config::set('ota-developer.enabled', false);

        $this->get(route('dev.cp.index'))->assertNotFound();
    }
}
