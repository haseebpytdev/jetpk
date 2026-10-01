<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\AirBlueConnectionSearchPolicy;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;
use ReflectionClass;
use Tests\TestCase;

class AirBlueConnectionSearchPolicyCertificationTest extends TestCase
{
    public function test_healthy_v3_without_certification_metadata_is_not_public(): void
    {
        $connection = $this->makeConnection([
            'last_test_status' => 'air_shopping_success',
            'credentials' => $this->credentials(['protocol_version' => '3.0']),
        ]);

        $this->assertFalse(app(AirBlueConnectionSearchPolicy::class)->isEligibleForPublicSearch($connection));
        $this->assertAirBlueExcludedFromSearch($connection);
    }

    public function test_unhealthy_v3_without_certification_metadata_is_not_public(): void
    {
        $connection = $this->makeConnection([
            'last_test_status' => 'failed',
            'credentials' => $this->credentials(['protocol_version' => '3.0']),
        ]);

        $this->assertFalse(app(AirBlueConnectionSearchPolicy::class)->isEligibleForPublicSearch($connection));
        $this->assertAirBlueExcludedFromSearch($connection);
    }

    public function test_lone_uncertified_v3_is_excluded_from_search(): void
    {
        $connection = $this->makeConnection([
            'id' => 99,
            'credentials' => $this->credentials([
                'protocol_version' => '3.0',
                'certification_status' => 'pending',
            ]),
        ]);

        $result = app(AirBlueConnectionSearchPolicy::class)->dedupeForSearch(collect([$connection]));

        $this->assertCount(0, $result->filter(fn (SupplierConnection $c): bool => $c->provider === SupplierProvider::Airblue));
    }

    public function test_lone_certified_v3_is_included_in_search(): void
    {
        $connection = $this->makeConnection([
            'id' => 99,
            'credentials' => $this->credentials([
                'protocol_version' => '3.0',
                'certification_status' => 'certified',
            ]),
        ]);

        $result = app(AirBlueConnectionSearchPolicy::class)->dedupeForSearch(collect([$connection]));

        $this->assertCount(1, $result);
        $this->assertSame(99, (int) $result->first()->id);
    }

    public function test_v2_legacy_without_certification_metadata_remains_eligible(): void
    {
        $connection = $this->makeConnection(['id' => 1]);

        $this->assertTrue(app(AirBlueConnectionSearchPolicy::class)->isEligibleForPublicSearch($connection));

        $result = app(AirBlueConnectionSearchPolicy::class)->dedupeForSearch(collect([$connection]));
        $this->assertCount(1, $result);
    }

    public function test_v2_explicit_pending_is_excluded(): void
    {
        $connection = $this->makeConnection([
            'credentials' => $this->credentials(['certification_status' => 'pending']),
        ]);

        $this->assertFalse(app(AirBlueConnectionSearchPolicy::class)->isEligibleForPublicSearch($connection));
        $this->assertAirBlueExcludedFromSearch($connection);
    }

    public function test_v3_pending_and_v2_legacy_selects_v2(): void
    {
        $v2 = $this->makeConnection(['id' => 1]);
        $v3 = $this->makeConnection([
            'id' => 2,
            'credentials' => $this->credentials([
                'protocol_version' => '3.0',
                'certification_status' => 'pending',
            ]),
        ]);

        $result = app(AirBlueConnectionSearchPolicy::class)->dedupeForSearch(collect([$v2, $v3]));

        $this->assertCount(1, $result->filter(fn (SupplierConnection $c): bool => $c->provider === SupplierProvider::Airblue));
        $this->assertSame(1, (int) $result->firstWhere('provider', SupplierProvider::Airblue)->id);
    }

    public function test_v3_certified_and_v2_legacy_may_select_v3(): void
    {
        $v2 = $this->makeConnection(['id' => 1]);
        $v3 = $this->makeConnection([
            'id' => 2,
            'credentials' => $this->credentials([
                'protocol_version' => '3.0',
                'certification_status' => 'certified',
            ]),
        ]);

        $result = app(AirBlueConnectionSearchPolicy::class)->dedupeForSearch(collect([$v2, $v3]));

        $this->assertSame(2, (int) $result->firstWhere('provider', SupplierProvider::Airblue)->id);
    }

    public function test_search_priority_cannot_override_uncertified_v3(): void
    {
        $v2 = $this->makeConnection(['id' => 1]);
        $v3 = $this->makeConnection([
            'id' => 2,
            'credentials' => $this->credentials([
                'protocol_version' => '3.0',
                'certification_status' => 'pending',
                'search_priority' => 999,
            ]),
        ]);

        $result = app(AirBlueConnectionSearchPolicy::class)->dedupeForSearch(collect([$v2, $v3]));

        $this->assertSame(1, (int) $result->firstWhere('provider', SupplierProvider::Airblue)->id);
    }

    public function test_supplier_health_cannot_imply_certification_for_v3(): void
    {
        $connection = $this->makeConnection([
            'last_test_status' => 'success',
            'credentials' => $this->credentials(['protocol_version' => '3.0']),
        ]);

        $this->assertTrue($connection->supplierHealthHealthy());
        $this->assertFalse(app(AirBlueConnectionSearchPolicy::class)->isEligibleForPublicSearch($connection));
    }

    public function test_blank_v3_search_soap_action_fails_closed(): void
    {
        $this->assertMissingSoapAction('air_low_fare_search');
    }

    public function test_configured_v3_search_soap_action_resolves_exact_value(): void
    {
        $this->withV3SoapAction('air_low_fare_search', 'https://supplier.example/AirLowFareSearch');

        $action = $this->resolveSoapAction('air_low_fare_search', ['protocol_version' => '3.0']);

        $this->assertSame('https://supplier.example/AirLowFareSearch', $action);
    }

    public function test_blank_v3_air_book_soap_action_fails_closed(): void
    {
        $this->assertMissingSoapAction('air_book');
    }

    public function test_configured_v3_air_book_soap_action_resolves_exact_value(): void
    {
        $this->withV3SoapAction('air_book', 'https://supplier.example/AirBook');

        $action = $this->resolveSoapAction('air_book', ['protocol_version' => '3.0']);

        $this->assertSame('https://supplier.example/AirBook', $action);
    }

    public function test_v3_read_test_default_resolves_to_documented_host(): void
    {
        $action = $this->resolveSoapAction('read', ['is_test' => true, 'protocol_version' => '3.0']);

        $this->assertSame('https://ota.qa.zapways.com/Read', $action);
    }

    public function test_v3_read_live_default_resolves_to_documented_host(): void
    {
        $action = $this->resolveSoapAction('read', ['is_test' => false, 'protocol_version' => '3.0']);

        $this->assertSame('https://ota.zapways.com/Read', $action);
    }

    public function test_v3_read_env_override_works(): void
    {
        $versions = (array) config('suppliers.airblue.protocol_versions');
        $v3 = is_array($versions['3.0'] ?? null) ? $versions['3.0'] : [];
        $operations = is_array($v3['ota_operations'] ?? null) ? $v3['ota_operations'] : [];
        $operations['read'] = [
            'soap_action_test' => 'https://override.test/Read',
            'soap_action_live' => 'https://override.live/Read',
        ];
        $v3['ota_operations'] = $operations;
        $versions['3.0'] = $v3;
        config(['suppliers.airblue.protocol_versions' => $versions]);

        $test = $this->resolveSoapAction('read', ['is_test' => true, 'protocol_version' => '3.0']);
        $live = $this->resolveSoapAction('read', ['is_test' => false, 'protocol_version' => '3.0']);

        $this->assertSame('https://override.test/Read', $test);
        $this->assertSame('https://override.live/Read', $live);
    }

    public function test_v2_soap_actions_remain_unchanged(): void
    {
        $search = $this->resolveSoapAction('air_low_fare_search', ['protocol_version' => '2.0']);
        $book = $this->resolveSoapAction('air_book', ['protocol_version' => '2.0']);
        $readTest = $this->resolveSoapAction('read', ['is_test' => true, 'protocol_version' => '2.0']);
        $readLive = $this->resolveSoapAction('read', ['is_test' => false, 'protocol_version' => '2.0']);

        $this->assertSame('http://zapways.com/air/ota/2.0/AirLowFareSearch', $search);
        $this->assertSame('http://zapways.com/air/ota/2.0/AirBook', $book);
        $this->assertSame('https://otatest4.zapways.com/Read', $readTest);
        $this->assertSame('https://ota4.zapways.com/Read', $readLive);
    }

    private function assertAirBlueExcludedFromSearch(SupplierConnection $connection): void
    {
        $result = app(AirBlueConnectionSearchPolicy::class)->dedupeForSearch(collect([$connection]));
        $this->assertCount(0, $result->filter(fn (SupplierConnection $c): bool => $c->provider === SupplierProvider::Airblue));
    }

    private function assertMissingSoapAction(string $operation): void
    {
        try {
            $this->resolveSoapAction($operation, ['is_test' => true, 'protocol_version' => '3.0']);
            $this->fail('Expected missing_soap_action for '.$operation);
        } catch (AirBlueValidationException $exception) {
            $this->assertSame('missing_soap_action', $exception->normalizedCode);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolveSoapAction(string $operation, array $config = []): string
    {
        $client = app(AirBlueClient::class);
        $method = (new ReflectionClass($client))->getMethod('resolveOtaSoapAction');
        $method->setAccessible(true);

        return $method->invoke($client, $operation, array_merge(['is_test' => true], $config));
    }

    private function withV3SoapAction(string $operation, string $soapAction): void
    {
        $versions = (array) config('suppliers.airblue.protocol_versions');
        $v3 = is_array($versions['3.0'] ?? null) ? $versions['3.0'] : [];
        $operations = is_array($v3['ota_operations'] ?? null) ? $v3['ota_operations'] : [];
        $operations[$operation] = ['soap_action' => $soapAction];
        $v3['ota_operations'] = $operations;
        $versions['3.0'] = $v3;
        config(['suppliers.airblue.protocol_versions' => $versions]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeConnection(array $attributes = []): SupplierConnection
    {
        $connection = new SupplierConnection([
            'provider' => SupplierProvider::Airblue,
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => $this->credentials(),
            ...$attributes,
        ]);
        $connection->id = (int) ($attributes['id'] ?? 42);

        return $connection;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function credentials(array $overrides = []): array
    {
        return array_merge([
            'api_channel' => 'zapways_ota',
            'client_id' => 'client',
            'client_key' => 'key',
            'agent_type' => '5',
            'agent_id' => 'agent',
            'agent_password' => 'secret',
        ], $overrides);
    }
}
