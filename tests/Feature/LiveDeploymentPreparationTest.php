<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\BookingDocumentStatus;
use App\Enums\BookingDocumentType;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\GuestBookingAccessToken;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LiveDeploymentPreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_env_example_contains_required_ota_keys(): void
    {
        $contents = file_get_contents(base_path('.env.example'));
        $this->assertIsString($contents);

        foreach ([
            'APP_NAME=',
            'APP_ENV=',
            'APP_KEY=',
            'APP_DEBUG=',
            'APP_URL=',
            'DB_CONNECTION=',
            'DB_HOST=',
            'DB_PORT=',
            'DB_DATABASE=',
            'DB_USERNAME=',
            'DB_PASSWORD=',
            'CACHE_STORE=',
            'QUEUE_CONNECTION=',
            'SESSION_DRIVER=',
            'MAIL_MAILER=',
            'MAIL_HOST=',
            'MAIL_PORT=',
            'MAIL_USERNAME=',
            'MAIL_PASSWORD=',
            'FILESYSTEM_DISK=',
            'LOG_CHANNEL=',
            'LOG_LEVEL=',
            'OTA_DEFAULT_AGENCY_SLUG=',
            'OTA_GUEST_LOOKUP_TOKEN_MINUTES=',
            'OTA_SUPPLIER_DEFAULT_PROVIDER=',
            'OTA_PRIVATE_DOCUMENTS_DIRECTORY=',
            'OTA_PDF_TEMP_DIRECTORY=',
        ] as $requiredKey) {
            $this->assertStringContainsString($requiredKey, $contents);
        }
    }

    public function test_cleanup_expired_guest_access_command_keeps_active_and_deletes_old_expired(): void
    {
        $booking = Booking::factory()->create();

        $activeToken = GuestBookingAccessToken::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'token_hash' => hash('sha256', 'active'),
            'expires_at' => now()->addMinutes(15),
        ]);
        $recentExpired = GuestBookingAccessToken::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'token_hash' => hash('sha256', 'recent-expired'),
            'expires_at' => now()->subDays(3),
        ]);
        $oldExpired = GuestBookingAccessToken::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'token_hash' => hash('sha256', 'old-expired'),
            'expires_at' => now()->subDays(45),
        ]);

        $this->artisan('ota:cleanup-expired-access --days=30')
            ->expectsOutputToContain('OTA guest access token cleanup complete.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('guest_booking_access_tokens', ['id' => $activeToken->id]);
        $this->assertDatabaseHas('guest_booking_access_tokens', ['id' => $recentExpired->id]);
        $this->assertDatabaseMissing('guest_booking_access_tokens', ['id' => $oldExpired->id]);
    }

    public function test_storage_check_command_runs(): void
    {
        $this->artisan('ota:storage-check')
            ->expectsOutputToContain('public_disk_writable')
            ->assertExitCode(0);
    }

    public function test_backup_check_command_runs(): void
    {
        $this->artisan('ota:backup-check')
            ->expectsOutputToContain('database_connection_ok')
            ->assertExitCode(0);
    }

    public function test_security_headers_apply_on_public_and_admin_routes(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $public = $this->get(route('home'))->assertOk();
        $adminResponse = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        foreach ([$public, $adminResponse] as $response) {
            $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->assertHeader('Permissions-Policy');
        }
    }

    public function test_deployment_checklist_and_system_health_stay_admin_only(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.deployment-checklist'))->assertOk();
        $this->actingAs($admin)->get(route('admin.system-health'))->assertOk();
        $this->actingAs($staff)->get(route('admin.deployment-checklist'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.system-health'))->assertForbidden();
    }

    public function test_private_document_download_requires_policy(): void
    {
        [$customer] = $this->customerBooking();
        [, $otherBooking] = $this->customerBooking();
        $document = $this->documentForBooking($otherBooking);

        $this->actingAs($customer)->get(route('customer.documents.download', $document))->assertForbidden();
    }

    public function test_public_media_url_generation_uses_storage_path(): void
    {
        $url = Storage::disk('public')->url('agencies/test/logo.png');
        $this->assertStringContainsString('/storage/agencies/test/logo.png', $url);
    }

    public function test_queue_tables_exist_after_migration(): void
    {
        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('failed_jobs'));
        $this->assertTrue(Schema::hasTable('job_batches'));
    }

    /**
     * @return array{0: User, 1: Booking}
     */
    protected function customerBooking(): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'booking_reference' => 'BKG-'.strtoupper((string) fake()->unique()->numberBetween(1000, 9999)),
        ]);
        $booking->contact()->create(['email' => 'customer@example.test']);

        return [$customer, $booking->fresh()];
    }

    protected function documentForBooking(Booking $booking): BookingDocument
    {
        $path = 'private/agency-'.$booking->agency_id.'/bookings/'.$booking->id.'/documents/test.pdf';
        Storage::disk('local')->put($path, 'PDF FILE');

        return BookingDocument::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'document_type' => BookingDocumentType::BookingConfirmation,
            'document_number' => 'BC-'.$booking->booking_reference,
            'title' => 'Booking Confirmation',
            'file_path' => $path,
            'status' => BookingDocumentStatus::Generated,
            'generated_by' => $booking->customer_id,
            'generated_at' => now(),
        ]);
    }
}
