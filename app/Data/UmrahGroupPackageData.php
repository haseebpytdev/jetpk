<?php

namespace App\Data;

/**
 * Normalized Al-Haider Umrah group package for public display (no raw supplier payload).
 */
class UmrahGroupPackageData
{
    /**
     * @param  list<array<string, mixed>>  $legs
     * @param  list<string>  $included_services
     */
    public function __construct(
        public string $supplier,
        public string $supplier_package_id,
        public string $public_id,
        public string $title,
        public ?string $departure_city,
        public ?string $destination,
        public ?string $sector,
        public ?string $departure_date,
        public ?string $return_date,
        public ?int $duration_days,
        public ?string $airline,
        public ?string $airline_logo_url,
        public ?string $package_type,
        public float $price,
        public ?float $price_child,
        public ?float $price_infant,
        public string $currency,
        public string $availability_status,
        public int $seats_available,
        public ?string $baggage,
        public ?string $meal,
        public array $legs = [],
        public ?string $makkah_hotel = null,
        public ?string $madinah_hotel = null,
        public array $included_services = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'supplier' => $this->supplier,
            'supplier_package_id' => $this->supplier_package_id,
            'public_id' => $this->public_id,
            'title' => $this->title,
            'departure_city' => $this->departure_city,
            'destination' => $this->destination,
            'sector' => $this->sector,
            'departure_date' => $this->departure_date,
            'return_date' => $this->return_date,
            'duration_days' => $this->duration_days,
            'airline' => $this->airline,
            'airline_logo_url' => $this->airline_logo_url,
            'package_type' => $this->package_type,
            'price' => $this->price,
            'price_child' => $this->price_child,
            'price_infant' => $this->price_infant,
            'currency' => $this->currency,
            'availability_status' => $this->availability_status,
            'seats_available' => $this->seats_available,
            'baggage' => $this->baggage,
            'meal' => $this->meal,
            'legs' => $this->legs,
            'makkah_hotel' => $this->makkah_hotel,
            'madinah_hotel' => $this->madinah_hotel,
            'included_services' => $this->included_services,
        ];
    }
}
