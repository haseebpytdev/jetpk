<?php

namespace Tests\Feature;

use App\Http\Requests\Frontend\StoreBookingPassengersRequest;
use Database\Seeders\AirportAirlineReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StoreBookingPassengersTravelDocumentValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $payload
     */
    private function formRequestFromPayload(array $payload): StoreBookingPassengersRequest
    {
        $base = Request::create('/booking/passengers', 'POST', $payload);
        $req = StoreBookingPassengersRequest::createFrom($base);
        $req->setContainer($this->app);
        $req->setRedirector($this->app['redirect']);

        return $req;
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(string $from, string $to): array
    {
        return [
            'from' => $from,
            'to' => $to,
            'depart' => now()->addWeek()->format('Y-m-d'),
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'lead_passenger_index' => 0,
            'email' => 'travel-docs@example.com',
            'phone' => '+923001112233',
        ];
    }

    public function test_international_requires_passport_number_expiry_and_issue_date(): void
    {
        $this->seed(AirportAirlineReferenceSeeder::class);
        $payload = $this->basePayload('LHE', 'DXB');
        $payload['passengers'] = [[
            'passenger_type' => 'adult',
            'title' => 'Mr',
            'first_name' => 'A',
            'last_name' => 'B',
            'date_of_birth' => '1990-01-01',
            'gender' => 'M',
            'nationality' => 'PK',
            'document_type' => 'passport',
            'passport_number' => '',
            'passport_issuing_country' => 'PK',
            'passport_expiry_date' => now()->addYears(3)->format('Y-m-d'),
            'passport_issue_date' => '2018-01-15',
        ]];

        try {
            $this->formRequestFromPayload($payload)->validateResolved();
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('passengers.0.passport_number', $e->errors());
        }
    }

    public function test_international_passes_without_residency_details(): void
    {
        $this->seed(AirportAirlineReferenceSeeder::class);
        $payload = $this->basePayload('LHE', 'DXB');
        $payload['passengers'] = [[
            'passenger_type' => 'adult',
            'title' => 'Mr',
            'first_name' => 'A',
            'last_name' => 'B',
            'date_of_birth' => '1990-01-01',
            'gender' => 'M',
            'nationality' => 'PK',
            'document_type' => 'passport',
            'passport_number' => 'AB9988776',
            'passport_issuing_country' => 'PK',
            'passport_expiry_date' => now()->addYears(5)->format('Y-m-d'),
            'passport_issue_date' => '2018-01-15',
            'country_of_residence' => '',
            'place_of_birth' => '',
        ]];

        $this->formRequestFromPayload($payload)->validateResolved();
        $this->assertTrue(true);
    }

    public function test_international_requires_passport_issue_date_and_other_passport_fields(): void
    {
        $this->seed(AirportAirlineReferenceSeeder::class);
        $payload = $this->basePayload('LHE', 'DXB');
        $payload['passengers'] = [[
            'passenger_type' => 'adult',
            'title' => 'Mr',
            'first_name' => 'A',
            'last_name' => 'B',
            'date_of_birth' => '1990-01-01',
            'gender' => 'M',
            'nationality' => 'PK',
            'document_type' => 'passport',
            'passport_number' => 'X1',
            'passport_issuing_country' => 'PK',
            'passport_expiry_date' => now()->addYears(3)->format('Y-m-d'),
            'passport_issue_date' => '',
        ]];

        try {
            $this->formRequestFromPayload($payload)->validateResolved();
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('passengers.0.passport_issue_date', $e->errors());
        }
    }

    public function test_pk_domestic_national_id_accepts_without_nationality_or_passport(): void
    {
        $this->seed(AirportAirlineReferenceSeeder::class);
        $payload = $this->basePayload('LHE', 'KHI');
        $payload['passengers'] = [[
            'passenger_type' => 'adult',
            'title' => 'Mr',
            'first_name' => 'A',
            'last_name' => 'B',
            'date_of_birth' => '1990-01-01',
            'gender' => 'M',
            'nationality' => '',
            'document_type' => 'national_id',
            'national_id_number' => '35202-1234567-1',
            'passport_number' => '',
            'passport_issuing_country' => '',
            'passport_expiry_date' => '',
            'passport_issue_date' => '',
        ]];

        $this->formRequestFromPayload($payload)->validateResolved();
        $this->assertTrue(true);
    }

    public function test_pk_domestic_passport_requires_nationality(): void
    {
        $this->seed(AirportAirlineReferenceSeeder::class);
        $payload = $this->basePayload('LHE', 'KHI');
        $payload['passengers'] = [[
            'passenger_type' => 'adult',
            'title' => 'Mr',
            'first_name' => 'A',
            'last_name' => 'B',
            'date_of_birth' => '1990-01-01',
            'gender' => 'M',
            'nationality' => '',
            'document_type' => 'passport',
            'passport_number' => 'AB9988776',
            'passport_issuing_country' => 'PK',
            'passport_expiry_date' => now()->addYears(5)->format('Y-m-d'),
            'passport_issue_date' => '2018-01-15',
        ]];

        try {
            $this->formRequestFromPayload($payload)->validateResolved();
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('passengers.0.nationality', $e->errors());
        }
    }

    public function test_non_pk_domestic_forces_passport_and_requires_passport_fields(): void
    {
        $this->seed(AirportAirlineReferenceSeeder::class);
        $payload = $this->basePayload('ORD', 'JFK');
        $payload['passengers'] = [[
            'passenger_type' => 'adult',
            'title' => 'Mr',
            'first_name' => 'A',
            'last_name' => 'B',
            'date_of_birth' => '1990-01-01',
            'gender' => 'M',
            'nationality' => 'US',
            'document_type' => 'national_id',
            'national_id_number' => '123',
            'passport_number' => '',
            'passport_issuing_country' => '',
            'passport_expiry_date' => '',
            'passport_issue_date' => '',
        ]];

        try {
            $this->formRequestFromPayload($payload)->validateResolved();
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('passengers.0.passport_number', $e->errors());
        }
    }
}
