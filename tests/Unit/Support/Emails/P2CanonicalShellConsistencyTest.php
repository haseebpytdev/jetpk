<?php

namespace Tests\Unit\Support\Emails;

use App\Mail\AbandonedFlightSearchMail;
use App\Mail\BookingRequestReceivedMail;
use App\Mail\CustomerWelcomeMail;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use App\Support\Emails\AuthEmailRenderer;
use App\Support\Emails\CustomerFacingEmailRenderer;
use App\Support\Emails\OtaOperationalEmailRenderer;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class P2CanonicalShellConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ota_client.slug' => 'jetpk',
            'app.url' => 'https://jetpakistan.pk',
        ]);
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_representative_families_use_jetpakistan_shell_without_legacy_markers(): void
    {
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
        $user = User::factory()->create([
            'current_agency_id' => $agency->id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
        ]);
        $booking = Booking::factory()->for($agency)->create([
            'customer_id' => $user->id,
            'pnr' => null,
        ]);

        $samples = [
            app(AuthEmailRenderer::class)->customerWelcome($user, $agency->name ?? 'JetPakistan')->html,
            app(AuthEmailRenderer::class)->loginSecurity([
                'event' => 'admin_login_success',
                'type' => 'auth_privileged_login_success',
                'title' => 'Login successful',
                'status_label' => 'Security notice',
                'greeting_name' => 'Admin',
                'intro' => 'A login to your account was detected.',
                'notes' => ['Time: now', 'IP address: 203.0.113.10', 'Device / browser: Test'],
                'cta' => [['label' => 'Reset password', 'url' => 'https://jetpakistan.pk/forgot-password']],
            ])->html,
            app(CustomerFacingEmailRenderer::class)->bookingRequestReceived($booking)->html,
            app(OtaOperationalEmailRenderer::class)->wrapStoredBody(
                $agency,
                'booking_manual_review_required',
                'Manual review required',
                'A booking requires manual review.',
                ['booking_reference' => (string) $booking->reference],
            )->html,
            (new AbandonedFlightSearchMail(
                subjectLine: 'Still interested?',
                brandName: 'JetPakistan',
                supportEmail: 'support@jetpakistan.pk',
                supportPhone: '',
                routeLabel: 'LHE → DXB',
                tripTypeLabel: 'One way',
                departDate: '2026-10-01',
                returnDate: null,
                passengerSummary: '1 adult',
                offers: [[
                    'airline_name' => 'Emirates',
                    'airline_code' => 'EK',
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => '01 Oct 2026 08:00',
                    'arrival_at' => '01 Oct 2026 10:30',
                    'duration' => '2h 30m',
                    'stops_label' => 'Direct',
                    'price_label' => 'PKR 45,000',
                ]],
                ctaUrl: 'https://jetpakistan.pk/flights',
                agency: $agency,
            ))->htmlBody,
            (new CustomerWelcomeMail($user, 'JetPakistan'))->htmlBody ?? '',
            (new BookingRequestReceivedMail($booking))->htmlBody ?? '',
        ];

        foreach (array_filter($samples) as $html) {
            $this->assertStringContainsString('jetpk-container', $html);
            $this->assertStringContainsString('max-width:640px', $html);
            $this->assertStringNotContainsString('emails.layouts.modern', $html);
            $this->assertStringNotContainsString('Operational Alert', $html);
            $this->assertStringNotContainsString('localhost', $html);
            $this->assertStringNotContainsString('127.0.0.1', $html);
            $this->assertStringNotContainsString('PNR: null', $html);
            $this->assertStringNotContainsString('Phone: null', $html);
            $this->assertStringNotContainsString('\\u2014', $html);
        }
    }
}
