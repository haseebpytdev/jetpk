<?php

namespace Tests\Unit\Support\Bookings;

use App\Models\Agency;
use App\Models\Booking;
use App\Support\Bookings\QaFixtureBookingScope;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaFixtureBookingScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_excludes_local_qa_and_qa_fixture_meta_from_balance_sum(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agencyId = Agency::query()->firstOrFail()->id;

        Booking::factory()->create([
            'agency_id' => $agencyId,
            'supplier' => 'sabre',
            'balance_due' => 5000,
            'meta' => null,
        ]);
        Booking::factory()->create([
            'agency_id' => $agencyId,
            'supplier' => 'local_qa_inert',
            'balance_due' => 9999,
            'meta' => ['qa_fixture' => true],
        ]);
        Booking::factory()->create([
            'agency_id' => $agencyId,
            'supplier' => 'sabre',
            'balance_due' => 1111,
            'meta' => ['qa_fixture' => true],
        ]);

        $sum = (float) QaFixtureBookingScope::excludeFromOperationalMetrics(
            Booking::query()->where('agency_id', $agencyId)
        )->sum('balance_due');

        $this->assertSame(5000.0, $sum);
        $this->assertTrue(QaFixtureBookingScope::isQaFixture(['qa_fixture' => true], 'sabre'));
        $this->assertTrue(QaFixtureBookingScope::isQaFixture(null, 'local_qa_inert'));
        $this->assertFalse(QaFixtureBookingScope::isQaFixture(null, 'sabre'));
    }
}
