<?php

namespace Tests\Feature\Admin;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Search\OneApiFlightSearchService;
use App\Services\Suppliers\OneApi\Support\OneApiReadinessService;
use App\Support\Suppliers\OneApiAccountContext;
use App\Support\Suppliers\OneApiSupplierConnectionNormalizer;
use App\Support\Suppliers\SupplierCredentialFormPresenter;
use App\Data\FlightSearchRequestData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class OneApiCredentialPortalMinimizationTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_normal_form_field_count_minimized(): void
    {
        $visible = SupplierCredentialFormPresenter::visibleFieldKeys('one_api');
        $this->assertCount(5, $visible);
        $this->assertSame([
            'rest_username',
            'rest_password',
            'soap_username',
            'soap_password',
            'agent_code',
        ], $visible);
    }

    public function test_normal_form_shows_required_credential_fields_in_config(): void
    {
        $fields = (array) config('supplier_credentials.providers.one_api.fields', []);
        foreach (['rest_username', 'rest_password', 'soap_username', 'soap_password', 'agent_code'] as $key) {
            $this->assertArrayHasKey($key, $fields);
            $this->assertTrue(SupplierCredentialFormPresenter::isFormVisible($fields[$key]));
        }
    }

    public function test_normal_form_hides_legacy_username_password(): void
    {
        $fields = (array) config('supplier_credentials.providers.one_api.fields', []);
        $this->assertFalse(SupplierCredentialFormPresenter::isFormVisible($fields['username']));
        $this->assertFalse(SupplierCredentialFormPresenter::isFormVisible($fields['password']));
    }

    public function test_normal_form_hides_endpoint_fields(): void
    {
        $fields = (array) config('supplier_credentials.providers.one_api.fields', []);
        foreach (['rest_auth_url', 'rest_search_url', 'soap_url'] as $key) {
            $this->assertFalse(SupplierCredentialFormPresenter::isFormVisible($fields[$key]));
        }
    }

    public function test_normal_form_hides_runtime_options(): void
    {
        $fields = (array) config('supplier_credentials.providers.one_api.fields', []);
        foreach ([
            'sales_channel',
            'terminal_id',
            'soap_action_price',
            'connect_timeout_seconds',
            'verify_tls',
            'carrier_allowlist',
            'live_search_enabled',
            'max_search_results',
            'log_payloads',
            'health_check_route',
            'notes',
        ] as $key) {
            $this->assertArrayHasKey($key, $fields);
            $this->assertFalse(SupplierCredentialFormPresenter::isFormVisible($fields[$key]));
        }
    }

    public function test_account_context_separated_from_credentials_config(): void
    {
        $fields = (array) config('supplier_credentials.providers.one_api.fields', []);
        foreach (['agent_preferred_currency', 'pos_country', 'pos_station'] as $key) {
            $this->assertArrayHasKey($key, $fields);
            $this->assertFalse(SupplierCredentialFormPresenter::isFormVisible($fields[$key]));
        }
    }

    public function test_unconfirmed_pos_context_fails_search_readiness(): void
    {
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'credentials' => $this->oneApiTestCredentials(),
            'settings' => [
                'account_context' => [
                    'agent_preferred_currency' => 'AED',
                    'pos_country' => 'AE',
                    'pos_station' => 'DXB',
                ],
                'account_context_status' => OneApiAccountContext::STATUS_UNRESOLVED,
            ],
        ]);

        $readiness = app(OneApiReadinessService::class);
        $this->assertTrue($readiness->credentialReadiness($connection)['ready']);
        $this->assertFalse($readiness->searchReadiness($connection)['ready']);
        $this->assertSame(
            OneApiAccountContext::SEARCH_BLOCKED_MESSAGE,
            $readiness->searchReadiness($connection)['detail']
        );

        $result = app(OneApiFlightSearchService::class)->search(
            new FlightSearchRequestData(
                origin: 'SHJ',
                destination: 'KHI',
                departure_date: now()->addMonths(2)->toDateString(),
                return_date: null,
                adults: 1,
            ),
            $connection,
        );
        $this->assertSame('account_context_unconfirmed', $result->meta['error_code'] ?? null);
    }

    public function test_legacy_connection_compatibility(): void
    {
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'credentials' => [
                'username' => 'LEGACY_ONLY_USER',
                'password' => 'LEGACY_ONLY_PASS',
                'agent_code' => 'AGENT',
                'rest_auth_url' => 'https://example.test/auth',
                'rest_search_url' => 'https://example.test/search',
            ],
            'settings' => $this->oneApiConfirmedAccountSettings(),
        ]);

        $config = app(\App\Services\Suppliers\OneApi\Support\OneApiConfigResolver::class)->resolve($connection);
        $this->assertSame('LEGACY_ONLY_USER', $config['rest_username']);
        $this->assertSame('LEGACY_ONLY_PASS', $config['soap_password']);
        $this->assertTrue(app(OneApiReadinessService::class)->credentialReadiness($connection)['ready']);
    }

    public function test_password_edit_preservation_via_normalizer(): void
    {
        $existing = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'credentials' => $this->oneApiTestCredentials([
                'rest_password' => 'REST_SECRET',
                'soap_password' => 'SOAP_SECRET',
                'password' => 'LEGACY_SECRET',
            ]),
        ]);

        $payload = OneApiSupplierConnectionNormalizer::normalizePayload([
            'provider' => 'one_api',
            'credentials' => [
                'rest_username' => 'ONE_API_TEST_USERNAME',
                'rest_password' => '',
                'soap_username' => 'ONE_API_TEST_USERNAME',
                'soap_password' => '',
                'agent_code' => 'ONE_API_TEST_AGENT',
                'password' => '',
            ],
        ], $existing);

        $this->assertSame('REST_SECRET', $payload['credentials']['rest_password']);
        $this->assertSame('SOAP_SECRET', $payload['credentials']['soap_password']);
        $this->assertSame('LEGACY_SECRET', $payload['credentials']['password']);
    }

    public function test_encrypted_storage_at_rest(): void
    {
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'credentials' => $this->oneApiTestCredentials([
                'rest_password' => 'REST_SECRET',
            ]),
        ]);

        $raw = $connection->getAttributes()['credentials'] ?? '';
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('REST_SECRET', $raw);
    }

    public function test_create_requires_explicit_rest_and_soap_credentials(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::factory()->create();
        $admin->forceFill(['current_agency_id' => $agency->id])->save();

        $this->actingAs($admin)->post(route('admin.api-settings.store'), [
            'provider' => 'one_api',
            'name' => 'One API Minimized Create',
            'environment' => 'sandbox',
            'status' => 'active',
            'credentials' => [
                'username' => 'LEGACY_ONLY',
                'password' => 'LEGACY_PASS',
                'agent_code' => 'AGENT',
            ],
        ])->assertSessionHasErrors([
            'credentials.rest_username',
            'credentials.rest_password',
            'credentials.soap_username',
            'credentials.soap_password',
        ]);
    }
}
