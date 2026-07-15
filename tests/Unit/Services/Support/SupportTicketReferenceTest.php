<?php

namespace Tests\Unit\Services\Support;

use App\Models\Agency;
use App\Services\Support\SupportTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_unique_reference_uses_compact_s_prefix(): void
    {
        Agency::factory()->create(['slug' => 'asif-travels']);
        config()->set('ota.default_agency_slug', 'asif-travels');

        $reference = app(SupportTicketService::class)->generateUniqueReference();

        $this->assertMatchesRegularExpression('/^S[A-Z2-9]{7}$/', $reference);
    }
}
