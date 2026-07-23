<?php

namespace App\Services\Suppliers\OneApi\Transport;

use App\Services\Suppliers\OneApi\Exceptions\OneApiValidationException;

/**
 * Allowlisted fixture filenames for One API (no arbitrary paths).
 */
final class OneApiFixtureCaseCatalog
{
    /** @var array<string, string> */
    private const MAP = [
        'price_base' => 'price_base.xml',
        'book_paid' => 'book_paid.xml',
        'book_on_hold' => 'book_on_hold.xml',
        'read_on_hold' => 'read_on_hold.xml',
        'hold_payment_modify' => 'hold_payment_modify.xml',
        'ancillary_baggage' => 'ancillary_baggage.xml',
        'ancillary_meals' => 'ancillary_meals.xml',
        'ancillary_seats' => 'ancillary_seats.xml',
        'auth_success' => 'auth_success.json',
        'search_oneway' => 'search_oneway.json',
    ];

    public static function resolvePath(string $key): string
    {
        $key = trim($key);
        if ($key === '' || ! isset(self::MAP[$key])) {
            throw new OneApiValidationException('fixture_forbidden', 422, 'Unknown fixture key.');
        }

        return base_path('tests/Fixtures/Suppliers/OneApi/'.self::MAP[$key]);
    }
}
