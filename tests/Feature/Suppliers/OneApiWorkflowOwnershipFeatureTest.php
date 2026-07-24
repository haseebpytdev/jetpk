<?php

namespace Tests\Feature\Suppliers;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContextStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OneApiWorkflowOwnershipFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_catalog_request_is_rejected(): void
    {
        $agency = Agency::factory()->create();
        $connection = $this->connection($agency->id);
        $context = app(OneApiWorkflowContextStore::class)->create($connection->id, 'c1', []);

        $this->getJson(route('booking.one-api.catalog', [
            'workflow_context_id' => $context->contextId,
            'supplier_connection_id' => $connection->id,
        ]))->assertUnauthorized();
    }

    public function test_guard_denies_cross_user_context_access(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->create(['current_agency_id' => $agency->id]);
        $intruder = User::factory()->create(['current_agency_id' => $agency->id]);
        $connection = $this->connection($agency->id);
        $context = app(OneApiWorkflowContextStore::class)->create($connection->id, 'c1', [], $agency->id, $owner->id);
        $context->moneySnapshot = ['price_confirmed' => true, 'bundles' => []];
        app(OneApiWorkflowContextStore::class)->put($context);

        $reloaded = app(OneApiWorkflowContextStore::class)->get($context->contextId);
        $this->assertSame($owner->id, $reloaded?->ownerUserId);

        try {
            app(\App\Support\OneApi\OneApiWorkflowContextGuard::class)->authorizeHttp(
                $intruder,
                $connection,
                $reloaded,
                'intruder-session',
            );
            $this->fail('Expected workflow access denial.');
        } catch (\App\Services\Suppliers\OneApi\Exceptions\OneApiValidationException $exception) {
            $this->assertSame(404, $exception->httpStatus);
        }
    }

    public function test_http_cross_user_catalog_denied(): void
    {
        Http::fake(['*' => Http::response('<soapenv:Envelope/>', 200)]);
        $agency = Agency::factory()->create();
        $owner = User::factory()->create(['current_agency_id' => $agency->id]);
        $intruder = User::factory()->create(['current_agency_id' => $agency->id]);
        $connection = $this->connection($agency->id);
        $context = app(OneApiWorkflowContextStore::class)->create($connection->id, 'c1', [], $agency->id, $owner->id);
        $context->moneySnapshot = ['price_confirmed' => true, 'bundles' => []];
        app(OneApiWorkflowContextStore::class)->put($context);

        $this->actingAs($intruder)->getJson(route('booking.one-api.catalog', [
            'workflow_context_id' => $context->contextId,
            'supplier_connection_id' => $connection->id,
        ]))->assertStatus(404);
    }

    public function test_wrong_supplier_connection_returns_not_found(): void
    {
        Http::fake(['*' => Http::response('<soapenv:Envelope/>', 200)]);
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['current_agency_id' => $agency->id]);
        $connectionA = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'name' => 'One API A',
            'provider' => SupplierProvider::OneApi,
            'credentials' => $this->credentials(),
        ]);
        $connectionB = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'name' => 'One API B',
            'provider' => SupplierProvider::OneApi,
            'credentials' => $this->credentials(),
        ]);
        $context = app(OneApiWorkflowContextStore::class)->create($connectionA->id, 'c1', [], $agency->id, $user->id);
        $context->moneySnapshot = ['price_confirmed' => true, 'bundles' => []];
        app(OneApiWorkflowContextStore::class)->put($context);

        $this->actingAs($user)->getJson(route('booking.one-api.catalog', [
            'workflow_context_id' => $context->contextId,
            'supplier_connection_id' => $connectionB->id,
        ]))->assertStatus(404);
    }

    public function test_fixture_path_in_final_price_body_is_rejected(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['current_agency_id' => $agency->id]);
        $connection = $this->connection($agency->id);
        $context = app(OneApiWorkflowContextStore::class)->create($connection->id, 'c1', [], $agency->id, $user->id);
        $context->moneySnapshot = ['price_confirmed' => true];
        app(OneApiWorkflowContextStore::class)->put($context);

        $this->actingAs($user)->postJson(route('booking.one-api.final-price'), [
            'workflow_context_id' => $context->contextId,
            'supplier_connection_id' => $connection->id,
            'fixture_path' => base_path('tests/Fixtures/Suppliers/OneApi/price_base.xml'),
        ])->assertStatus(422);
    }

    private function connection(int $agencyId): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'agency_id' => $agencyId,
            'name' => 'One API '.uniqid('', true),
            'provider' => SupplierProvider::OneApi,
            'credentials' => $this->credentials(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function credentials(): array
    {
        return [
            'username' => 'ONE_API_TEST_USERNAME',
            'password' => 'ONE_API_TEST_PASSWORD',
            'agent_code' => 'AGENT',
            'rest_auth_url' => 'https://example.test/auth',
            'rest_search_url' => 'https://example.test/search',
            'soap_url' => 'https://example.test/soap',
        ];
    }
}
