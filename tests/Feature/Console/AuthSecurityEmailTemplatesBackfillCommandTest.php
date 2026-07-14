<?php

namespace Tests\Feature\Console;

use App\Models\Agency;
use App\Models\AgencyMessageTemplate;
use App\Support\Emails\OperationalEmailDefaults;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthSecurityEmailTemplatesBackfillCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dry_run_reports_missing_rows_without_writing(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        $this->artisan('ota:backfill-auth-email-templates', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(
            0,
            AgencyMessageTemplate::query()->where('agency_id', $agency->id)->count(),
        );
    }

    #[Test]
    public function command_creates_missing_auth_templates_without_overwriting_existing(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        AgencyMessageTemplate::query()->create([
            'agency_id' => $agency->id,
            'event' => 'admin_login_success',
            'channel' => 'email',
            'subject' => 'Custom edited subject',
            'body' => 'Custom edited body',
            'is_enabled' => true,
        ]);

        $this->artisan('ota:backfill-auth-email-templates')
            ->assertSuccessful();

        $this->assertDatabaseHas('agency_message_templates', [
            'agency_id' => $agency->id,
            'event' => 'admin_login_success',
            'subject' => 'Custom edited subject',
        ]);

        $this->assertDatabaseHas('agency_message_templates', [
            'agency_id' => $agency->id,
            'event' => 'staff_login_success',
            'is_enabled' => true,
        ]);

        $expectedCount = count(OperationalEmailDefaults::AUTH_SECURITY_EVENT_KEYS);
        $this->assertSame(
            $expectedCount,
            AgencyMessageTemplate::query()->where('agency_id', $agency->id)->where('channel', 'email')->count(),
        );
    }

    #[Test]
    public function force_overwrites_existing_template_copy(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $defaults = OperationalEmailDefaults::forEvent('admin_login_success');
        $this->assertNotNull($defaults);

        AgencyMessageTemplate::query()->create([
            'agency_id' => $agency->id,
            'event' => 'admin_login_success',
            'channel' => 'email',
            'subject' => 'Custom edited subject',
            'body' => 'Custom edited body',
            'is_enabled' => false,
        ]);

        $this->artisan('ota:backfill-auth-email-templates', ['--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('agency_message_templates', [
            'agency_id' => $agency->id,
            'event' => 'admin_login_success',
            'subject' => $defaults['subject'],
            'body' => $defaults['body'],
            'is_enabled' => true,
        ]);
    }
}
