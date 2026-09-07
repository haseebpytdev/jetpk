<?php

use App\Mail\BookingRequestReceivedMail;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 4).'/vendor/autoload.php';
$app = require dirname(__DIR__, 4).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config([
    'ota_client.slug' => 'jetpk',
    'app.url' => 'https://jetpakistan.pk',
]);

// Use an isolated in-memory sqlite for synthetic artifacts only.
config(['database.default' => 'sqlite']);
config(['database.connections.sqlite.database' => ':memory:']);
DB::purge('sqlite');
DB::reconnect('sqlite');
$app->make('Illuminate\Contracts\Console\Kernel')->call('migrate', ['--force' => true]);

$app->make(OtaFoundationSeeder::class)->run();

$out = __DIR__;
@mkdir($out, 0777, true);

$agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
$agency->forceFill([
    'name' => 'JetPakistan International Corporate Travel Management And Group Fare Coordination Limited Partnership',
])->save();

$user = User::factory()->create([
    'current_agency_id' => $agency->id,
    'name' => 'Alexandria Maximiliana Catherine-Therese von Hohenzollern-Sigmaringen-Passenger',
    'email' => 'alexandria.maximiliana.catherine.therese.von.hohenzollern.sigmaringen.passenger.longstress@example.test',
]);

$booking = Booking::factory()->for($agency)->create([
    'customer_id' => $user->id,
    'pnr' => null,
    'booking_reference' => 'JP-LONGSTRESS-BOOKING-REFERENCE-ABCDEFGHIJKLMNOPQRSTUVWXYZ-20260907-999999',
    'route' => 'LHE → KHI → DXB → LHR → JFK → ORD → LAX → HNL',
    'airline' => 'Multi-carrier alliance itinerary with extended operating carrier names',
    'currency' => 'PKR',
    'balance_due' => 987654321.99,
    'amount_paid' => 0,
    'meta' => [
        'long_data_stress' => true,
        'passenger_display_name' => 'Alexandria Maximiliana Catherine-Therese von Hohenzollern-Sigmaringen-Passenger',
        'company_name' => 'JetPakistan International Corporate Travel Management And Group Fare Coordination Limited Partnership',
    ],
]);

$html = (new BookingRequestReceivedMail($booking))->htmlBody;
$path = $out.DIRECTORY_SEPARATOR.'booking-long-data-stress.html';
file_put_contents($path, $html);
echo 'booking-long-data-stress.html bytes='.strlen($html).PHP_EOL;
echo 'booking_reference='.$booking->booking_reference.PHP_EOL;
echo 'email='.$user->email.PHP_EOL;
echo 'route='.$booking->route.PHP_EOL;
