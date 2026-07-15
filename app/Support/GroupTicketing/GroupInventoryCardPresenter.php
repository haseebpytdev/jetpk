<?php

namespace App\Support\GroupTicketing;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\GroupInventory;
use App\Services\GroupTicketing\GroupBookingRestrictionService;
use App\Services\TravelData\AirlineBrandingService;
use App\Support\TravelData\AirportDisplayLabelResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * View-model for public group inventory result cards (search + detail).
 */
class GroupInventoryCardPresenter
{
    /** @var array<string, string> */
    private const AIRLINE_NAME_TO_CODE = [
        'AIR ARABIA' => 'G9',
        'FLYNAS' => 'XY',
        'SAUDI AIRLINE' => 'SV',
        'SAUDI AIRLINES' => 'SV',
        'FLY JINNAH' => '9P',
        'AIR SIAL' => 'PF',
        'SALAM AIR' => 'OV',
        'FLYADEAL' => 'F3',
    ];

    public function __construct(
        protected AirlineBrandingService $airlineBranding,
        protected GroupBookingRestrictionService $restrictionService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(GroupInventory $inventory, bool $bookable = true): array
    {
        return $this->presentMany(collect([$inventory]), $bookable)->get($inventory->id, []);
    }

    /**
     * Normalized sidebar summary for shared checkout UI.
     *
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    public function buildCheckoutSummary(array $card, int $seatCount, ?float $totalAmount = null): array
    {
        $seatCount = max(1, $seatCount);
        $pricePerAdult = (float) str_replace(',', '', (string) ($card['price_formatted'] ?? '0'));
        $computedTotal = $totalAmount ?? ($pricePerAdult * $seatCount);
        $baggage = is_array($card['baggage'] ?? null) ? $card['baggage'] : [];

        return [
            'product_type' => 'Group Ticketing',
            'route_line' => (string) ($card['route_line'] ?? ''),
            'sector_code' => (string) ($card['sector_code'] ?? ''),
            'departure_date_short' => (string) ($card['departure_date_short'] ?? $card['departure_date'] ?? ''),
            'baggage_display' => (string) ($baggage['display'] ?? ''),
            'airline_name' => (string) ($card['airline_name'] ?? ''),
            'airline_logo_url' => $card['airline_logo_url'] ?? null,
            'price_per_adult_formatted' => (string) ($card['price_formatted'] ?? number_format($pricePerAdult, 0)),
            'currency' => (string) ($card['currency'] ?? 'PKR'),
            'total_formatted' => number_format($computedTotal, 0),
            'seat_count' => $seatCount,
            'seats_selected' => $seatCount,
            'available_seats' => (int) ($card['available_seats'] ?? 0),
        ];
    }

    /**
     * @param  Collection<int, GroupInventory>  $inventories
     * @return Collection<int, array<string, mixed>>
     */
    public function presentMany(Collection $inventories, bool $bookable = true): Collection
    {
        if ($inventories->isEmpty()) {
            return collect();
        }

        $iataCodes = $this->collectIataCodes($inventories);
        $airports = $this->loadAirports($iataCodes);
        $airlines = $this->loadAirlines($inventories);

        return $inventories->mapWithKeys(function (GroupInventory $inventory) use ($airports, $airlines, $bookable): array {
            return [$inventory->id => $this->buildCard($inventory, $airports, $airlines, $bookable)];
        });
    }

    /**
     * @param  Collection<string, Airport>  $airports
     * @param  Collection<int, Airline>  $airlines
     * @return array<string, mixed>
     */
    private function buildCard(GroupInventory $inventory, Collection $airports, Collection $airlines, bool $bookable = true): array
    {
        [$originCode, $destCode] = $this->parseSector($inventory->sector);
        $origin = $this->formatEndpoint($originCode, $airports);
        $dest = $this->formatEndpoint($destCode, $airports);

        $routeLine = $this->buildRouteLine($origin, $dest);
        $airlineCode = $this->resolveAirlineCode($inventory, $airlines);
        $airlineName = trim((string) ($inventory->airline_name ?? ''));
        $logoUrl = $airlineCode !== null ? $this->airlineBranding->getLogoForCode($airlineCode) : null;

        $availableSeats = $inventory->availableSeats();
        $passengersPath = route('group-ticketing.booking.passengers', $inventory, false);
        $blocked = auth()->check() && $this->restrictionService->isBlocked(auth()->user());
        $bookingAllowed = $bookable && ! $blocked && $availableSeats > 0;

        $sectorCode = ($originCode && $destCode) ? "{$originCode}-{$destCode}" : trim((string) $inventory->sector);
        $baggage = $this->formatBaggage($inventory->baggage);
        $snapshot = is_array($inventory->snapshot) ? $inventory->snapshot : [];
        $mealDisplay = $this->resolveMealDisplay($snapshot);
        $flightTimes = $this->resolveFlightTimes($snapshot, $inventory->departure_date?->format('j M Y'));

        return [
            'id' => $inventory->id,
            'title' => $inventory->title,
            'sector_raw' => $inventory->sector,
            'sector_code' => $sectorCode,
            'origin_code' => $originCode,
            'dest_code' => $destCode,
            'origin_label' => $origin['label'],
            'dest_label' => $dest['label'],
            'route_line' => $routeLine,
            'departure_date' => $inventory->departure_date?->format('D, j M Y'),
            'departure_date_short' => $inventory->departure_date?->format('j M Y'),
            'departure_datetime_display' => $flightTimes['departure_datetime_display'],
            'arrival_time_display' => $flightTimes['arrival_time_display'],
            'meal_status' => $mealDisplay['status'],
            'meal_label' => $mealDisplay['label'],
            'baggage' => $baggage,
            'baggage_line' => $this->buildBaggageLine($baggage),
            'sector_line' => $sectorCode !== '' ? "Sector: {$sectorCode}" : '',
            'refund_change_notes' => $inventory->refund_change_notes,
            'airline_name' => $airlineName !== '' ? $airlineName : ($airlineCode ?? 'Airline'),
            'airline_code' => $airlineCode,
            'airline_logo_url' => $logoUrl,
            'price_formatted' => number_format((float) $inventory->price, 0),
            'currency' => $inventory->currency,
            'available_seats' => $availableSeats,
            'seat_label' => $this->formatSeatLabel($availableSeats),
            'seats_badge_variant' => $availableSeats > 0 ? ($availableSeats <= 3 ? 'warn' : 'ok') : 'warn',
            'cta_label' => ! $bookable
                ? 'Unavailable'
                : ($blocked ? 'Booking restricted' : 'Book now'),
            'cta_url' => $bookingAllowed
                ? (auth()->check()
                    ? route('group-ticketing.booking.passengers', $inventory)
                    : route('login', ['redirect' => $passengersPath, 'checkout_return' => $passengersPath]))
                : '#',
            'cta_disabled' => ! $bookingAllowed,
            'cta_message' => ! $bookable
                ? GroupTicketingLivePolicy::PUBLIC_SEARCH_UNAVAILABLE_MESSAGE
                : ($blocked
                    ? 'Your group booking access is temporarily restricted because 3 reservations expired without payment. Please contact support or request admin reset.'
                    : null),
            'show_url' => route('group-ticketing.show', $inventory->public_id ?: $inventory->id),
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function parseSector(?string $sector): array
    {
        $sector = trim((string) $sector);
        if ($sector === '') {
            return [null, null];
        }

        $parts = preg_split('/\s*[-–→]\s*/u', $sector);
        if (! is_array($parts) || count($parts) < 2) {
            return [null, null];
        }

        $origin = $this->normalizeIata($parts[0]);
        $dest = $this->normalizeIata($parts[1]);

        return [$origin, $dest];
    }

    private function normalizeIata(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return preg_match('/^[A-Z]{3}$/', $code) === 1 ? $code : null;
    }

    /**
     * @param  Collection<int, GroupInventory>  $inventories
     * @return list<string>
     */
    private function collectIataCodes(Collection $inventories): array
    {
        $codes = [];
        foreach ($inventories as $inventory) {
            [$origin, $dest] = $this->parseSector($inventory->sector);
            if ($origin !== null) {
                $codes[] = $origin;
            }
            if ($dest !== null) {
                $codes[] = $dest;
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * @param  list<string>  $iataCodes
     * @return Collection<string, Airport>
     */
    private function loadAirports(array $iataCodes): Collection
    {
        if ($iataCodes === []) {
            return collect();
        }

        return Airport::query()
            ->active()
            ->whereIn('iata_code', $iataCodes)
            ->get()
            ->keyBy(fn (Airport $airport): string => strtoupper((string) $airport->iata_code));
    }

    /**
     * @param  Collection<int, GroupInventory>  $inventories
     * @return Collection<int, Airline>
     */
    private function loadAirlines(Collection $inventories): Collection
    {
        $ids = $inventories
            ->pluck('airline_id')
            ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return Airline::query()
            ->active()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  Collection<string, Airport>  $airports
     * @return array{label: string, city: ?string, country: ?string}
     */
    private function formatEndpoint(?string $code, Collection $airports): array
    {
        if ($code === null) {
            return ['label' => '—', 'city' => null, 'country' => null];
        }

        $airport = $airports->get($code);

        return AirportDisplayLabelResolver::resolveEndpoint($code, $airport);
    }

    /**
     * @param  array{label: string, city: ?string, country: ?string}  $origin
     * @param  array{label: string, city: ?string, country: ?string}  $dest
     */
    private function buildRouteLine(array $origin, array $dest): string
    {
        if ($origin['label'] === '—' && $dest['label'] === '—') {
            return '—';
        }

        return $origin['label'].' → '.$dest['label'];
    }

    /**
     * @param  Collection<int, Airline>  $airlines
     */
    private function resolveAirlineCode(GroupInventory $inventory, Collection $airlines): ?string
    {
        if (is_numeric($inventory->airline_id) && (int) $inventory->airline_id > 0) {
            $airline = $airlines->get((int) $inventory->airline_id);
            if ($airline !== null) {
                $code = trim((string) ($airline->iata_code ?? ''));
                if ($code !== '') {
                    return Str::upper($code);
                }
            }
        }

        $snapshot = is_array($inventory->snapshot) ? $inventory->snapshot : [];
        foreach (['airline_code', 'carrier_code', 'iata_code'] as $key) {
            $code = trim((string) ($snapshot[$key] ?? ''));
            if ($code !== '') {
                return Str::upper($code);
            }
        }

        $name = Str::upper(trim((string) ($inventory->airline_name ?? '')));
        if ($name !== '' && isset(self::AIRLINE_NAME_TO_CODE[$name])) {
            return self::AIRLINE_NAME_TO_CODE[$name];
        }

        foreach (self::AIRLINE_NAME_TO_CODE as $needle => $mapped) {
            if ($name !== '' && str_contains($name, $needle)) {
                return $mapped;
            }
        }

        return null;
    }

    private function formatSeatLabel(int $availableSeats): string
    {
        if ($availableSeats === 1) {
            return '1 seat left';
        }

        return "{$availableSeats} seats left";
    }

    /**
     * @param  array{display: string, checked: ?string, cabin: ?string, raw: ?string}  $baggage
     */
    private function buildBaggageLine(array $baggage): string
    {
        if (! empty($baggage['checked']) && ! empty($baggage['cabin'])) {
            return 'Baggage: Checked '.$baggage['checked'].' · Cabin '.$baggage['cabin'];
        }

        $display = trim((string) ($baggage['display'] ?? ''));

        return $display !== '' ? 'Baggage: '.$display : '';
    }

    /**
     * @return array{display: string, checked: ?string, cabin: ?string, raw: ?string}
     */
    private function formatBaggage(?string $baggage): array
    {
        $raw = trim((string) $baggage);
        if ($raw === '') {
            return ['display' => '', 'checked' => null, 'cabin' => null, 'raw' => null];
        }

        if (preg_match('/^(\d+)\s*[\+\-\/]\s*(\d+)$/u', $raw, $matches) === 1) {
            $checked = $matches[1].'kg';
            $cabin = $matches[2].'kg';

            return [
                'display' => "Checked: {$checked} · Cabin: {$cabin}",
                'checked' => $checked,
                'cabin' => $cabin,
                'raw' => $raw,
            ];
        }

        return ['display' => $raw, 'checked' => null, 'cabin' => null, 'raw' => $raw];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{status: string, label: string}
     */
    private function resolveMealDisplay(array $snapshot): array
    {
        $meal = trim((string) ($snapshot['meal'] ?? ''));

        if ($meal === 'Included') {
            return ['status' => 'included', 'label' => 'Meal included'];
        }

        if ($meal === 'Not included') {
            return ['status' => 'excluded', 'label' => 'No meal'];
        }

        return ['status' => 'unspecified', 'label' => 'Meal: Not specified'];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{departure_datetime_display: string, arrival_time_display: ?string}
     */
    private function resolveFlightTimes(array $snapshot, ?string $dateShort): array
    {
        $dateShort = trim((string) ($dateShort ?? ''));
        $outboundLeg = $this->resolveOutboundLeg($snapshot);
        $departureTime = $this->normalizeTimeValue($outboundLeg['departure_time'] ?? null);
        $arrivalTime = $this->normalizeTimeValue($outboundLeg['arrival_time'] ?? null);

        $departureDisplay = $dateShort !== '' ? $dateShort : '—';
        if ($departureTime !== null && $dateShort !== '') {
            $departureDisplay = $dateShort.' · '.$departureTime;
        }

        return [
            'departure_datetime_display' => $departureDisplay,
            'arrival_time_display' => $arrivalTime,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function resolveOutboundLeg(array $snapshot): array
    {
        $legs = $snapshot['legs'] ?? [];
        if (! is_array($legs) || $legs === []) {
            return [];
        }

        foreach ($legs as $leg) {
            if (! is_array($leg)) {
                continue;
            }
            if (($leg['type'] ?? '') === 'outbound') {
                return $leg;
            }
        }

        $first = $legs[0];

        return is_array($first) ? $first : [];
    }

    private function normalizeTimeValue(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}/', $value) === 1) {
            return substr($value, 0, 5);
        }

        if (preg_match('/^\d{3,4}$/', $value) === 1) {
            return str_pad(substr($value, 0, -2), 2, '0', STR_PAD_LEFT).':'.substr($value, -2);
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('H:i', $timestamp) : null;
    }
}
