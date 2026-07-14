<?php

namespace Tests\Unit\Services\Suppliers\Sabre;

use App\Services\Suppliers\Sabre\Ticketing\SabreGdsTicketingResponseParser;
use Tests\TestCase;

class SabreGdsTicketingResponseParserTest extends TestCase
{
    public function test_parser_stores_safe_ticket_numbers_only(): void
    {
        $parser = new SabreGdsTicketingResponseParser;
        $json = [
            'AirTicketRS' => [
                'ApplicationResults' => ['status' => 'Complete'],
                'Ticket' => [
                    'Summary' => [
                        [
                            'DocumentNumber' => '176-1234567890',
                            'FirstName' => 'JOHN',
                            'LastName' => 'DOE',
                        ],
                    ],
                ],
            ],
        ];

        $parsed = $parser->parse($json, 'PNR123');
        $this->assertTrue($parsed['success']);
        $this->assertSame('176-1234567890', $parsed['tickets'][0]['ticket_number']);
        $this->assertArrayNotHasKey('raw', $parsed['safe_summary']);
        $encoded = json_encode($parsed);
        $this->assertStringNotContainsString('ApplicationResults', $encoded);
    }
}
