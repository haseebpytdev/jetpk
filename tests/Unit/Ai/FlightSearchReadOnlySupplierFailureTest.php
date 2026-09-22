<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Lab\FlightSearchReadOnlyExecutor;
use App\Services\FlightSearch\FlightSearchService;
use Mockery;
use Tests\TestCase;

class FlightSearchReadOnlySupplierFailureTest extends TestCase
{
    public function test_supplier_timeout_propagates_without_local_mutation(): void
    {
        $mock = Mockery::mock(FlightSearchService::class);
        $mock->shouldReceive('searchWithMeta')
            ->once()
            ->andThrow(new \RuntimeException('supplier timeout'));
        $this->app->instance(FlightSearchService::class, $mock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('supplier timeout');

        app(FlightSearchReadOnlyExecutor::class)->execute([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_date' => '2026-12-15',
            'adults' => 1,
        ]);
    }
}
