<?php

namespace Tests\Unit\GuestBooking;

use App\Support\GuestBooking\LegacyGuestTokenUsageTelemetry;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LegacyGuestTokenUsageTelemetryTest extends TestCase
{
    public function test_outcome_classification_from_status_only(): void
    {
        $this->assertSame('success', LegacyGuestTokenUsageTelemetry::outcomeFromStatus(200));
        $this->assertSame('success', LegacyGuestTokenUsageTelemetry::outcomeFromStatus(302));
        $this->assertSame('denied', LegacyGuestTokenUsageTelemetry::outcomeFromStatus(403));
        $this->assertSame('denied', LegacyGuestTokenUsageTelemetry::outcomeFromStatus(401));
        $this->assertSame('validation_failure', LegacyGuestTokenUsageTelemetry::outcomeFromStatus(422));
        $this->assertSame('validation_failure', LegacyGuestTokenUsageTelemetry::outcomeFromStatus(419));
        $this->assertSame('other_failure', LegacyGuestTokenUsageTelemetry::outcomeFromStatus(404));
        $this->assertSame('other_failure', LegacyGuestTokenUsageTelemetry::outcomeFromStatus(500));
    }

    public function test_route_category_map_covers_six_legacy_routes_only(): void
    {
        $this->assertSame('guest_detail_exchange', LegacyGuestTokenUsageTelemetry::categoryForRouteName('guest.bookings.exchange'));
        $this->assertSame('payment_proof', LegacyGuestTokenUsageTelemetry::categoryForRouteName('guest.bookings.payment-proof.legacy'));
        $this->assertSame('promo_apply', LegacyGuestTokenUsageTelemetry::categoryForRouteName('guest.bookings.promo.apply.legacy'));
        $this->assertSame('promo_remove', LegacyGuestTokenUsageTelemetry::categoryForRouteName('guest.bookings.promo.remove.legacy'));
        $this->assertSame('abhipay_start', LegacyGuestTokenUsageTelemetry::categoryForRouteName('guest.bookings.abhipay.start.legacy'));
        $this->assertSame('cancellation', LegacyGuestTokenUsageTelemetry::categoryForRouteName('guest.bookings.cancellations.store.legacy'));

        $this->assertNull(LegacyGuestTokenUsageTelemetry::categoryForRouteName('guest.bookings.show'));
        $this->assertNull(LegacyGuestTokenUsageTelemetry::categoryForRouteName('guest.bookings.payment-proof'));
        $this->assertNull(LegacyGuestTokenUsageTelemetry::categoryForRouteName(null));
    }

    public function test_record_writes_allowlisted_fields_only(): void
    {
        Cache::flush();
        LegacyGuestTokenUsageTelemetry::enableTestSink();

        LegacyGuestTokenUsageTelemetry::record('guest_detail_exchange', 'success', 'GET');
        $records = LegacyGuestTokenUsageTelemetry::drainTestSink();

        $this->assertCount(1, $records);
        $keys = array_keys($records[0]);
        sort($keys);
        $allowed = LegacyGuestTokenUsageTelemetry::ALLOWED_CONTEXT_KEYS;
        sort($allowed);
        $this->assertSame($allowed, $keys);
        $this->assertSame(LegacyGuestTokenUsageTelemetry::EVENT, $records[0]['event']);
        $this->assertSame('guest_detail_exchange', $records[0]['route_category']);
        $this->assertSame('GET', $records[0]['method']);
        $this->assertSame('success', $records[0]['outcome']);
    }

    public function test_flood_gate_limits_writes_per_category_per_minute(): void
    {
        Cache::flush();
        LegacyGuestTokenUsageTelemetry::enableTestSink();

        $limit = LegacyGuestTokenUsageTelemetry::MAX_WRITES_PER_CATEGORY_PER_MINUTE;
        for ($i = 0; $i < $limit + 10; $i++) {
            LegacyGuestTokenUsageTelemetry::record('payment_proof', 'denied', 'POST');
        }

        $this->assertCount($limit, LegacyGuestTokenUsageTelemetry::drainTestSink());
    }

    public function test_unknown_category_is_ignored(): void
    {
        LegacyGuestTokenUsageTelemetry::enableTestSink();
        LegacyGuestTokenUsageTelemetry::record('not_a_real_category', 'success', 'GET');
        $this->assertSame([], LegacyGuestTokenUsageTelemetry::drainTestSink());
    }
}
