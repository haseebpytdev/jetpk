<?php

namespace Tests\Feature;

use App\Services\Booking\BookingDraftService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

/**
 * R5: empty query search_id must not wipe draft search_id (avoids full re-shop on Traveler GET).
 */
class PassengersSearchIdPreservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_query_search_id_does_not_blank_draft_search_id(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        $draft = app(BookingDraftService::class);
        $draft->merge([
            'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
            'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
            'search_id' => 'durable-search-keep-me',
            'search_from' => 'LHE',
            'search_to' => 'DXB',
            'search_depart' => $depart,
            'adults' => 1,
        ]);

        $response = $this->getJson('/booking/passengers?'.http_build_query([
            'format' => 'json',
            'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
            'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
            // Intentionally omit search_id — must not overwrite draft.
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => $depart,
            'adults' => 1,
        ]));

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertSame('durable-search-keep-me', $draft->current()['search_id'] ?? null);
        $response->assertJsonPath('selection.search_id', 'durable-search-keep-me');
    }
}
