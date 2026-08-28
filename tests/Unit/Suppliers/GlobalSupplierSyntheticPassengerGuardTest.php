<?php

namespace Tests\Unit\Suppliers;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Architecture-level guard: production supplier booking payload builders must not
 * invent Guest/Passenger/dummy identity values for live mutations.
 */
class GlobalSupplierSyntheticPassengerGuardTest extends TestCase
{
    /** @var list<string> */
    private const PRODUCTION_BUILDER_PATHS = [
        'app/Services/Suppliers/AlHaider/AlHaiderGroupBookingPayloadBuilder.php',
        'app/Services/Suppliers/Iati/IatiPayloadBuilder.php',
        'app/Services/Suppliers/Iati/IatiPassengerNormalizer.php',
        'app/Services/Suppliers/Sabre/Booking/SabreBookingPayloadBuilder.php',
        'app/Services/Suppliers/PiaNdc/PiaNdcXmlBuilder.php',
        'app/Services/Suppliers/AirBlue/AirBlueXmlBuilder.php',
        'app/Services/Suppliers/Duffel/DuffelOrderRequestBuilder.php',
        'app/Services/Suppliers/OneApi/Booking/OneApiBookingService.php',
    ];

    /** @var list<string> */
    private const FORBIDDEN_PATTERNS = [
        '/[\'"]Guest[\'"]/',
        '/first_name[\'"\\s]*=>\\s*[\'"]Passenger[\'"]/',
        '/[\'"]dummy@/',
        '/[\'"]qa@example\\.com[\'"]/',
        '/[\'"]test@example\\.com[\'"]/',
        '/generated.?passport/i',
        '/fake.?passport/i',
        '/nationality\\s*\\?\\?\\s*[\'"]PK[\'"]/',
        '/\\?:\\s*[\'"]PK[\'"]/',
        '/gender\\s*\\?\\?\\s*[\'"]M[\'"]/',
        '/\\$input\\[\'gender\'\\]\\s*\\?\\?\\s*[\'"]M[\'"]/',
        '/default\\s*=>\\s*[\'"]MR[\'"]/',
    ];

    #[Test]
    public function test_production_builders_have_no_obvious_synthetic_passenger_fallbacks(): void
    {
        $violations = [];

        foreach (self::PRODUCTION_BUILDER_PATHS as $relative) {
            $absolute = base_path($relative);
            $this->assertFileExists($absolute, "Missing builder: {$relative}");
            $contents = (string) file_get_contents($absolute);

            foreach (self::FORBIDDEN_PATTERNS as $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $violations[] = "{$relative} matches {$pattern}";
                }
            }
        }

        $this->assertSame([], $violations, "Synthetic passenger fallbacks found:\n".implode("\n", $violations));
    }
}
