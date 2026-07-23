<?php

namespace Tests\Unit\Services\Suppliers\OneApi;

use App\Contracts\Suppliers\OneApi\OneApiSoapTransportContract;
use App\Services\Suppliers\OneApi\Transport\FixtureOneApiSoapTransport;
use App\Services\Suppliers\OneApi\Transport\LiveOneApiSoapTransport;
use App\Support\OneApi\OneApiFixtureTransportScope;
use Tests\TestCase;

class OneApiTransportBindingTest extends TestCase
{
    public function test_production_scope_resolves_live_transport(): void
    {
        OneApiFixtureTransportScope::disable();
        OneApiFixtureTransportScope::disallowUnitTestFixtures();

        $transport = app(OneApiSoapTransportContract::class);
        $this->assertInstanceOf(LiveOneApiSoapTransport::class, $transport);
    }

    public function test_fixture_scope_resolves_fixture_transport(): void
    {
        OneApiFixtureTransportScope::enable('fixture_command');

        $transport = app(OneApiSoapTransportContract::class);
        $this->assertInstanceOf(FixtureOneApiSoapTransport::class, $transport);

        OneApiFixtureTransportScope::disable();
    }
}
