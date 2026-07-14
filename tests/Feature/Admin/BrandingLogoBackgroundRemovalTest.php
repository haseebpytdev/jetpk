<?php

namespace Tests\Feature\Admin;

use App\Data\Media\BackgroundRemovalInput;
use App\Data\Media\BackgroundRemovalResult;
use App\Enums\AccountType;
use App\Enums\BrandingAssetProcessStatus;
use App\Models\Agency;
use App\Models\AgencySetting;
use App\Models\BackgroundRemovalSetting;
use App\Models\BrandingAssetProcess;
use App\Models\User;
use App\Services\Media\Providers\MockBackgroundRemovalProvider;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesBrandingBackgroundContext;
use Tests\TestCase;

class BrandingLogoBackgroundRemovalTest extends TestCase
{
    use CreatesBrandingBackgroundContext;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required for branding background-removal tests.');
        }

        $this->withoutMiddleware(ValidateCsrfToken::class);
        Config::set('background-removal.force_mock_provider', true);
        Storage::fake('local');
        Storage::fake('public');
        $this->setUpBrandingBackgroundContext();
    }

    protected function tearDown(): void
    {
        MockBackgroundRemovalProvider::reset();
        parent::tearDown();
    }

    public function test_stage_endpoint_stores_private_original(): void
    {
        $file = UploadedFile::fake()->image('logo.jpg', 120, 60);

        $response = $this->actingAs($this->brandingAdmin)->postJson(
            route('admin.settings.branding.logo-background.stage'),
            ['logo' => $file],
        );

        $response->assertOk();
        $uuid = (string) $response->json('uuid');
        $process = BrandingAssetProcess::query()->where('uuid', $uuid)->first();
        $this->assertNotNull($process);
        $this->assertTrue(Storage::disk('local')->exists((string) $process->source_path));
        $this->assertStringStartsWith('branding-background-removal/', (string) $process->source_path);
    }

    public function test_active_logo_remains_unchanged_after_staging(): void
    {
        $originalPath = $this->brandingSettings->logo_path;
        $file = UploadedFile::fake()->image('logo.jpg', 120, 60);

        $this->actingAs($this->brandingAdmin)->postJson(
            route('admin.settings.branding.logo-background.stage'),
            ['logo' => $file],
        )->assertOk();

        $this->brandingSettings->refresh();
        $this->assertSame($originalPath, $this->brandingSettings->logo_path);
        $this->assertTrue(Storage::disk('public')->exists($originalPath));
    }

    public function test_provider_disabled_path_returns_safe_failure(): void
    {
        $this->disableBackgroundRemovalProvider();
        $process = $this->stageProcess();

        $response = $this->actingAs($this->brandingAdmin)->postJson(
            route('admin.settings.branding.logo-background.run', ['process' => $process->uuid]),
        );

        $response->assertOk();
        $response->assertJsonPath('status', BrandingAssetProcessStatus::Failed->value);
        $response->assertJsonPath('error_code', 'provider_disabled');
        $this->brandingSettings->refresh();
        $this->assertSame('agencies/'.$this->brandingAgency->id.'/branding/active-logo.png', $this->brandingSettings->logo_path);
    }

    public function test_provider_success_path_completes_with_transparent_png(): void
    {
        $this->enableMockProvider(function (BackgroundRemovalInput $input): BackgroundRemovalResult {
            $path = $this->writeTempPng($this->makeTransparentLogoPngBytes());

            return new BackgroundRemovalResult(success: true, outputAbsolutePath: $path, processingMs: 12);
        });

        $process = $this->stageAndRun();

        $this->assertSame(BrandingAssetProcessStatus::Completed, $process->status);
        $this->assertNotNull($process->result_path);
        $this->assertNotNull($process->result_checksum);
        $this->assertTrue(Storage::disk('local')->exists((string) $process->result_path));
    }

    public function test_provider_timeout_path_marks_failed_without_changing_active_logo(): void
    {
        $this->enableMockProvider(fn (): BackgroundRemovalResult => BackgroundRemovalResult::failed(
            'provider_timeout',
            'Background removal timed out or failed. Please try again or keep the original logo.',
        ));

        $process = $this->stageAndRun();
        $this->assertSame(BrandingAssetProcessStatus::Failed, $process->status);
        $this->assertSame('provider_timeout', $process->error_code);
        $this->brandingSettings->refresh();
        $this->assertSame('agencies/'.$this->brandingAgency->id.'/branding/active-logo.png', $this->brandingSettings->logo_path);
    }

    public function test_invalid_response_path_is_rejected(): void
    {
        $this->enableMockProvider(function (): BackgroundRemovalResult {
            $path = tempnam(sys_get_temp_dir(), 'jp-invalid-');
            $this->assertNotFalse($path);
            $pngPath = $path.'.png';
            @unlink($path);
            file_put_contents($pngPath, 'not-a-png');

            return new BackgroundRemovalResult(success: true, outputAbsolutePath: $pngPath, processingMs: 5);
        });

        $process = $this->stageAndRun();
        $this->assertSame(BrandingAssetProcessStatus::Failed, $process->status);
        $this->assertSame('invalid_output', $process->error_code);
    }

    public function test_output_without_transparency_is_rejected(): void
    {
        $this->enableMockProvider(function (): BackgroundRemovalResult {
            $path = $this->writeTempPng($this->makeOpaquePngBytes());

            return new BackgroundRemovalResult(success: true, outputAbsolutePath: $path, processingMs: 5);
        });

        $process = $this->stageAndRun();
        $this->assertSame(BrandingAssetProcessStatus::Failed, $process->status);
        $this->assertSame('no_transparency', $process->error_code);
    }

    public function test_completely_transparent_output_is_rejected(): void
    {
        $this->enableMockProvider(function (): BackgroundRemovalResult {
            $path = $this->writeTempPng($this->makeFullyTransparentPngBytes());

            return new BackgroundRemovalResult(success: true, outputAbsolutePath: $path, processingMs: 5);
        });

        $process = $this->stageAndRun();
        $this->assertSame(BrandingAssetProcessStatus::Failed, $process->status);
        $this->assertSame('fully_transparent', $process->error_code);
    }

    public function test_processed_preview_authorized_for_owning_agency_only(): void
    {
        $process = $this->stageCompletedProcess();

        $this->actingAs($this->brandingAdmin)->get(
            route('admin.settings.branding.logo-background.preview', ['process' => $process->uuid, 'variant' => 'processed']),
        )->assertOk();

        $otherAgency = Agency::factory()->create();
        $otherAdmin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => $otherAgency->id,
        ]);

        $this->actingAs($otherAdmin)->get(
            route('admin.settings.branding.logo-background.preview', ['process' => $process->uuid, 'variant' => 'processed']),
            ['Accept' => 'application/json'],
        )->assertNotFound();
    }

    public function test_accept_processed_logo_updates_active_branding_and_validates_png(): void
    {
        $process = $this->stageCompletedProcess();
        $originalLogo = $this->brandingSettings->logo_path;

        $response = $this->actingAs($this->brandingAdmin)->postJson(
            route('admin.settings.branding.logo-background.accept', ['process' => $process->uuid]),
        );

        $response->assertOk();
        $process->refresh();
        $this->brandingSettings->refresh();

        $this->assertSame(BrandingAssetProcessStatus::Accepted, $process->status);
        $this->assertNotSame($originalLogo, $this->brandingSettings->logo_path);
        $this->assertStringStartsWith('agencies/'.$this->brandingAgency->id.'/branding/logo-', $this->brandingSettings->logo_path);
        $this->assertTrue(Storage::disk('public')->exists($this->brandingSettings->logo_path));

        $absolute = Storage::disk('public')->path($this->brandingSettings->logo_path);
        $info = getimagesize($absolute);
        $this->assertIsArray($info);
        $this->assertSame('image/png', $info['mime'] ?? null);
        $this->assertGreaterThan(0, $info[0]);
        $this->assertGreaterThan(0, $info[1]);
        $this->assertLessThanOrEqual(5_242_880, filesize($absolute) ?: 0);

        $inspector = app(\App\Services\Media\ImageTransparencyInspector::class);
        $this->assertTrue($inspector->hasAlphaChannel($absolute));
        $this->assertTrue($inspector->hasTransparentPixels($absolute));
        $this->assertFalse($inspector->isFullyTransparent($absolute));
        $this->assertFalse($inspector->isFullyOpaque($absolute));
    }

    public function test_keep_original_leaves_active_logo_unchanged_after_discard(): void
    {
        $process = $this->stageCompletedProcess();
        $originalLogo = $this->brandingSettings->logo_path;

        $this->actingAs($this->brandingAdmin)->postJson(
            route('admin.settings.branding.logo-background.discard', ['process' => $process->uuid]),
        )->assertOk();

        $this->brandingSettings->refresh();
        $this->assertSame($originalLogo, $this->brandingSettings->logo_path);
    }

    public function test_discard_result_marks_process_discarded(): void
    {
        $process = $this->stageCompletedProcess();

        $this->actingAs($this->brandingAdmin)->postJson(
            route('admin.settings.branding.logo-background.discard', ['process' => $process->uuid]),
        )->assertOk()
            ->assertJsonPath('status', BrandingAssetProcessStatus::Discarded->value);
    }

    public function test_retry_failed_process_can_complete(): void
    {
        $this->enableMockProvider(fn (): BackgroundRemovalResult => BackgroundRemovalResult::failed('provider_error', 'Temporary failure.'));
        $process = $this->stageAndRun();
        $this->assertSame(BrandingAssetProcessStatus::Failed, $process->status);

        $this->enableMockProvider(function (): BackgroundRemovalResult {
            $path = $this->writeTempPng($this->makeTransparentLogoPngBytes());

            return new BackgroundRemovalResult(success: true, outputAbsolutePath: $path, processingMs: 8);
        });

        $retried = $this->actingAs($this->brandingAdmin)->postJson(
            route('admin.settings.branding.logo-background.run', ['process' => $process->uuid]),
        )->assertOk()
            ->json('status');

        $this->assertSame(BrandingAssetProcessStatus::Completed->value, $retried);
    }

    public function test_unauthorized_role_denied(): void
    {
        $customer = User::factory()->customer()->create([
            'current_agency_id' => $this->brandingAgency->id,
        ]);

        $this->actingAs($customer)->postJson(
            route('admin.settings.branding.logo-background.stage'),
            ['logo' => UploadedFile::fake()->image('logo.jpg')],
        )->assertForbidden();
    }

    public function test_cross_agency_access_denied(): void
    {
        $process = $this->stageProcess();
        $otherAgency = Agency::factory()->create();
        $otherAdmin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => $otherAgency->id,
        ]);

        $this->actingAs($otherAdmin)->getJson(
            route('admin.settings.branding.logo-background.show', ['process' => $process->uuid]),
        )->assertNotFound();
    }

    public function test_duplicate_submission_protected(): void
    {
        $bytes = $this->makeOpaquePngBytes(100, 50);
        $first = UploadedFile::fake()->createWithContent('logo.jpg', $bytes);
        $second = UploadedFile::fake()->createWithContent('logo.jpg', $bytes);

        $this->actingAs($this->brandingAdmin)->postJson(
            route('admin.settings.branding.logo-background.stage'),
            ['logo' => $first],
        )->assertOk();

        $this->actingAs($this->brandingAdmin)->postJson(
            route('admin.settings.branding.logo-background.stage'),
            ['logo' => $second],
        )->assertStatus(422);
    }

    public function test_encrypted_api_key_masked_and_preserved(): void
    {
        $this->actingAs($this->brandingAdmin)->patch(
            route('admin.settings.background-removal.update'),
            [
                'provider' => 'remove_bg',
                'api_endpoint' => 'https://api.remove.bg/v1.0/removebg',
                'api_key' => 'secret-test-key-12345',
                'timeout_seconds' => 20,
                'max_source_bytes' => 5_242_880,
                'is_enabled' => true,
                'default_for_logos' => false,
            ],
        )->assertRedirect();

        $stored = BackgroundRemovalSetting::query()->where('agency_id', $this->brandingAgency->id)->first();
        $this->assertNotNull($stored);
        $this->assertSame('secret-test-key-12345', $stored->api_key);
        $this->assertSame('********', $stored->maskedApiKey());

        $this->actingAs($this->brandingAdmin)->patch(
            route('admin.settings.background-removal.update'),
            [
                'provider' => 'remove_bg',
                'api_endpoint' => 'https://api.remove.bg/v1.0/removebg',
                'api_key' => '',
                'timeout_seconds' => 20,
                'max_source_bytes' => 5_242_880,
                'is_enabled' => true,
                'default_for_logos' => false,
            ],
        )->assertRedirect();

        $stored->refresh();
        $this->assertSame('secret-test-key-12345', $stored->api_key);
    }

    public function test_cleanup_removes_expired_staging(): void
    {
        $process = $this->stageCompletedProcess();
        $process->forceFill(['expires_at' => now()->subHour()])->save();

        Artisan::call('jetpk:branding-background-cleanup');

        $this->assertFalse(Storage::disk('local')->exists((string) $process->source_path));
        $this->assertFalse(Storage::disk('local')->exists((string) $process->result_path));
        $this->assertSame(BrandingAssetProcessStatus::Expired, $process->fresh()->status);
    }

    public function test_accepted_public_asset_remains_after_cleanup(): void
    {
        $process = $this->stageCompletedProcess();
        $this->actingAs($this->brandingAdmin)->postJson(
            route('admin.settings.branding.logo-background.accept', ['process' => $process->uuid]),
        )->assertOk();

        $acceptedPath = $this->brandingSettings->fresh()->logo_path;
        $process->forceFill(['expires_at' => now()->subHour()])->save();

        Artisan::call('jetpk:branding-background-cleanup');

        $this->assertTrue(Storage::disk('public')->exists((string) $acceptedPath));
    }

    public function test_failed_processing_never_replaces_active_logo(): void
    {
        $this->enableMockProvider(fn (): BackgroundRemovalResult => BackgroundRemovalResult::failed('provider_error', 'Failed.'));
        $this->stageAndRun();

        $this->brandingSettings->refresh();
        $this->assertSame('agencies/'.$this->brandingAgency->id.'/branding/active-logo.png', $this->brandingSettings->logo_path);
    }

    private function stageProcess(): BrandingAssetProcess
    {
        $response = $this->actingAs($this->brandingAdmin)->postJson(
            route('admin.settings.branding.logo-background.stage'),
            ['logo' => UploadedFile::fake()->image('logo.jpg', 100, 50)],
        );
        $response->assertOk();

        return BrandingAssetProcess::query()->where('uuid', $response->json('uuid'))->firstOrFail();
    }

    private function stageAndRun(): BrandingAssetProcess
    {
        $process = $this->stageProcess();
        $this->actingAs($this->brandingAdmin)->postJson(
            route('admin.settings.branding.logo-background.run', ['process' => $process->uuid]),
        )->assertOk();

        return $process->fresh();
    }

    private function stageCompletedProcess(): BrandingAssetProcess
    {
        $this->enableMockProvider(function (): BackgroundRemovalResult {
            $path = $this->writeTempPng($this->makeTransparentLogoPngBytes(96, 48));

            return new BackgroundRemovalResult(success: true, outputAbsolutePath: $path, processingMs: 9);
        });

        $process = $this->stageAndRun();
        $this->assertSame(BrandingAssetProcessStatus::Completed, $process->status);

        return $process;
    }
}
