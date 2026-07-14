<?php

namespace App\Support\Time;

use Illuminate\Support\Carbon;

/**
 * Formats UTC-stored timestamps for local display with optional UTC tooltip text.
 */
class LocalTimeDisplay
{
    /**
     * @return array{label: string, utc_title: string}|null
     */
    public function format(Carbon|string|null $value, string $timezone, bool $includeUtcTitle = false): ?array
    {
        $local = $this->toLocal($value, $timezone);
        if ($local === null) {
            return null;
        }

        $label = $local->format('d M Y, g:i A T');
        $utcTitle = 'UTC: '.$local->copy()->utc()->format('d M Y, g:i A').' UTC';

        return [
            'label' => $label,
            'utc_title' => $includeUtcTitle ? $utcTitle : '',
        ];
    }

    /**
     * Short label for countdown expiry hints, e.g. "11:42 PM PKT".
     */
    public function formatExpiryHint(Carbon|string|null $value, string $timezone): ?string
    {
        $local = $this->toLocal($value, $timezone);

        return $local?->format('g:i A T');
    }

    public function toLocal(Carbon|string|null $value, string $timezone): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $carbon = $value instanceof Carbon ? $value->copy() : Carbon::parse($value);

            return $carbon->utc()->timezone(DisplayTimezoneResolver::safeTimezone($timezone));
        } catch (\Throwable) {
            return null;
        }
    }
}
