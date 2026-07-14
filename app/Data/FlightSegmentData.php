<?php

namespace App\Data;

class FlightSegmentData
{
    public function __construct(
        public string $origin,
        public string $destination,
        public string $departure_at,
        public string $arrival_at,
        public ?string $flight_number = null,
        public ?string $airline_code = null,
        public ?string $airline_name = null,
        public int $duration_minutes = 0,
        public ?string $operating_airline_code = null,
        public ?string $operating_airline_name = null,
        /** Sabre resBookDesigCode / booking class when available from BFM fare components. */
        public ?string $booking_class = null,
        /** Fare basis from BFM segment or parent fare component. */
        public ?string $fare_basis_code = null,
        /** Raw Sabre cabin letter on the priced segment (e.g. Y, J). */
        public ?string $segment_cabin_code = null,
    ) {}

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'origin' => $this->origin,
            'destination' => $this->destination,
            'departure_at' => $this->departure_at,
            'arrival_at' => $this->arrival_at,
            'flight_number' => $this->flight_number,
            'airline_code' => $this->airline_code,
            'airline_name' => $this->airline_name,
            'duration_minutes' => $this->duration_minutes,
            'operating_airline_code' => $this->operating_airline_code,
            'operating_airline_name' => $this->operating_airline_name,
            'booking_class' => $this->booking_class,
            'fare_basis_code' => $this->fare_basis_code,
            'segment_cabin_code' => $this->segment_cabin_code,
        ];
    }
}
