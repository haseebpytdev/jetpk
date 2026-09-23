<?php

namespace Tests\Unit\Suppliers\AmeerEMillat;

use App\Services\Suppliers\AmeerEMillat\AmeerEMillatClient;
use App\Services\Suppliers\AmeerEMillat\AmeerEMillatProviderException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmeerEMillatClientContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Config::set('suppliers.ameer_e_millat.enabled', true);
        Config::set('suppliers.ameer_e_millat.token', 'contract-test-token');
        Config::set('suppliers.ameer_e_millat.default_base_url', 'https://ameer.test');
        Config::set('suppliers.ameer_e_millat.sectors_path', '/api/available/sectors');
        Config::set('suppliers.ameer_e_millat.airlines_path', '/api/available/airlines');
        Config::set('suppliers.ameer_e_millat.groups_path', '/api/available/groups');
        Config::set('suppliers.ameer_e_millat.group_detail_path', '/api/group/detail/{id}');
        Config::set('suppliers.ameer_e_millat.seats_path', '/api/available/seats/{id}');
        Config::set('suppliers.ameer_e_millat.legacy_seats_path', '/api/check/available_seats/{id}');
        Config::set('suppliers.ameer_e_millat.create_booking_path', '/api/create/booking');
        Config::set('suppliers.ameer_e_millat.show_booking_path', '/api/show/booking/{id}');
        Config::set('suppliers.ameer_e_millat.booking_enabled', true);
    }

    public function test_list_sectors_airlines_and_groups(): void
    {
        Http::fake([
            'ameer.test/api/available/sectors' => Http::response(['sectors' => ['SKT-SHJ']], 200),
            'ameer.test/api/available/airlines' => Http::response(['airlines' => [['id' => 1, 'airline_name' => 'Air Arabia']]], 200),
            'ameer.test/api/available/groups*' => Http::response(['groups' => [['id' => 42, 'sector' => 'SKT-SHJ']]], 200),
        ]);

        $client = app(AmeerEMillatClient::class);

        $this->assertSame(['SKT-SHJ'], $client->listSectors()['sectors']);
        $this->assertCount(1, $client->listAirlines()['airlines']);
        $this->assertSame(42, $client->listGroups(['sector' => 'SKT-SHJ'])['groups'][0]['id']);
    }

    public function test_group_detail_and_primary_seats(): void
    {
        Http::fake([
            'ameer.test/api/group/detail/42' => Http::response(['group' => ['id' => 42, 'sector' => 'SKT-SHJ']], 200),
            'ameer.test/api/available/seats/42' => Http::response(['seats' => 7], 200),
        ]);

        $client = app(AmeerEMillatClient::class);

        $this->assertSame(42, $client->getGroupDetail('42')['group']['id']);
        $this->assertSame(7, $client->getAvailableSeats('42')['seats']);
    }

    public function test_seats_falls_back_to_legacy_path_on_404(): void
    {
        Http::fake([
            'ameer.test/api/available/seats/42' => Http::response(['message' => 'Not found'], 404),
            'ameer.test/api/check/available_seats/42' => Http::response(['seats' => 3], 200),
        ]);

        $this->assertSame(3, app(AmeerEMillatClient::class)->getAvailableSeats('42')['seats']);
    }

    public function test_create_booking_posts_expected_json_shape(): void
    {
        Http::fake([
            'ameer.test/api/create/booking' => function ($request) {
                $payload = $request->data();
                $this->assertSame(99, $payload['group_id']);
                $this->assertArrayHasKey('booking_details', $payload);
                $this->assertArrayNotHasKey('passengers', $payload);
                $this->assertSame('MR', $payload['booking_details'][0]['title']);
                $this->assertSame('Ali', $payload['booking_details'][0]['given_name']);
                $this->assertSame('Khan', $payload['booking_details'][0]['surname']);
                $this->assertSame('AB1234567', $payload['booking_details'][0]['passport_no']);
                $this->assertSame('1990-01-15', $payload['booking_details'][0]['dob']);
                $this->assertSame('2030-01-01', $payload['booking_details'][0]['doe']);
                $this->assertSame(99, $payload['agency_info']['group_id']);
                $this->assertSame('JetPakistan', $payload['agency_info']['agency_name']);
                $this->assertSame('Ops Agent', $payload['agency_info']['agent_name']);
                $this->assertSame('ops@example.test', $payload['agency_info']['email']);
                $this->assertSame('03001232412', $payload['agency_info']['mobile']);
                $this->assertSame(1, $payload['agency_info']['adults']);
                $this->assertSame(0, $payload['agency_info']['child']);
                $this->assertSame(0, $payload['agency_info']['infant']);
                $this->assertArrayHasKey('agent_notes', $payload['agency_info']);

                return Http::response([
                    'error' => false,
                    'success' => true,
                    'message' => 'Booking created',
                    'data' => ['id' => 1001],
                ], 200);
            },
        ]);

        $response = app(AmeerEMillatClient::class)->createBooking([
            'group_id' => 99,
            'agency_info' => [
                'group_id' => 99,
                'agent_name' => 'Ops Agent',
                'agency_name' => 'JetPakistan',
                'email' => 'ops@example.test',
                'mobile' => '03001232412',
                'adults' => 1,
                'child' => 0,
                'infant' => 0,
                'agent_notes' => null,
            ],
            'booking_details' => [[
                'surname' => 'Khan',
                'given_name' => 'Ali',
                'title' => 'MR',
                'passport_no' => 'AB1234567',
                'dob' => '1990-01-15',
                'doe' => '2030-01-01',
            ]],
        ]);

        // parseResponse unwraps documented data envelope → data.id becomes id
        $this->assertSame(1001, $response['id']);
        $this->assertArrayNotHasKey('passengers', $response);
    }

    public function test_show_booking_unwraps_data_payload(): void
    {
        Http::fake([
            'ameer.test/api/show/booking/1001' => Http::response([
                'error' => false,
                'success' => true,
                'data' => ['id' => 1001, 'status' => 'confirmed'],
            ], 200),
        ]);

        $response = app(AmeerEMillatClient::class)->showBooking('1001');

        $this->assertSame(1001, $response['id']);
        $this->assertSame('confirmed', $response['status']);
    }

    public function test_show_booking_vendor_error_true_on_http_200_throws(): void
    {
        Http::fake([
            'ameer.test/api/show/booking/BK-ERR' => Http::response([
                'error' => true,
                'message' => 'Booking not found',
            ], 200),
        ]);

        $this->expectException(AmeerEMillatProviderException::class);
        $this->expectExceptionMessage('Booking not found');

        app(AmeerEMillatClient::class)->showBooking('BK-ERR');
    }

    public function test_http_errors_map_to_provider_exception(): void
    {
        Http::fake([
            'ameer.test/api/available/groups*' => Http::response(['message' => 'Server error'], 500),
        ]);

        $this->expectException(AmeerEMillatProviderException::class);

        app(AmeerEMillatClient::class)->listGroups();
    }
}
