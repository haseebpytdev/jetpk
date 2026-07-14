<?php

namespace Tests\Support;

use App\Http\Requests\Frontend\StoreBookingPassengersRequest;

/**
 * Required passenger/contact fields for POST /booking/passengers in feature tests.
 *
 * @see StoreBookingPassengersRequest
 */
class PublicBookingPassengersPayload
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function merge(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Mr',
            'first_name' => 'Test',
            'last_name' => 'User',
            'dob' => '1990-05-10',
            'nationality' => 'PK',
            'gender' => 'M',
            'email' => 'guest.tester@example.com',
            'phone' => '+923001112233',
            'country' => 'Pakistan',
            'document_type' => 'passport',
            'create_account' => '0',
        ], $overrides);
    }

    /**
     * Add with LHE → DXB (or any international pair resolved in airports table).
     *
     * @return array<string, mixed>
     */
    public static function internationalDocuments(): array
    {
        return [
            'passport_number' => 'AB9988776',
            'passport_issuing_country' => 'PK',
            'passport_expiry_date' => now()->addYears(7)->format('Y-m-d'),
            'passport_issue_date' => '2018-01-15',
        ];
    }
}
