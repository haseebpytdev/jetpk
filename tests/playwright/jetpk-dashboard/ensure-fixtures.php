<?php

/**
 * Idempotent local fixtures for JetPK dashboard Playwright audits.
 * Safe: creates demo bookings only when the demo customer/agent have none.
 */
require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;

$customer = User::query()->where('email', 'customer@ota.demo')->first();
$agent = User::query()->where('email', 'agent@ota.demo')->first();

if (! $customer || ! $agent) {
    fwrite(STDERR, "Demo users missing; run OtaFoundationSeeder first.\n");
    exit(1);
}

$agency = Agency::query()->find($agent->current_agency_id) ?? Agency::query()->first();
if (! $agency) {
    fwrite(STDERR, "No agency available for Playwright fixtures.\n");
    exit(1);
}

if ($customer->current_agency_id !== $agency->id) {
    $customer->forceFill(['current_agency_id' => $agency->id])->save();
    $agency->users()->syncWithoutDetaching([$customer->id => ['role' => 'customer']]);
}

$agentModel = $agent->agent();
if (! $agentModel) {
    fwrite(STDERR, "Demo agent user has no agent profile.\n");
    exit(1);
}

$customerBooking = Booking::query()
    ->where('customer_id', $customer->id)
    ->where('agency_id', $agency->id)
    ->first();

if (! $customerBooking) {
    $customerBooking = Booking::factory()->create([
        'agency_id' => $agency->id,
        'agent_id' => $agentModel->id,
        'customer_id' => $customer->id,
        'status' => BookingStatus::PaymentPending,
        'payment_status' => 'unpaid',
        'booking_reference' => 'BKG-PW-CUST-001',
        'route' => 'LHE-DXB',
        'travel_date' => now()->addDays(14)->toDateString(),
    ]);
    $customerBooking->contact()->create([
        'email' => $customer->email,
        'phone' => '03001234567',
        'country' => 'PK',
        'address_line' => 'Playwright fixture',
    ]);
    echo "Created customer Playwright booking {$customerBooking->display_reference}\n";
}

$agentBooking = Booking::query()
    ->where('agency_id', $agency->id)
    ->where('booking_reference', 'BKG-PW-AGENT-001')
    ->first();

if (! $agentBooking) {
    $agentBooking = Booking::factory()->create([
        'agency_id' => $agency->id,
        'agent_id' => $agentModel->id,
        'customer_id' => null,
        'status' => BookingStatus::Confirmed,
        'payment_status' => 'paid',
        'booking_reference' => 'BKG-PW-AGENT-001',
        'route' => 'ISB-LHR',
        'travel_date' => now()->addDays(21)->toDateString(),
        'pnr' => 'PW1234',
    ]);
    $agentBooking->contact()->create([
        'email' => 'fixture@example.test',
        'phone' => '03007654321',
        'country' => 'PK',
        'address_line' => 'Playwright fixture',
    ]);
    echo "Created agent Playwright booking {$agentBooking->display_reference}\n";
} elseif ($agentBooking->agent_id !== $agentModel->id) {
    $agentBooking->forceFill(['agent_id' => $agentModel->id])->save();
    echo "Updated agent Playwright booking agent_id for {$agentBooking->display_reference}\n";
}

$fixturePath = storage_path('app/playwright/jetpk-dashboard-fixtures.json');
if (! is_dir(dirname($fixturePath))) {
    mkdir(dirname($fixturePath), 0775, true);
}

file_put_contents($fixturePath, json_encode([
    'customerBookingPath' => '/customer/bookings/'.$customerBooking->getKey(),
    'agentBookingPath' => '/agent/bookings/'.$agentBooking->getKey(),
    'customerBookingReference' => $customerBooking->display_reference,
    'agentBookingReference' => $agentBooking->display_reference,
], JSON_PRETTY_PRINT));

echo "JetPK dashboard Playwright fixtures OK\n";
