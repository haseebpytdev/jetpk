<?php

namespace Tests\Support\Sabre;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Base test case for Phase 17E HTTP booking.review flows.
 */
abstract class SabrePublicCreatePhase17ETestCase extends TestCase
{
    use RefreshDatabase;
    use SabrePublicCreatePhase17ETestSupport;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seedPhase17eFoundation();
        $this->configureSabrePublicCreatePhase17E();
    }

    protected function tearDown(): void
    {
        $this->resetHttpRecordedSnapshot();
        Http::fake();
        parent::tearDown();
    }
}
