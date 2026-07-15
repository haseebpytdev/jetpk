<?php

namespace Tests\Unit\Support\Emails;

use App\Models\Agency;
use App\Models\AgencyMessageTemplate;
use App\Models\Booking;
use App\Services\Communication\NotificationTemplateRenderer;
use App\Support\Emails\OperationalEmailDefaults;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailBaseVariablesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function booking_request_db_template_subject_resolves_brand_name(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
        $booking = Booking::factory()->for($agency)->create();

        $defaults = OperationalEmailDefaults::forEvent('booking_request_received');
        $this->assertNotNull($defaults);

        AgencyMessageTemplate::query()->create([
            'agency_id' => $agency->id,
            'event' => 'booking_request_received',
            'channel' => 'email',
            'subject' => $defaults['subject'],
            'body' => $defaults['body'],
            'is_enabled' => true,
        ]);

        $rendered = app(NotificationTemplateRenderer::class)->render(
            $agency,
            'booking_request_received',
            'email',
            ['booking_reference' => $booking->reference_code],
            'Fallback subject',
            'Fallback body',
            $booking,
        );

        $this->assertStringNotContainsString('{{', $rendered['subject']);
        $this->assertStringNotContainsString('{{', $rendered['body']);
        $this->assertStringContainsString($booking->reference_code, $rendered['subject']);
        $this->assertStringContainsString('—', $rendered['subject']);
    }
}
