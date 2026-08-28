<?php

namespace Tests\Unit\GroupTicketing;

use App\Services\GroupTicketing\GroupFinalCheckoutDecisionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GroupFinalCheckoutDecisionServiceTest extends TestCase
{
    private GroupFinalCheckoutDecisionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GroupFinalCheckoutDecisionService;
    }

    #[Test]
    public function test_request_one_of_ten_is_ok(): void
    {
        $decision = $this->service->decide(1, 10, 70000, 70000);

        $this->assertSame(GroupFinalCheckoutDecisionService::DECISION_OK, $decision['decision']);
        $this->assertTrue($decision['allow_payment']);
        $this->assertTrue($decision['allow_supplier_mutation']);
    }

    #[Test]
    public function test_request_all_ten_is_ok(): void
    {
        $decision = $this->service->decide(10, 10, 70000, 70000);

        $this->assertSame(GroupFinalCheckoutDecisionService::DECISION_OK, $decision['decision']);
    }

    #[Test]
    public function test_request_eleven_of_ten_rejects_without_mutation(): void
    {
        $decision = $this->service->decide(11, 10, 70000, 70000);

        $this->assertSame(GroupFinalCheckoutDecisionService::DECISION_REDUCE_SEATS, $decision['decision']);
        $this->assertFalse($decision['allow_payment']);
        $this->assertFalse($decision['allow_supplier_mutation']);
        $this->assertTrue($decision['require_explicit_passenger_reduction']);
        $this->assertSame(10, $decision['available_seats']);
    }

    #[Test]
    public function test_stale_browser_ten_fresh_eight_opens_availability_modal(): void
    {
        $decision = $this->service->decide(10, 8, 70000, 70000);

        $this->assertSame(GroupFinalCheckoutDecisionService::DECISION_REDUCE_SEATS, $decision['decision']);
        $this->assertSame('Availability changed', $decision['modal']['title']);
        $this->assertStringContainsString('Only 8 seats', $decision['modal']['body']);
        $this->assertFalse($decision['allow_payment']);
    }

    #[Test]
    public function test_stale_four_fresh_two_opens_modal(): void
    {
        $decision = $this->service->decide(4, 2, 70000, 70000);

        $this->assertSame(GroupFinalCheckoutDecisionService::DECISION_REDUCE_SEATS, $decision['decision']);
        $this->assertSame(2, $decision['available_seats']);
        $this->assertSame('Book remaining 2 seats', $decision['modal']['primary_action']);
    }

    #[Test]
    public function test_zero_seats_sold_out_modal(): void
    {
        $decision = $this->service->decide(4, 0, 70000, 70000);

        $this->assertSame(GroupFinalCheckoutDecisionService::DECISION_SOLD_OUT, $decision['decision']);
        $this->assertStringContainsString('sold out', strtolower((string) $decision['modal']['body']));
        $this->assertNull($decision['modal']['primary_action']);
        $this->assertFalse($decision['allow_payment']);
    }

    #[Test]
    public function test_fare_change_requires_accept(): void
    {
        $decision = $this->service->decide(2, 5, 70000, 72000);

        $this->assertSame(GroupFinalCheckoutDecisionService::DECISION_FARE_CHANGED, $decision['decision']);
        $this->assertSame('Fare updated', $decision['modal']['title']);
        $this->assertFalse($decision['allow_payment']);

        $accepted = $this->service->decide(2, 5, 70000, 72000, acceptFareChange: true);
        $this->assertSame(GroupFinalCheckoutDecisionService::DECISION_OK, $accepted['decision']);
        $this->assertTrue($accepted['allow_payment']);
    }

    #[Test]
    public function test_seats_and_fare_changed_combined_modal(): void
    {
        $decision = $this->service->decide(4, 2, 70000, 72000);

        $this->assertSame(GroupFinalCheckoutDecisionService::DECISION_SEATS_AND_FARE_CHANGED, $decision['decision']);
        $this->assertTrue($decision['require_explicit_passenger_reduction']);
        $this->assertStringContainsString('72000', str_replace(',', '', (string) $decision['modal']['body']));
        $this->assertFalse($decision['allow_supplier_mutation']);
    }
}
