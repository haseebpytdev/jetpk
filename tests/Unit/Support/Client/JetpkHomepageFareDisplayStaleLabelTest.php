<?php

namespace Tests\Unit\Support\Client;

use App\Enums\JetpkHomepageFareRefreshStatus;
use App\Support\Client\JetpkHomepageFareDisplay;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class JetpkHomepageFareDisplayStaleLabelTest extends TestCase
{
    public function test_fresh_cache_labels_as_success_cheapest(): void
    {
        $result = JetpkHomepageFareDisplay::resolve([], [
            'resolved_fare' => 54000,
            'resolved_currency' => 'PKR',
            'fare_refreshed_at' => now()->toIso8601String(),
        ]);

        $this->assertNotNull($result);
        $this->assertSame(JetpkHomepageFareRefreshStatus::Success->value, $result['status']);
        $this->assertSame('PKR 54,000', $result['label']);
    }

    public function test_stale_cache_not_labeled_current_when_stale_display_disabled(): void
    {
        config(['jetpk_homepage.allow_stale_fare_display' => false]);

        $result = JetpkHomepageFareDisplay::resolve([], [
            'resolved_fare' => 54000,
            'resolved_currency' => 'PKR',
            'fare_refreshed_at' => Carbon::now()->subDays(3)->toIso8601String(),
        ]);

        $this->assertNull($result);
    }

    public function test_neutral_label_is_check_fare(): void
    {
        $this->assertSame('Check fare', JetpkHomepageFareDisplay::neutralAvailabilityLabel());
    }
}
