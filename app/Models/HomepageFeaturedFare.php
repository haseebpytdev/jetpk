<?php

namespace App\Models;

use App\Enums\HomepageFeaturedFareRefreshStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'agency_id',
    'title',
    'origin_code',
    'destination_code',
    'date_offset_days',
    'cabin',
    'adults',
    'is_enabled',
    'sort_order',
    'last_refreshed_at',
    'last_status',
    'last_error_code',
    'last_error_message',
    'snapshot',
])]
class HomepageFeaturedFare extends Model
{
    public const ALLOWED_DATE_OFFSETS = [3, 5, 7];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'snapshot' => 'array',
            'last_refreshed_at' => 'datetime',
            'last_status' => HomepageFeaturedFareRefreshStatus::class,
            'date_offset_days' => 'integer',
            'adults' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function departureDate(?Carbon $base = null): string
    {
        $base ??= now();

        return $base->copy()->addDays($this->date_offset_days)->toDateString();
    }

    /**
     * @return array<string, mixed>
     */
    public function searchCriteria(?Carbon $base = null): array
    {
        return [
            'origin' => strtoupper($this->origin_code),
            'destination' => strtoupper($this->destination_code),
            'departure_date' => $this->departureDate($base),
            'trip_type' => 'one_way',
            'adults' => max(1, (int) $this->adults),
            'children' => 0,
            'infants' => 0,
            'cabin' => $this->cabin ?: 'economy',
        ];
    }

    public function viewFaresUrl(?Carbon $base = null): string
    {
        $criteria = $this->searchCriteria($base);

        return route('flights.results', [
            'from' => $criteria['origin'],
            'to' => $criteria['destination'],
            'depart' => $criteria['departure_date'],
            'trip_type' => 'one_way',
            'cabin' => $criteria['cabin'],
            'adults' => $criteria['adults'],
            'children' => 0,
            'infants' => 0,
        ]);
    }

    public function hasDisplayableSnapshot(): bool
    {
        return $this->last_status === HomepageFeaturedFareRefreshStatus::Success
            && is_array($this->snapshot)
            && ($this->snapshot['price_total'] ?? 0) > 0;
    }
}
