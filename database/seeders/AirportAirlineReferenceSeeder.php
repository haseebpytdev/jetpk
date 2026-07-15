<?php

namespace Database\Seeders;

use App\Models\Airline;
use App\Models\Airport;
use Illuminate\Database\Seeder;

class AirportAirlineReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $airports = [
            ['iata' => 'LHE', 'icao' => 'OPLA', 'name' => 'Allama Iqbal International Airport', 'city' => 'Lahore', 'country' => 'Pakistan'],
            ['iata' => 'KHI', 'icao' => 'OPKC', 'name' => 'Jinnah International Airport', 'city' => 'Karachi', 'country' => 'Pakistan'],
            ['iata' => 'ISB', 'icao' => 'OPIS', 'name' => 'Islamabad International Airport', 'city' => 'Islamabad', 'country' => 'Pakistan'],
            ['iata' => 'PEW', 'icao' => 'OPPS', 'name' => 'Bacha Khan International Airport', 'city' => 'Peshawar', 'country' => 'Pakistan'],
            ['iata' => 'SKT', 'icao' => 'OPST', 'name' => 'Sialkot International Airport', 'city' => 'Sialkot', 'country' => 'Pakistan'],
            ['iata' => 'DXB', 'icao' => 'OMDB', 'name' => 'Dubai International Airport', 'city' => 'Dubai', 'country' => 'United Arab Emirates'],
            ['iata' => 'SHJ', 'icao' => 'OMSJ', 'name' => 'Sharjah International Airport', 'city' => 'Sharjah', 'country' => 'United Arab Emirates'],
            ['iata' => 'AUH', 'icao' => 'OMAA', 'name' => 'Zayed International Airport', 'city' => 'Abu Dhabi', 'country' => 'United Arab Emirates'],
            ['iata' => 'JED', 'icao' => 'OEJN', 'name' => 'King Abdulaziz International Airport', 'city' => 'Jeddah', 'country' => 'Saudi Arabia'],
            ['iata' => 'RUH', 'icao' => 'OERK', 'name' => 'King Khalid International Airport', 'city' => 'Riyadh', 'country' => 'Saudi Arabia'],
            ['iata' => 'MED', 'icao' => 'OEMA', 'name' => 'Prince Mohammad Bin Abdulaziz Airport', 'city' => 'Madinah', 'country' => 'Saudi Arabia'],
            ['iata' => 'DOH', 'icao' => 'OTHH', 'name' => 'Hamad International Airport', 'city' => 'Doha', 'country' => 'Qatar'],
            ['iata' => 'KWI', 'icao' => 'OKBK', 'name' => 'Kuwait International Airport', 'city' => 'Kuwait City', 'country' => 'Kuwait'],
            ['iata' => 'BAH', 'icao' => 'OBBI', 'name' => 'Bahrain International Airport', 'city' => 'Manama', 'country' => 'Bahrain'],
            ['iata' => 'IST', 'icao' => 'LTFM', 'name' => 'Istanbul Airport', 'city' => 'Istanbul', 'country' => 'Turkey'],
            ['iata' => 'LHR', 'icao' => 'EGLL', 'name' => 'Heathrow Airport', 'city' => 'London', 'country' => 'United Kingdom'],
            ['iata' => 'MAN', 'icao' => 'EGCC', 'name' => 'Manchester Airport', 'city' => 'Manchester', 'country' => 'United Kingdom'],
            ['iata' => 'BHX', 'icao' => 'EGBB', 'name' => 'Birmingham Airport', 'city' => 'Birmingham', 'country' => 'United Kingdom'],
            ['iata' => 'MEL', 'icao' => 'YMML', 'name' => 'Melbourne Airport', 'city' => 'Melbourne', 'country' => 'Australia'],
            ['iata' => 'SYD', 'icao' => 'YSSY', 'name' => 'Sydney Airport', 'city' => 'Sydney', 'country' => 'Australia'],
            ['iata' => 'YYZ', 'icao' => 'CYYZ', 'name' => 'Toronto Pearson International Airport', 'city' => 'Toronto', 'country' => 'Canada'],
            ['iata' => 'YUL', 'icao' => 'CYUL', 'name' => 'Montreal-Trudeau International Airport', 'city' => 'Montreal', 'country' => 'Canada'],
            ['iata' => 'JFK', 'icao' => 'KJFK', 'name' => 'John F. Kennedy International Airport', 'city' => 'New York', 'country' => 'United States'],
            ['iata' => 'ORD', 'icao' => 'KORD', 'name' => "O'Hare International Airport", 'city' => 'Chicago', 'country' => 'United States'],
            ['iata' => 'KUL', 'icao' => 'WMKK', 'name' => 'Kuala Lumpur International Airport', 'city' => 'Kuala Lumpur', 'country' => 'Malaysia'],
            ['iata' => 'BKK', 'icao' => 'VTBS', 'name' => 'Suvarnabhumi Airport', 'city' => 'Bangkok', 'country' => 'Thailand'],
            ['iata' => 'SIN', 'icao' => 'WSSS', 'name' => 'Changi Airport', 'city' => 'Singapore', 'country' => 'Singapore'],
        ];

        foreach ($airports as $airport) {
            Airport::query()->updateOrCreate(
                ['iata_code' => $airport['iata']],
                [
                    'icao_code' => $airport['icao'],
                    'name' => $airport['name'],
                    'city' => $airport['city'],
                    'country' => $airport['country'],
                    'priority_score' => 150,
                    'is_commercial' => true,
                    'is_active' => true,
                    'search_keywords' => strtolower(implode(' ', [$airport['iata'], $airport['icao'], $airport['name'], $airport['city'], $airport['country']])),
                    'meta' => ['source' => 'fallback-seeder'],
                ]
            );
        }

        $airlines = [
            ['iata' => 'PK', 'icao' => 'PIA', 'name' => 'Pakistan International Airlines', 'country' => 'Pakistan'],
            ['iata' => 'EK', 'icao' => 'UAE', 'name' => 'Emirates', 'country' => 'United Arab Emirates'],
            ['iata' => 'SV', 'icao' => 'SVA', 'name' => 'Saudia', 'country' => 'Saudi Arabia'],
            ['iata' => 'QR', 'icao' => 'QTR', 'name' => 'Qatar Airways', 'country' => 'Qatar'],
            ['iata' => 'EY', 'icao' => 'ETD', 'name' => 'Etihad Airways', 'country' => 'United Arab Emirates'],
            ['iata' => 'FZ', 'icao' => 'FDB', 'name' => 'flydubai', 'country' => 'United Arab Emirates'],
            ['iata' => 'TK', 'icao' => 'THY', 'name' => 'Turkish Airlines', 'country' => 'Turkey'],
            ['iata' => 'BA', 'icao' => 'BAW', 'name' => 'British Airways', 'country' => 'United Kingdom'],
            ['iata' => 'QF', 'icao' => 'QFA', 'name' => 'Qantas', 'country' => 'Australia'],
            ['iata' => 'AC', 'icao' => 'ACA', 'name' => 'Air Canada', 'country' => 'Canada'],
        ];

        foreach ($airlines as $airline) {
            Airline::query()->updateOrCreate(
                ['iata_code' => $airline['iata']],
                [
                    'icao_code' => $airline['icao'],
                    'name' => $airline['name'],
                    'country' => $airline['country'],
                    'is_active' => true,
                    'search_keywords' => strtolower(implode(' ', [$airline['iata'], $airline['icao'], $airline['name'], $airline['country']])),
                    'meta' => ['source' => 'fallback-seeder'],
                ]
            );
        }
    }
}
