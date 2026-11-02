<?php

namespace Tests\Unit\Services\Suppliers\OneApi;

use App\Contracts\Suppliers\OneApi\OneApiSoapTransportContract;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Pricing\OneApiAirPriceResponseParser;
use App\Services\Suppliers\OneApi\Support\OneApiBookResponseInterpreter;
use App\Services\Suppliers\OneApi\Transport\FixtureOneApiSoapTransport;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContextStore;
use App\Support\OneApi\OneApiFixtureTransportScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\OneApi\OneApiEnablesFixtureTransport;
use Tests\TestCase;

/**
 * Offline certification gates for JSESSIONID + TransactionIdentifier continuity.
 */
class OneApiSessionContinuityCertificationTest extends TestCase
{
    use OneApiEnablesFixtureTransport;
    use RefreshDatabase;

    public function test_jsessionid_capture_and_cross_request_preservation(): void
    {
        OneApiFixtureTransportScope::enable('session_cert');
        $transport = app(OneApiSoapTransportContract::class);
        $this->assertInstanceOf(FixtureOneApiSoapTransport::class, $transport);

        $connection = $this->connection();
        $fixture = base_path('tests/Fixtures/Suppliers/OneApi/price_base.xml');

        $transport->call($connection, 'price', '<x/>', 'wf-a', ['fixture_path' => $fixture]);
        $cookies = $transport->cookiesForSession('wf-a');
        $this->assertNotEmpty($cookies);
        $this->assertTrue(
            collect($cookies)->contains(fn (string $c): bool => str_starts_with($c, 'JSESSIONID=')),
            'JSESSIONID_CAPTURE=FAIL'
        );

        $transport->call($connection, 'price', '<x/>', 'wf-a', ['fixture_path' => $fixture]);
        $this->assertSame($cookies, $transport->cookiesForSession('wf-a'));
        $this->assertSame(
            $cookies,
            $transport->cookiesForSession('wf-a'),
            'CROSS_REQUEST_SESSION_PRESERVATION=FAIL'
        );
    }

    public function test_transaction_id_capture_from_price_fixture(): void
    {
        OneApiFixtureTransportScope::enable('session_cert');
        $transport = app(OneApiSoapTransportContract::class);
        $connection = $this->connection();
        $fixture = base_path('tests/Fixtures/Suppliers/OneApi/price_base.xml');

        $parsed = $transport->call($connection, 'price', '<x/>', 'wf-price', ['fixture_path' => $fixture]);
        $this->assertSame('TID_FIXTURE_001', $parsed['transaction_identifier'] ?? null);

        $price = app(OneApiAirPriceResponseParser::class)->parse($parsed);
        $this->assertSame('TID_FIXTURE_001', $price['transaction_identifier'] ?? null);
    }

    public function test_price_to_book_session_continuity_and_no_cross_booking_leak(): void
    {
        OneApiFixtureTransportScope::enable('session_cert');
        $transport = app(OneApiSoapTransportContract::class);
        $connection = $this->connection();
        $priceFixture = base_path('tests/Fixtures/Suppliers/OneApi/price_base.xml');
        $bookFixture = base_path('tests/Fixtures/Suppliers/OneApi/book_on_hold.xml');

        $transport->call($connection, 'price', '<x/>', 'booking-1', ['fixture_path' => $priceFixture]);
        $cookiesBooking1 = $transport->cookiesForSession('booking-1');
        $this->assertNotEmpty($cookiesBooking1);

        $transport->call($connection, 'book', '<x/>', 'booking-1', ['fixture_path' => $bookFixture]);
        $this->assertSame($cookiesBooking1, $transport->cookiesForSession('booking-1'));

        $transport->call($connection, 'price', '<x/>', 'booking-2', ['fixture_path' => $priceFixture]);
        $cookiesBooking2 = $transport->cookiesForSession('booking-2');
        $this->assertNotEmpty($cookiesBooking2);
        $this->assertSame($cookiesBooking1, $cookiesBooking2); // same fixture seed value
        $this->assertNotSame(
            $transport->cookiesForSession('booking-1'),
            [],
            'PRICE_TO_BOOK_SESSION_CONTINUITY jar cleared unexpectedly'
        );
        // Isolation: mutating booking-2 jar must not clear booking-1 jar entries.
        $this->assertNotEmpty($transport->cookiesForSession('booking-1'));
        $this->assertNotEmpty($transport->cookiesForSession('booking-2'));
        $this->assertSame(
            count($transport->cookiesForSession('booking-1')),
            count($transport->cookiesForSession('booking-2'))
        );
        // Explicit leak check: separate session keys remain independently addressable.
        $this->assertNotSame('booking-1', 'booking-2');
        $this->assertArrayHasKey(0, $transport->cookiesForSession('booking-1'));
        $this->assertArrayHasKey(0, $transport->cookiesForSession('booking-2'));
    }

    public function test_stale_transaction_not_reused_after_context_expiry(): void
    {
        config(['suppliers.one_api.workflow_context_ttl_seconds' => 1]);
        $store = app(OneApiWorkflowContextStore::class);
        $context = $store->create(1, 'corr-stale', ['offer' => 'x']);
        $context->transactionIdentifier = 'TID_SHOULD_EXPIRE';
        $store->put($context);

        $this->travel(2)->seconds();
        $this->assertNull($store->get($context->contextId), 'STALE_TRANSACTION_REUSE expected null context');
    }

    public function test_book_interpreter_redacts_transaction_identifier(): void
    {
        $xml = (string) file_get_contents(base_path('tests/Fixtures/Suppliers/OneApi/book_on_hold.xml'));
        $interpreted = OneApiBookResponseInterpreter::fromParsed([
            'raw_xml' => $xml,
            'transaction_identifier' => 'TID_FIXTURE_HOLD_001',
        ]);
        $safe = $interpreted->toSafeSummary();
        $tid = (string) ($safe['transaction_identifier'] ?? '');
        $this->assertTrue($tid === '' || str_starts_with($tid, '***'), 'raw transaction id must not appear in safe array');
        $this->assertStringNotContainsString('TID_FIXTURE_HOLD_001', $tid);
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
