<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Models\Booking;
use App\Services\Suppliers\AirBlue\AirBlueBookingService;
use ReflectionClass;
use Tests\TestCase;

class AirBlueBookingProviderContextResolutionTest extends TestCase
{
    private function resolveProviderContext(Booking $booking, array $meta): array
    {
        $service = (new ReflectionClass(AirBlueBookingService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(AirBlueBookingService::class, 'resolveProviderContext');
        $method->setAccessible(true);

        /** @var array<string, mixed> $resolved */
        $resolved = $method->invoke($service, $booking, $meta);

        return $resolved;
    }

    public function test_validated_meta_snapshot_takes_precedence_over_stale_booking_offer_snapshot(): void
    {
        $booking = new Booking();
        $booking->offer_snapshot = [
            'raw_payload' => [
                'provider_context' => [
                    'protocol_version' => '2.0',
                    'stale_booking_snapshot' => true,
                ],
            ],
        ];

        $resolved = $this->resolveProviderContext($booking, [
            'validated_offer_snapshot' => [
                'raw_payload' => [
                    'provider_context' => [
                        'protocol_version' => '3.0',
                        'fare_type_code' => 'EF',
                        'priced_itineraries' => [['id' => 'validated']],
                    ],
                ],
            ],
        ]);

        $this->assertSame('3.0', $resolved['protocol_version'] ?? null);
        $this->assertSame('EF', $resolved['fare_type_code'] ?? null);
        $this->assertSame([['id' => 'validated']], $resolved['priced_itineraries'] ?? null);
        $this->assertArrayNotHasKey('stale_booking_snapshot', $resolved);
    }

    public function test_normalized_meta_snapshot_is_merged_with_airblue_lifecycle_context(): void
    {
        $booking = new Booking();
        $booking->offer_snapshot = [
            'raw_payload' => [
                'provider_context' => [
                    'protocol_version' => '2.0',
                    'fare_type_code' => 'EV',
                ],
            ],
        ];

        $resolved = $this->resolveProviderContext($booking, [
            'normalized_offer_snapshot' => [
                'raw_payload' => [
                    'provider_context' => [
                        'protocol_version' => '3.0',
                        'fare_type_code' => 'EF',
                        'rbd' => 'U',
                        'priced_itineraries' => [['id' => 'normalized']],
                    ],
                ],
            ],
            'airblue_context' => [
                'pnr' => 'TESTPNR',
                'instance' => 'TESTINSTANCE',
                'fare_type_code' => 'EF',
            ],
        ]);

        $this->assertSame('3.0', $resolved['protocol_version'] ?? null);
        $this->assertSame('EF', $resolved['fare_type_code'] ?? null);
        $this->assertSame('U', $resolved['rbd'] ?? null);
        $this->assertSame([['id' => 'normalized']], $resolved['priced_itineraries'] ?? null);
        $this->assertSame('TESTPNR', $resolved['pnr'] ?? null);
        $this->assertSame('TESTINSTANCE', $resolved['instance'] ?? null);
        $this->assertArrayNotHasKey('stale_booking_snapshot', $resolved);
    }
}
