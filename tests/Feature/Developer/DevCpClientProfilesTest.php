<?php

namespace Tests\Feature\Developer;

use App\Enums\SupplierProvider;
use App\Models\ClientProfile;
use App\Models\ClientProfileBranding;
use App\Models\ClientProfileModule;
use App\Models\ClientProfileSupplier;
use App\Models\DeveloperUser;
use App\Support\Client\ClientProfileConfigReader;
use App\Support\Client\ClientProfileExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DevCpClientProfilesTest extends TestCase
{
    use RefreshDatabase;

    private string $tempRoot;

    private string $clientsRoot;

    private string $publicRoot;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ota-developer.enabled', true);

        $this->tempRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ota-devcp-clients-'.uniqid('', true);
        $this->clientsRoot = $this->tempRoot.DIRECTORY_SEPARATOR.'clients';
        $this->publicRoot = $this->tempRoot.DIRECTORY_SEPARATOR.'public';

        File::ensureDirectoryExists($this->clientsRoot);
        File::copyDirectory(base_path('clients/_template'), $this->clientsRoot.'/_template');

        $this->app->instance(
            ClientProfileExporter::class,
            new ClientProfileExporter($this->clientsRoot, $this->publicRoot),
        );
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempRoot)) {
            File::deleteDirectory($this->tempRoot);
        }

        parent::tearDown();
    }

    public function test_guest_cannot_access_clients(): void
    {
        $this->get(route('dev.cp.clients.index'))
            ->assertRedirect(route('dev.cp.login'));
    }

    public function test_client_create(): void
    {
        $developer = $this->developerUser();

        $response = $this->withSession(['dev_cp_user_id' => $developer->id])
            ->post(route('dev.cp.clients.store'), [
                'name' => 'Demo Client',
                'slug' => 'demo-client',
                'domain' => 'demo.example.com',
                'environment' => 'staging',
                'default_locale' => 'en',
                'timezone' => 'UTC',
                'currency' => 'USD',
                'is_active' => '1',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $profile = ClientProfile::query()->where('slug', 'demo-client')->first();
        $this->assertNotNull($profile);
        $this->assertDatabaseHas('client_profile_branding', [
            'client_profile_id' => $profile->id,
            'company_name' => 'Demo Client',
        ]);

        $this->assertSame(
            count(ClientProfileConfigReader::MODULE_KEYS),
            ClientProfileModule::query()->where('client_profile_id', $profile->id)->count(),
        );

        $this->assertSame(
            count(SupplierProvider::cases()),
            ClientProfileSupplier::query()->where('client_profile_id', $profile->id)->count(),
        );
    }

    public function test_client_update(): void
    {
        $developer = $this->developerUser();
        $profile = $this->makeProfile(['slug' => 'update-me', 'name' => 'Before']);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->put(route('dev.cp.clients.update', $profile), [
                'name' => 'After Update',
                'domain' => 'updated.example.com',
                'environment' => 'production',
                'default_locale' => 'en',
                'timezone' => 'Asia/Karachi',
                'currency' => 'PKR',
                'is_active' => '1',
            ])
            ->assertRedirect(route('dev.cp.clients.edit', $profile));

        $profile->refresh();
        $this->assertSame('After Update', $profile->name);
        $this->assertSame('updated.example.com', $profile->domain);
        $this->assertSame('update-me', $profile->slug);
    }

    public function test_master_profile_requires_confirmation(): void
    {
        $developer = $this->developerUser();
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Master',
            'is_master_profile' => true,
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->put(route('dev.cp.clients.update', $profile), [
                'name' => 'Changed Without Confirm',
                'environment' => 'production',
                'default_locale' => 'en',
                'timezone' => 'Asia/Karachi',
                'currency' => 'PKR',
            ])
            ->assertSessionHasErrors('confirm_master_edit');

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->put(route('dev.cp.clients.update', $profile), [
                'name' => 'Changed With Confirm',
                'environment' => 'production',
                'default_locale' => 'en',
                'timezone' => 'Asia/Karachi',
                'currency' => 'PKR',
                'confirm_master_edit' => '1',
            ])
            ->assertRedirect(route('dev.cp.clients.edit', $profile));

        $this->assertSame('Changed With Confirm', $profile->fresh()->name);
    }

    public function test_module_toggle(): void
    {
        $developer = $this->developerUser();
        $profile = $this->makeProfile(['slug' => 'module-client']);

        ClientProfileModule::query()->create([
            'client_profile_id' => $profile->id,
            'module_key' => 'sabre',
            'enabled' => true,
        ]);

        $modules = [];
        foreach (ClientProfileConfigReader::MODULE_KEYS as $key) {
            $modules[$key] = $key === 'sabre' ? '0' : '0';
        }
        $modules['accounting'] = '1';

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->put(route('dev.cp.clients.modules.update', $profile), [
                'modules' => $modules,
            ])
            ->assertRedirect(route('dev.cp.clients.modules', $profile));

        $this->assertDatabaseHas('client_profile_modules', [
            'client_profile_id' => $profile->id,
            'module_key' => 'sabre',
            'enabled' => false,
        ]);
        $this->assertDatabaseHas('client_profile_modules', [
            'client_profile_id' => $profile->id,
            'module_key' => 'accounting',
            'enabled' => true,
        ]);
    }

    public function test_branding_update(): void
    {
        $developer = $this->developerUser();
        $profile = $this->makeProfile(['slug' => 'brand-client']);
        ClientProfileBranding::query()->create([
            'client_profile_id' => $profile->id,
            'company_name' => 'Old Co',
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->put(route('dev.cp.clients.branding.update', $profile), [
                'company_name' => 'New Co',
                'email' => 'hello@newco.test',
                'primary_color' => '#112233',
            ])
            ->assertRedirect(route('dev.cp.clients.branding', $profile));

        $this->assertDatabaseHas('client_profile_branding', [
            'client_profile_id' => $profile->id,
            'company_name' => 'New Co',
            'email' => 'hello@newco.test',
            'primary_color' => '#112233',
        ]);
    }

    public function test_supplier_update(): void
    {
        $developer = $this->developerUser();
        $profile = $this->makeProfile(['slug' => 'supplier-client']);

        ClientProfileSupplier::query()->create([
            'client_profile_id' => $profile->id,
            'supplier_key' => 'sabre',
            'enabled' => false,
            'credentials' => ['client_id' => 'keep-me', 'client_secret' => 'secret-value'],
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->put(route('dev.cp.clients.suppliers.update', $profile), [
                'suppliers' => [
                    'sabre' => [
                        'enabled' => '1',
                        'mode' => 'sandbox',
                    ],
                ],
            ])
            ->assertRedirect(route('dev.cp.clients.suppliers', $profile));

        $supplier = ClientProfileSupplier::query()
            ->where('client_profile_id', $profile->id)
            ->where('supplier_key', 'sabre')
            ->first();

        $this->assertNotNull($supplier);
        $this->assertTrue($supplier->enabled);
        $this->assertSame('sandbox', $supplier->mode);
        $this->assertSame('keep-me', $supplier->credentials['client_id'] ?? null);
    }

    public function test_duplicate_client(): void
    {
        $developer = $this->developerUser();
        $profile = $this->makeProfile(['slug' => 'source-client', 'name' => 'Source']);

        ClientProfileBranding::query()->create([
            'client_profile_id' => $profile->id,
            'company_name' => 'Source Co',
            'primary_color' => '#abcdef',
        ]);

        ClientProfileModule::query()->create([
            'client_profile_id' => $profile->id,
            'module_key' => 'visa',
            'enabled' => true,
        ]);

        ClientProfileSupplier::query()->create([
            'client_profile_id' => $profile->id,
            'supplier_key' => 'duffel',
            'enabled' => true,
            'credentials' => ['api_key' => 'hidden'],
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->post(route('dev.cp.clients.duplicate', $profile), [
                'new_name' => 'Copy Client',
                'new_slug' => 'copy-client',
            ])
            ->assertRedirect();

        $duplicate = ClientProfile::query()->where('slug', 'copy-client')->first();
        $this->assertNotNull($duplicate);
        $this->assertFalse($duplicate->is_master_profile);

        $this->assertDatabaseHas('client_profile_branding', [
            'client_profile_id' => $duplicate->id,
            'company_name' => 'Source Co',
            'primary_color' => '#abcdef',
        ]);

        $this->assertDatabaseHas('client_profile_modules', [
            'client_profile_id' => $duplicate->id,
            'module_key' => 'visa',
            'enabled' => true,
        ]);

        $dupSupplier = ClientProfileSupplier::query()
            ->where('client_profile_id', $duplicate->id)
            ->where('supplier_key', 'duffel')
            ->first();

        $this->assertNotNull($dupSupplier);
        $this->assertNull($dupSupplier->credentials);
    }

    public function test_duplicate_with_copy_credentials_when_requested(): void
    {
        $developer = $this->developerUser();
        $profile = $this->makeProfile(['slug' => 'cred-source', 'name' => 'Cred Source']);

        ClientProfileSupplier::query()->create([
            'client_profile_id' => $profile->id,
            'supplier_key' => 'sabre',
            'enabled' => true,
            'credentials' => ['client_id' => 'copy-id'],
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->post(route('dev.cp.clients.duplicate', $profile), [
                'new_name' => 'Cred Copy',
                'new_slug' => 'cred-copy',
                'copy_credentials' => '1',
            ])
            ->assertRedirect();

        $duplicate = ClientProfile::query()->where('slug', 'cred-copy')->first();
        $supplier = ClientProfileSupplier::query()
            ->where('client_profile_id', $duplicate->id)
            ->where('supplier_key', 'sabre')
            ->first();

        $this->assertSame('copy-id', $supplier?->credentials['client_id'] ?? null);
    }

    public function test_theme_page_displays_available_registry_themes(): void
    {
        $developer = $this->developerUser();
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'active_frontend_theme' => 'v1-classic',
            'active_admin_theme' => 'default-admin',
            'active_staff_theme' => 'default-staff',
            'is_master_profile' => true,
        ]);

        $response = $this->withSession(['dev_cp_user_id' => $developer->id])
            ->get(route('dev.cp.clients.theme', $profile));

        $response->assertOk();
        $response->assertSee('Runtime theme registry (MC-8A)', false);
        $response->assertSee('v2-modern', false);
        $response->assertSee('bento-admin', false);
        $response->assertSee('bento-staff', false);
        $response->assertSee('v1-classic', false);
        $response->assertSee('default-admin', false);
        $response->assertSee('default-staff', false);
    }

    public function test_theme_page_displays_view_resolution_summary(): void
    {
        $developer = $this->developerUser();
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'active_frontend_theme' => 'v1-classic',
            'active_admin_theme' => 'default-admin',
            'active_staff_theme' => 'default-staff',
            'is_master_profile' => true,
        ]);

        $response = $this->withSession(['dev_cp_user_id' => $developer->id])
            ->get(route('dev.cp.clients.theme', $profile));

        $response->assertOk();
        $response->assertSee('View resolution summary (MC-8B)', false);
        $response->assertSee('resources/views/themes/frontend/v1-classic', false);
        $response->assertSee('MC-8D:', false);
        $response->assertSee('Theme root exists', false);
    }

    public function test_theme_page_displays_ui_runtime_engine_panel(): void
    {
        $developer = $this->developerUser();
        $profile = $this->makeProfile([
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master',
            'active_frontend_theme' => 'v1-classic',
            'active_admin_theme' => 'default-admin',
            'active_staff_theme' => 'default-staff',
            'is_master_profile' => true,
        ]);

        $response = $this->withSession(['dev_cp_user_id' => $developer->id])
            ->get(route('dev.cp.clients.theme', $profile));

        $response->assertOk();
        $response->assertSee('UI Runtime Engine (MC-8D)', false);
        $response->assertSee('client_view()', false);
        $response->assertSee('client_layout()', false);
        $response->assertSee('client_route()', false);
        $response->assertSee('ota:ui-runtime-audit', false);
        $response->assertSee('Layout resolution (sample)', false);
        $response->assertSee('themes.frontend.v1-classic.layouts.frontend', false);
    }

    public function test_export_action(): void
    {
        $developer = $this->developerUser();
        $profile = $this->makeProfile([
            'slug' => 'export-client',
            'name' => 'Export Client',
            'asset_profile' => 'export-client',
        ]);

        ClientProfileBranding::query()->create([
            'client_profile_id' => $profile->id,
            'company_name' => 'Export Co',
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->post(route('dev.cp.clients.export', $profile))
            ->assertRedirect(route('dev.cp.clients.index'))
            ->assertSessionHas('status');

        $this->assertFileExists($this->clientsRoot.DIRECTORY_SEPARATOR.'export-client'.DIRECTORY_SEPARATOR.'client.json');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProfile(array $overrides = []): ClientProfile
    {
        return ClientProfile::query()->create(array_merge([
            'name' => 'Test Client',
            'slug' => 'test-client-'.uniqid(),
            'domain' => null,
            'environment' => 'production',
            'active_frontend_theme' => 'v1-classic',
            'asset_profile' => 'test-assets',
            'default_locale' => 'en',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'is_master_profile' => false,
            'is_active' => true,
        ], $overrides));
    }

    private function developerUser(): DeveloperUser
    {
        return DeveloperUser::query()->create([
            'name' => 'Dev',
            'email' => 'dev-clients-'.uniqid('', true).'@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);
    }
}
