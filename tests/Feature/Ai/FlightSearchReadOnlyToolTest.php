<?php

namespace Tests\Feature\Ai;

use App\Services\Ai\Lab\FlightSearchReadOnlyExecutor;
use App\Services\FlightSearch\FlightSearchService;
use Mockery;
use Tests\TestCase;

class FlightSearchReadOnlyToolTest extends TestCase
{
    public function test_executor_calls_search_service_read_only(): void
    {
        $mock = Mockery::mock(FlightSearchService::class);
        $mock->shouldReceive('searchWithMeta')
            ->once()
            ->with(
                Mockery::on(fn (array $criteria): bool => $criteria['origin'] === 'LHE'
                    && $criteria['destination'] === 'DXB'
                    && $criteria['depart_date'] === '2026-12-15'),
                null,
                'ai_assistant_read_only',
            )
            ->andReturn([
                'offers' => [
                    [
                        'total_price' => 45000,
                        'currency' => 'PKR',
                        'marketing_carrier' => 'PK',
                        'flight_number' => 'PK-203',
                    ],
                ],
                'warnings' => [],
            ]);

        $this->app->instance(FlightSearchService::class, $mock);

        $result = app(FlightSearchReadOnlyExecutor::class)->execute([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_date' => '2026-12-15',
            'adults' => 1,
        ]);

        $this->assertStringContainsString('live option', strtolower($result['message']));
        $this->assertCount(1, $result['recommendations']);
        $this->assertTrue($result['search_record']['live_supplier_called']);
        $this->assertFalse($result['search_record']['mutation']);
    }
}
