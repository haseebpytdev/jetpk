<?php

namespace Tests\Feature\Console;

use App\Models\Agency;
use App\Models\AgencyMessageTemplate;
use App\Support\Emails\OperationalEmailDefaults;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessEmailTemplatesBackfillCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dry_run_reports_missing_rows_without_writing(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        $this->artisan('ota:backfill-business-email-templates', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(
            0,
            AgencyMessageTemplate::query()
                ->where('agency_id', $agency->id)
                ->whereIn('event', OperationalEmailDefaults::BUSINESS_OPERATIONAL_EVENT_KEYS)
                ->count(),
        );
    }

    #[Test]
    public function command_creates_missing_business_templates_without_overwriting_existing(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        AgencyMessageTemplate::query()->create([
            'agency_id' => $agency->id,
            'event' => 'booking_request_received',
            'channel' => 'email',
            'subject' => 'Custom edited subject',
            'body' => 'Custom edited body',
            'is_enabled' => true,
        ]);

        $this->artisan('ota:backfill-business-email-templates')
            ->assertSuccessful();

        $this->assertDatabaseHas('agency_message_templates', [
            'agency_id' => $agency->id,
            'event' => 'booking_request_received',
            'subject' => 'Custom edited subject',
        ]);

        $this->assertDatabaseHas('agency_message_templates', [
            'agency_id' => $agency->id,
            'event' => 'support_ticket_created',
            'is_enabled' => true,
        ]);

        $expectedCount = count(OperationalEmailDefaults::BUSINESS_OPERATIONAL_EVENT_KEYS);
        $this->assertSame(
            $expectedCount,
            AgencyMessageTemplate::query()
                ->where('agency_id', $agency->id)
                ->where('channel', 'email')
                ->whereIn('event', OperationalEmailDefaults::BUSINESS_OPERATIONAL_EVENT_KEYS)
                ->count(),
        );
    }

    #[Test]
    public function force_overwrites_existing_template_copy(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $defaults = OperationalEmailDefaults::forEvent('booking_confirmed');
        $this->assertNotNull($defaults);

        AgencyMessageTemplate::query()->create([
            'agency_id' => $agency->id,
            'event' => 'booking_confirmed',
            'channel' => 'email',
            'subject' => 'Custom edited subject',
            'body' => 'Custom edited body',
            'is_enabled' => false,
        ]);

        $this->artisan('ota:backfill-business-email-templates', ['--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('agency_message_templates', [
            'agency_id' => $agency->id,
            'event' => 'booking_confirmed',
            'subject' => $defaults['subject'],
            'body' => $defaults['body'],
            'is_enabled' => true,
        ]);
    }
}
