<?php

namespace Tests\Unit\Support\Suppliers;

use App\Support\Suppliers\SabreNdcGroupedItineraryMessageExtractor;
use App\Support\Suppliers\SabreNdcOfferShopSafeErrorExtractor;
use Tests\TestCase;

class SabreNdcGroupedItineraryMessageExtractorTest extends TestCase
{
    public function test_message_27131_extracted_as_code_not_transaction_id(): void
    {
        $extractor = new SabreNdcGroupedItineraryMessageExtractor;
        $result = $extractor->extract([
            'groupedItineraryResponse' => [
                'itineraryGroups' => [],
                'messages' => [[
                    'type' => 'INFO',
                    'code' => '27131',
                    'text' => 'No offers available for requested itinerary.',
                ]],
                'transactionId' => 'sabre_GCC14-ISELL-REFTXN-12345',
            ],
        ]);

        $this->assertSame('27131', $result['message_code']);
        $this->assertSame('No offers available for requested itinerary.', $result['message_text']);
        $this->assertSame('sabre_GCC14-ISELL-REFTXN-12345', $result['sabre_transaction_id']);
    }

    public function test_sabre_server_pattern_numeric_text_becomes_message_code(): void
    {
        $extractor = new SabreNdcGroupedItineraryMessageExtractor;
        $result = $extractor->extract([
            'groupedItineraryResponse' => [
                'itineraryGroups' => [],
                'messages' => [[
                    'type' => 'SERVER',
                    'code' => 'GCA14-ISELL-TN-00-2025-03-00-MXK4',
                    'text' => '27131',
                ]],
            ],
        ]);

        $this->assertSame('27131', $result['message_code']);
        $this->assertNull($result['message_text']);
        $this->assertSame('GCA14-ISELL-TN-00-2025-03-00-MXK4', $result['sabre_transaction_id']);
    }

    public function test_transaction_like_message_code_is_not_used_as_message_code(): void
    {
        $extractor = new SabreNdcGroupedItineraryMessageExtractor;
        $result = $extractor->extract([
            'groupedItineraryResponse' => [
                'itineraryGroups' => [],
                'messages' => [[
                    'type' => 'INFO',
                    'code' => 'sabre_GCC14-ISELL-REFTXN-12345',
                ]],
            ],
        ]);

        $this->assertNull($result['message_code']);
        $this->assertSame('sabre_GCC14-ISELL-REFTXN-12345', $result['sabre_transaction_id']);
    }

    public function test_safe_error_extractor_separates_code_message_and_transaction_id(): void
    {
        $extractor = app(SabreNdcOfferShopSafeErrorExtractor::class);
        $result = $extractor->extract(200, [
            'groupedItineraryResponse' => [
                'itineraryGroups' => [],
                'messages' => [[
                    'type' => 'INFO',
                    'code' => '27131',
                    'text' => 'No offers available for requested itinerary.',
                ]],
                'transactionId' => 'sabre_GCC14-ISELL-REFTXN-12345',
            ],
        ], null, [
            'offer_count_raw' => 1,
            'normalized_offer_count' => 1,
        ]);

        $this->assertSame('No offers available for requested itinerary.', $result['safe_error_message']);
        $this->assertSame('sabre_GCC14-ISELL-REFTXN-12345', $result['sabre_transaction_id']);
        $this->assertStringNotContainsString('GCC14', (string) $result['safe_error_family']);
    }
}
