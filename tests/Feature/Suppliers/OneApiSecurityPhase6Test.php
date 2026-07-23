<?php

namespace Tests\Feature\Suppliers;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\OneApi\Transport\OneApiFixtureCaseCatalog;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContextStore;
use App\Support\OneApi\OneApiWorkflowFingerprint;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OneApiSecurityPhase6Test extends TestCase
{
    use RefreshDatabase;

    public function test_expired_context_denied_on_catalog(): void
    {
        Http::fake(['*' => Http::response('<soapenv:Envelope/>', 200)]);
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['current_agency_id' => $agency->id]);
        $connection = $this->connection($agency->id);
        $context = app(OneApiWorkflowContextStore::class)->create($connection->id, 'c1', [], $agency->id, $user->id);
        $context->expiresAtIso = CarbonImmutable::now()->subMinute()->toIso8601String();
        $context->moneySnapshot = ['price_confirmed' => true, 'bundles' => []];
        app(OneApiWorkflowContextStore::class)->put($context);

        $this->actingAs($user)->getJson(route('booking.one-api.catalog', [
            'workflow_context_id' => $context->contextId,
            'supplier_connection_id' => $connection->id,
        ]))->assertStatus(404);
    }

    public function test_tampered_signed_offer_fingerprint_denied(): void
    {
        Http::fake(['*' => Http::response('<soapenv:Envelope/>', 200)]);
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['current_agency_id' => $agency->id]);
        $connection = $this->connection($agency->id);
        $payload = ['segments' => [['origin' => 'SHJ', 'destination' => 'KHI']], 'trip_type' => 'one_way'];
        $context = app(OneApiWorkflowContextStore::class)->create($connection->id, 'c1', $payload, $agency->id, $user->id);
        $context->signedOfferPayload = array_merge($payload, ['segments' => [['origin' => 'DXB', 'destination' => 'KHI']]]);
        $context->moneySnapshot = ['price_confirmed' => true, 'bundles' => []];
        app(OneApiWorkflowContextStore::class)->put($context);

        $this->actingAs($user)->getJson(route('booking.one-api.catalog', [
            'workflow_context_id' => $context->contextId,
            'supplier_connection_id' => $connection->id,
        ]))->assertStatus(404);
    }

    public function test_completed_lifecycle_denied(): void
    {
        Http::fake(['*' => Http::response('<soapenv:Envelope/>', 200)]);
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['current_agency_id' => $agency->id]);
        $connection = $this->connection($agency->id);
        $context = app(OneApiWorkflowContextStore::class)->create($connection->id, 'c1', [], $agency->id, $user->id);
        $context->lifecycleStatus = 'completed';
        $context->moneySnapshot = ['price_confirmed' => true, 'bundles' => []];
        app(OneApiWorkflowContextStore::class)->put($context);

        $this->actingAs($user)->getJson(route('booking.one-api.catalog', [
            'workflow_context_id' => $context->contextId,
            'supplier_connection_id' => $connection->id,
        ]))->assertStatus(404);
    }

    public function test_unknown_fixture_key_rejected(): void
    {
        $this->expectException(\App\Services\Suppliers\OneApi\Exceptions\OneApiValidationException::class);
        OneApiFixtureCaseCatalog::resolvePath('not_a_real_fixture_key');
    }

    public function test_session_fingerprint_mismatch_denied(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->create(['current_agency_id' => $agency->id]);
        $connection = $this->connection($agency->id);
        $context = app(OneApiWorkflowContextStore::class)->create($connection->id, 'c1', [], $agency->id, $owner->id);
        $context->sessionFingerprint = OneApiWorkflowFingerprint::session('bound-session');
        app(OneApiWorkflowContextStore::class)->put($context);
        $reloaded = app(OneApiWorkflowContextStore::class)->get($context->contextId);
        $this->assertNotNull($reloaded);

        try {
            app(\App\Support\OneApi\OneApiWorkflowContextGuard::class)->authorizeHttp(
                $owner,
                $connection,
                $reloaded,
                'different-session-id',
            );
            $this->fail('Expected session mismatch denial.');
        } catch (\App\Services\Suppliers\OneApi\Exceptions\OneApiValidationException $exception) {
            $this->assertSame(404, $exception->httpStatus);
        }
    }

    private function connection(int $agencyId): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'agency_id' => $agencyId,
            'name' => 'One API '.uniqid('', true),
            'provider' => SupplierProvider::OneApi,
            'credentials' => [
                'username' => 'ONE_API_TEST_USERNAME',
                'password' => 'ONE_API_TEST_PASSWORD',
                'agent_code' => 'AGENT',
                'rest_auth_url' => 'https://example.test/auth',
                'rest_search_url' => 'https://example.test/search',
                'soap_url' => 'https://example.test/soap',
            ],
        ]);
    }
}
