<?php

use App\Mail\AbandonedFlightSearchMail;
use App\Mail\BookingRequestReceivedMail;
use App\Mail\CustomerWelcomeMail;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use App\Support\Emails\AuthEmailRenderer;
use App\Support\Emails\OtaOperationalEmailRenderer;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 4).'/vendor/autoload.php';
$app = require dirname(__DIR__, 4).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config([
    'ota_client.slug' => 'jetpk',
    'app.url' => 'https://jetpakistan.pk',
]);

$app->make(OtaFoundationSeeder::class)->run();

$out = __DIR__;
@mkdir($out, 0777, true);

$agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
$user = User::factory()->create([
    'current_agency_id' => $agency->id,
    'name' => 'Ada Lovelace',
    'email' => 'ada.p2-artifact@example.test',
]);
$booking = Booking::factory()->for($agency)->create([
    'customer_id' => $user->id,
    'pnr' => null,
]);

$writes = [
    'auth-admin-login.html' => app(AuthEmailRenderer::class)->loginSecurity([
        'event' => 'admin_login_success',
        'type' => 'auth_privileged_login_success',
        'title' => 'Login successful',
        'status_label' => 'Security notice',
        'greeting_name' => 'Admin',
        'intro' => 'A login to your account was detected.',
        'notes' => ['Time: 07 Sep 2026 10:00 PKT', 'IP address: 203.0.113.10', 'Device / browser: TestBrowser'],
        'cta' => [['label' => 'Reset password', 'url' => 'https://jetpakistan.pk/forgot-password']],
    ])->html,
    'welcome-customer.html' => (new CustomerWelcomeMail($user, 'JetPakistan'))->htmlBody,
    'booking-confirmed-customer.html' => (new BookingRequestReceivedMail($booking))->htmlBody,
    'daily-admin-report.html' => app(OtaOperationalEmailRenderer::class)->wrapStoredBody(
        $agency,
        'booking_manual_review_required',
        'Manual review required',
        'A booking requires manual review.',
        ['booking_reference' => (string) $booking->reference_code, 'admin_booking_url' => 'https://jetpakistan.pk/admin'],
    )->html,
    'abandoned-search.html' => (new AbandonedFlightSearchMail(
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
];

foreach ($writes as $name => $html) {
    file_put_contents($out.DIRECTORY_SEPARATOR.$name, $html);
    echo $name.' bytes='.strlen($html).PHP_EOL;
}
