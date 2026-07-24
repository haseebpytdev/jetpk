<?php

namespace Tests\Unit\Services\Suppliers\OneApi;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\OneApi\Booking\OneApiBookingService;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContextStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OneApiWorkflowPropagationTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_rejected_without_final_price_confirmed(): void
    {
        $connection = $this->connection();
        $context = app(OneApiWorkflowContextStore::class)->create($connection->id, 'corr', []);
        $booking = Booking::factory()->create([
            'agency_id' => $connection->agency_id,
            'supplier' => SupplierProvider::OneApi->value,
            'meta' => ['one_api_context' => ['workflow_context_id' => $context->contextId]],
        ]);
        $user = User::factory()->create(['current_agency_id' => $connection->agency_id]);
        $result = app(OneApiBookingService::class)->createSupplierBooking($booking, $connection, $user, []);
        $this->assertFalse($result->success);
        $this->assertSame('stale_offer', $result->error_code);
    }

    public function test_stale_catalog_selection_rejected(): void
    {
        $connection = $this->connection();
        $store = app(OneApiWorkflowContextStore::class);
        $context = $store->create($connection->id, 'corr', []);
        $context->moneySnapshot = ['price_confirmed' => true, 'catalog_registry' => []];
        $store->put($context);

        $user = User::factory()->create(['current_agency_id' => $connection->agency_id]);
        $this->actingAs($user)->postJson(route('booking.one-api.final-price'), [
            'workflow_context_id' => $context->contextId,
            'supplier_connection_id' => $connection->id,
            'bundle_selection_ids' => ['oa_sel_missing'],
        ])->assertStatus(422);
    }

    private function connection(): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'credentials' => [
                'username' => 'ONE_API_TEST_USERNAME',
                'password' => 'ONE_API_TEST_PASSWORD',
                'agent_code' => 'ONE_API_TEST_AGENT',
                'agent_preferred_currency' => 'AED',
                'pos_country' => 'AE',
                'pos_station' => 'DXB',
                'rest_auth_url' => 'https://example.test/auth',
                'rest_search_url' => 'https://example.test/search',
                'soap_url' => 'https://example.test/soap',
            ],
        ]);
    }
}
