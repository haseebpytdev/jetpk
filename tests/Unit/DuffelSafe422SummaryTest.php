<?php

namespace Tests\Unit;

use App\Services\Suppliers\Duffel\DuffelSafe422Summary;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\TestCase;

class DuffelSafe422SummaryTest extends TestCase
{
    public function test_summarize_extracts_safe_fields_only(): void
    {
        $response = new Response(new \GuzzleHttp\Psr7\Response(422, [], json_encode([
            'errors' => [[
                'type' => 'validation_error',
                'title' => 'Offer expired',
                'detail' => 'This offer is no longer valid',
                'code' => 'offer_expired',
                'source' => ['pointer' => '/data/offer_id'],
            ]],
        ], JSON_THROW_ON_ERROR)));

        $summary = DuffelSafe422Summary::fromResponse($response);

        $this->assertCount(1, $summary['duffel_errors']);
        $row = $summary['duffel_errors'][0];
        $this->assertSame('offer_expired', $row['code']);
        $this->assertStringContainsString('Offer expired', $row['title']);
        $this->assertSame('/data/offer_id', $row['source_pointer']);
    }

    public function test_indicates_unavailable_for_offer_expired_code(): void
    {
        $errors = [[
            'code' => 'offer_expired',
            'title' => '',
            'detail' => '',
            'source_pointer' => '',
            'type' => '',
        ]];

        $this->assertTrue(DuffelSafe422Summary::indicatesUnavailableOrExpiredOffer($errors));
    }

    public function test_indicates_unavailable_for_resource_not_found_on_offer(): void
    {
        $errors = [[
            'code' => 'resource_not_found',
            'title' => '',
            'detail' => 'offer not found',
            'source_pointer' => '',
            'type' => '',
        ]];

        $this->assertTrue(DuffelSafe422Summary::indicatesUnavailableOrExpiredOffer($errors));
    }

    public function test_does_not_treat_generic_validation_as_unavailable(): void
    {
        $errors = [[
            'code' => 'validation_error',
            'title' => 'Invalid passenger type',
            'detail' => '',
            'source_pointer' => '/data/passengers',
            'type' => '',
        ]];

        $this->assertFalse(DuffelSafe422Summary::indicatesUnavailableOrExpiredOffer($errors));
    }
}
