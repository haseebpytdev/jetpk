<?php

namespace Tests\Unit\FlightResults;

use App\Http\Controllers\Frontend\FlightController;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Proves cheapest sort uses numeric final_customer_price, not string order.
 */
class CheapestNumericSortTest extends TestCase
{
    public function test_cheapest_orders_by_final_customer_price_numerically(): void
    {
        $controller = app(FlightController::class);
        $method = new ReflectionMethod(FlightController::class, 'sortOffers');
        $method->setAccessible(true);

        $offers = [
            ['offer_id' => 'a', 'final_customer_price' => 100000, 'price_confirmed' => true, 'inquiry_only' => false],
            ['offer_id' => 'b', 'final_customer_price' => 80000, 'price_confirmed' => true, 'inquiry_only' => false],
            ['offer_id' => 'c', 'final_customer_price' => 120000, 'price_confirmed' => true, 'inquiry_only' => false],
            ['offer_id' => 'd', 'final_customer_price' => 90000, 'price_confirmed' => true, 'inquiry_only' => false],
        ];

        $sorted = $method->invoke($controller, $offers, 'cheapest', []);

        $this->assertSame('b', $sorted[0]['offer_id']);
        $this->assertSame(80000.0, (float) $sorted[0]['final_customer_price']);
    }

    public function test_cheapest_does_not_use_string_order_for_formatted_like_amounts(): void
    {
        $controller = app(FlightController::class);
        $method = new ReflectionMethod(FlightController::class, 'sortOffers');
        $method->setAccessible(true);

        // String sort would put 100500 before 9999; numeric must put 9999 first.
        $offers = [
            ['offer_id' => 'high', 'final_customer_price' => 100500, 'price_confirmed' => true, 'inquiry_only' => false],
            ['offer_id' => 'mid', 'final_customer_price' => 81326, 'price_confirmed' => true, 'inquiry_only' => false],
            ['offer_id' => 'low', 'final_customer_price' => 9999, 'price_confirmed' => true, 'inquiry_only' => false],
        ];

        $sorted = $method->invoke($controller, $offers, 'cheapest', []);

        $this->assertSame(['low', 'mid', 'high'], array_column($sorted, 'offer_id'));
        $this->assertSame(9999.0, (float) $sorted[0]['final_customer_price']);
    }
}
