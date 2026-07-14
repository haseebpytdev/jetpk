<?php

namespace Tests\Feature\Console;

use App\Models\ClientProfile;
use App\Models\ClientProfileBranding;
use App\Models\ClientProfileModule;
use App\Models\ClientProfileSupplier;
use App\Support\Client\ClientProfileExporter;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OtaExportClientProfileCommandTest extends TestCase
{
    private string $tempRoot;

    private string $clientsRoot;

    private string $publicRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ota-export-test-'.uniqid('', true);
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

    public function test_export_uses_config_fallback(): void
    {
        config([
            'ota_client.slug' => 'export-config-client',
            'ota_client.theme' => 'v2-modern',
            'ota_client.asset_profile' => 'export-config-client',
            'ota_client.modules' => [
                'sabre' => false,
                'al_haider_group_ticketing' => false,
                'accounting' => true,
                'hotels' => false,
                'visa' => false,
                'payment_gateway' => true,
                'dev_cp' => false,
                'staff_panel' => true,
                'admin_panel' => true,
            ],
            'ota-client.agency_name' => 'Config Fallback Travel',
            'ota-client.support_phone' => '+92 300 9999999',
            'ota-client.support_email' => 'ops@config-fallback.test',
            'ota-client.primary_color' => '#112233',
            'ota-client.footer_text' => 'Config footer text.',
            'ota-client.domain_preview' => 'config-fallback.test',
            'app.url' => 'https://config-fallback.test',
            'app.name' => 'Config Fallback Travel',
            'app.env' => 'staging',
            'app.locale' => 'en',
            'app.timezone' => 'Asia/Karachi',
        ]);

        $this->artisan('ota:export-client-profile', [
            'slug' => 'export-config-client',
            '--force' => true,
        ])->assertSuccessful();

        $clientJson = json_decode(
            (string) file_get_contents($this->clientsRoot.'/export-config-client/client.json'),
            true,
        );
        $brandingJson = json_decode(
            (string) file_get_contents($this->clientsRoot.'/export-config-client/branding.json'),
            true,
        );
        $modulesJson = json_decode(
            (string) file_get_contents($this->clientsRoot.'/export-config-client/modules.json'),
            true,
        );

        $this->assertSame('export-config-client', $clientJson['client_slug']);
        $this->assertSame('v2-modern', $clientJson['active_theme']);
        $this->assertSame('Config Fallback Travel', $clientJson['client_name']);
        $this->assertSame('config-fallback.test', $clientJson['domain']);
        $this->assertSame('Config Fallback Travel', $brandingJson['company_name']);
        $this->assertSame('+92 300 9999999', $brandingJson['phone']);
        $this->assertSame('ops@config-fallback.test', $brandingJson['email']);
        $this->assertSame('#112233', $brandingJson['primary_color']);
        $this->assertSame('Config footer text.', $brandingJson['footer_text']);
        $this->assertFalse($modulesJson['sabre']);
        $this->assertTrue($modulesJson['accounting']);
    }

    public function test_export_creates_clients_slug_directory(): void
    {
        config([
            'ota_client.slug' => 'export-dir-client',
            'ota_client.theme' => 'v1-classic',
            'ota_client.asset_profile' => 'export-dir-client',
            'ota-client.agency_name' => 'Directory Client',
            'app.url' => 'https://directory-client.test',
        ]);

        $clientDir = $this->clientsRoot.'/export-dir-client';

        $this->assertDirectoryDoesNotExist($clientDir);

        $this->artisan('ota:export-client-profile', [
            'slug' => 'export-dir-client',
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDirectoryExists($clientDir);
        $this->assertFileExists($clientDir.'/client.json');
        $this->assertFileExists($clientDir.'/branding.json');
        $this->assertFileExists($clientDir.'/modules.json');
        $this->assertFileExists($clientDir.'/deployment.json');
        $this->assertFileExists($clientDir.'/env.production.example');
        $this->assertFileExists($clientDir.'/notes.md');
    }

    public function test_export_creates_public_client_assets_slug(): void
    {
        config([
            'ota_client.slug' => 'export-assets-client',
            'ota_client.asset_profile' => 'export-assets-client',
            'ota-client.agency_name' => 'Assets Client',
            'app.url' => 'https://assets-client.test',
        ]);

        $assetsDir = $this->publicRoot.'/client-assets/export-assets-client';

        $this->assertDirectoryDoesNotExist($assetsDir);

        $this->artisan('ota:export-client-profile', [
            'slug' => 'export-assets-client',
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDirectoryExists($assetsDir.'/logo');
        $this->assertDirectoryExists($assetsDir.'/banners');
        $this->assertDirectoryExists($assetsDir.'/favicon');
        $this->assertDirectoryExists($assetsDir.'/uploads');
    }

    public function test_export_does_not_create_secrets(): void
    {
        config([
            'ota_client.slug' => 'export-secret-client',
            'ota_client.asset_profile' => 'export-secret-client',
            'ota-client.agency_name' => 'Secret Safe Client',
            'app.url' => 'https://secret-safe.test',
            'app.key' => 'base64:super-secret-app-key-value',
        ]);

        putenv('DB_PASSWORD=super-secret-db-password');
        putenv('MAIL_PASSWORD=super-secret-mail-password');

        $this->artisan('ota:export-client-profile', [
            'slug' => 'export-secret-client',
            '--force' => true,
        ])->assertSuccessful();

        $clientDir = $this->clientsRoot.'/export-secret-client';
        $contents = '';
        foreach (glob($clientDir.'/*') ?: [] as $file) {
            if (is_file($file)) {
                $contents .= (string) file_get_contents($file);
            }
        }

        $this->assertStringNotContainsString('super-secret-app-key-value', $contents);
        $this->assertStringNotContainsString('super-secret-db-password', $contents);
        $this->assertStringNotContainsString('super-secret-mail-password', $contents);
        $this->assertStringContainsString('DB_PASSWORD=', $contents);
        $this->assertStringContainsString('APP_KEY=', $contents);

        putenv('DB_PASSWORD');
        putenv('MAIL_PASSWORD');
    }

    public function test_export_refuses_overwrite_unless_force(): void
    {
        config([
            'ota_client.slug' => 'export-overwrite-client',
            'ota_client.asset_profile' => 'export-overwrite-client',
            'ota-client.agency_name' => 'Overwrite Client',
            'app.url' => 'https://overwrite-client.test',
        ]);

        $this->artisan('ota:export-client-profile', [
            'slug' => 'export-overwrite-client',
        ])->assertSuccessful();

        $this->artisan('ota:export-client-profile', [
            'slug' => 'export-overwrite-client',
        ])
            ->expectsOutputToContain('Pass --force to overwrite.')
            ->assertExitCode(1);

        $this->artisan('ota:export-client-profile', [
            'slug' => 'export-overwrite-client',
            '--force' => true,
        ])->assertSuccessful();
    }

    public function test_export_prefers_db_profile_over_config(): void
    {
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_06_20_120000_phase_mc2_create_client_profile_tables.php'])
            ->assertSuccessful();

        $profile = ClientProfile::query()->create([
            'name' => 'DB Export Travel',
            'slug' => 'db-export-client',
            'domain' => 'db-export.test',
            'environment' => 'staging',
            'active_frontend_theme' => 'v2-modern',
            'asset_profile' => 'db-export-client',
            'default_locale' => 'en',
            'timezone' => 'Asia/Karachi',
            'currency' => 'USD',
            'is_master_profile' => false,
            'is_active' => true,
        ]);

        ClientProfileBranding::query()->create([
            'client_profile_id' => $profile->id,
            'company_name' => 'DB Export Travel',
            'phone' => '+1 555 0100',
            'email' => 'ops@db-export.test',
            'primary_color' => '#998877',
        ]);

        ClientProfileModule::query()->create([
            'client_profile_id' => $profile->id,
            'module_key' => 'sabre',
            'enabled' => false,
        ]);

        config([
            'ota_client.slug' => 'db-export-client',
            'ota_client.theme' => 'v1-classic',
            'ota_client.asset_profile' => 'db-export-client',
            'ota_client.modules' => ['sabre' => true],
            'ota-client.agency_name' => 'Config Should Lose',
            'ota-client.primary_color' => '#000000',
            'app.url' => 'https://config-should-lose.test',
        ]);

        $this->artisan('ota:export-client-profile', [
            'slug' => 'db-export-client',
            '--force' => true,
        ])->assertSuccessful();

        $clientJson = json_decode(
            (string) file_get_contents($this->clientsRoot.'/db-export-client/client.json'),
            true,
        );
        $brandingJson = json_decode(
            (string) file_get_contents($this->clientsRoot.'/db-export-client/branding.json'),
            true,
        );
        $modulesJson = json_decode(
            (string) file_get_contents($this->clientsRoot.'/db-export-client/modules.json'),
            true,
        );

        $this->assertSame('DB Export Travel', $clientJson['client_name']);
        $this->assertSame('v2-modern', $clientJson['active_theme']);
        $this->assertSame('db-export.test', $clientJson['domain']);
        $this->assertSame('USD', $clientJson['currency']);
        $this->assertSame('ops@db-export.test', $brandingJson['email']);
        $this->assertSame('#998877', $brandingJson['primary_color']);
        $this->assertFalse($modulesJson['sabre']);
    }

    public function test_export_does_not_leak_supplier_credentials_into_env_example(): void
    {
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_06_20_120000_phase_mc2_create_client_profile_tables.php'])
            ->assertSuccessful();

        $profile = ClientProfile::query()->create([
            'name' => 'Supplier Secret Client',
            'slug' => 'supplier-secret-client',
            'domain' => 'supplier-secret.test',
            'environment' => 'production',
            'active_frontend_theme' => 'v1-classic',
            'asset_profile' => 'supplier-secret-client',
            'default_locale' => 'en',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'is_master_profile' => false,
            'is_active' => true,
        ]);

        ClientProfileBranding::query()->create([
            'client_profile_id' => $profile->id,
            'company_name' => 'Supplier Secret Client',
        ]);

        ClientProfileSupplier::query()->create([
            'client_profile_id' => $profile->id,
            'supplier_key' => 'sabre',
            'enabled' => true,
            'mode' => 'sandbox',
            'credentials' => [
                'client_id' => 'live-sabre-client-id-value',
                'client_secret' => 'live-sabre-client-secret-value',
            ],
            'config' => ['pcc' => 'TEST'],
        ]);

        putenv('SABRE_CLIENT_ID=live-sabre-client-id-value');
        putenv('SABRE_CLIENT_SECRET=live-sabre-client-secret-value');

        config([
            'ota_client.slug' => 'supplier-secret-client',
            'ota_client.asset_profile' => 'supplier-secret-client',
            'app.url' => 'https://supplier-secret.test',
        ]);

        $this->artisan('ota:export-client-profile', [
            'slug' => 'supplier-secret-client',
            '--force' => true,
        ])->assertSuccessful();

        $envExample = (string) file_get_contents(
            $this->clientsRoot.'/supplier-secret-client/env.production.example',
        );

        $this->assertStringNotContainsString('live-sabre-client-id-value', $envExample);
        $this->assertStringNotContainsString('live-sabre-client-secret-value', $envExample);

        $allExportContents = $envExample;
        foreach (glob($this->clientsRoot.'/supplier-secret-client/*') ?: [] as $file) {
            if (is_file($file)) {
                $allExportContents .= (string) file_get_contents($file);
            }
        }

        $this->assertStringNotContainsString('live-sabre-client-id-value', $allExportContents);
        $this->assertStringNotContainsString('live-sabre-client-secret-value', $allExportContents);

        putenv('SABRE_CLIENT_ID');
        putenv('SABRE_CLIENT_SECRET');
    }
}
