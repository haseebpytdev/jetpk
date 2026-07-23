<?php

namespace Tests\Support\OneApi;

use App\Support\OneApi\OneApiFixtureTransportScope;

trait OneApiEnablesFixtureTransport
{
    protected function enableOneApiFixtureTransport(string $reason = 'phpunit'): void
    {
        OneApiFixtureTransportScope::enable($reason);
    }

    protected function disableOneApiFixtureTransport(): void
    {
        OneApiFixtureTransportScope::disable();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableOneApiFixtureTransport('phpunit');
    }

    protected function tearDown(): void
    {
        $this->disableOneApiFixtureTransport();
        OneApiFixtureTransportScope::allowUnitTestFixtures();
        parent::tearDown();
    }
}
