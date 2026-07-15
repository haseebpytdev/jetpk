<?php

namespace Tests\Feature\Client;

use App\Models\ClientProfile;
use App\Models\ClientProfileModule;
use App\Services\Client\ClientProfileResolver;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DefaultClientCanonicalRedirectTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function defaultSlugAliasRedirectProvider(): array
    {
        return [
            'root' => ['/haseeb-master', '/'],
            'home alias' => ['/haseeb-master/home', '/'],
            'login' => ['/haseeb-master/login', '/login'],
            'register' => ['/haseeb-master/register', '/register'],
            'admin' => ['/haseeb-master/admin', '/admin'],
            'admin bookings' => ['/haseeb-master/admin/bookings', '/admin/bookings'],
            'staff' => ['/haseeb-master/staff', '/staff'],
            'agent' => ['/haseeb-master/agent', '/agent'],
            'customer' => ['/haseeb-master/customer', '/customer'],
            'lookup booking' => ['/haseeb-master/lookup-booking', '/lookup-booking'],
            'groups search' => ['/haseeb-master/groups/search', '/groups/search'],
        ];
    }

    #[DataProvider('defaultSlugAliasRedirectProvider')]
    public function test_default_slug_prefixed_paths_redirect_to_canonical_root(string $from, string $to): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'is_master_profile' => true,
        ]);

        $this->get($from)
            ->assertStatus(302)
            ->assertRedirect($to);
    }

    public function test_default_slug_redirect_preserves_query_string(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'is_master_profile' => true,
        ]);

        $this->get('/haseeb-master/flights/results?x=1')
            ->assertStatus(302)
            ->assertRedirect('/flights/results?x=1');
    }

    public function test_root_login_still_returns_200(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'is_master_profile' => true,
        ]);

        $this->get('/login')->assertOk();
    }

    public function test_root_admin_still_redirects_guest_to_login(): void
    {
        $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'is_master_profile' => true,
        ]);

        $this->get('/admin')->assertRedirect(route('login', absolute: false));
    }

    public function test_non_default_client_keeps_prefixed_path(): void
    {
        $this->makeProfile([
            'slug' => 'jetpk',
            'name' => 'Jet Pakistan',
        ]);

        $this->get('/jetpk/login')->assertOk();
        $this->get('/jetpk/admin')->assertRedirect('/jetpk/login');
    }

    public function test_is_default_deployment_slug_helper(): void
    {
        $resolver = app(ClientProfileResolver::class);

        $this->assertTrue($resolver->isDefaultDeploymentSlug('haseeb-master'));
        $this->assertTrue($resolver->isDefaultDeploymentSlug('Haseeb-Master'));
        $this->assertFalse($resolver->isDefaultDeploymentSlug('jetpk'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProfile(array $overrides = []): ClientProfile
    {
        $profile = ClientProfile::query()->create(array_merge([
            'name' => 'Test Client',
            'slug' => 'test-client-'.uniqid(),
            'domain' => null,
            'environment' => 'staging',
            'active_frontend_theme' => 'v1-classic',
            'active_admin_theme' => 'v1-classic',
            'active_staff_theme' => 'v1-classic',
            'asset_profile' => 'test-assets',
            'default_locale' => 'en',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'is_master_profile' => false,
            'is_active' => true,
        ], $overrides));

        foreach (ClientProfileConfigReader::MODULE_KEYS as $moduleKey) {
            ClientProfileModule::query()->create([
                'client_profile_id' => $profile->id,
                'module_key' => $moduleKey,
                'enabled' => false,
            ]);
        }

        return $profile;
    }
}
