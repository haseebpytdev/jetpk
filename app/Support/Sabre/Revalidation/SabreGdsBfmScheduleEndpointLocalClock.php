<?php

namespace App\Support\Sabre\Revalidation;

/**
 * Shared Sabre BFM scheduleDesc endpoint local wall-clock rules for shop normalization and revalidation linkage.
 */
final class SabreGdsBfmScheduleEndpointLocalClock
{
    public function endpointLocalTimePresent(array $schedule, string $endpoint): bool
    {
        if (! in_array($endpoint, ['departure', 'arrival'], true)) {
            return false;
        }
        $node = data_get($schedule, $endpoint);

        return is_array($node) && trim((string) ($node['time'] ?? '')) !== '';
    }

    public function endpointDateTimePresent(array $schedule, string $endpoint): bool
    {
        if (! in_array($endpoint, ['departure', 'arrival'], true)) {
            return false;
        }
        $node = data_get($schedule, $endpoint);
        if (! is_array($node)) {
            return false;
        }

        return trim((string) ($node['dateTime'] ?? $node['date'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    public function endpointClockRaw(array $schedule, string $endpoint): string
    {
        if (! in_array($endpoint, ['departure', 'arrival'], true)) {
            return '';
        }
        $node = data_get($schedule, $endpoint);
        if (! is_array($node)) {
            return '';
        }
        $time = trim((string) ($node['time'] ?? ''));
        if ($time !== '') {
            return $time;
        }

        return trim((string) ($node['dateTime'] ?? $node['date'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    public function endpointClockSourceShapeCategory(array $schedule, string $endpoint): string
    {
        if ($this->endpointLocalTimePresent($schedule, $endpoint)) {
            return 'bfm_endpoint_time_local';
        }
        $raw = $this->endpointClockRaw($schedule, $endpoint);
        if ($raw === '') {
            return 'absent';
        }

        return $this->rawDateTimeShapeCategory($raw);
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    public function endpointDateAdjustmentDays(array $schedule, string $endpoint): int
    {
        if (! in_array($endpoint, ['departure', 'arrival'], true)) {
            return 0;
        }
        $node = data_get($schedule, $endpoint);
        if (is_array($node)) {
            $keys = $endpoint === 'departure'
                ? ['dateAdjustment', 'departureDateAdjustment', 'dayAdjustment']
                : ['dateAdjustment', 'arrivalDateAdjustment', 'dayAdjustment'];
            foreach ($keys as $key) {
                if (isset($node[$key]) && is_numeric($node[$key])) {
                    return (int) $node[$key];
                }
            }
        }
        $top = $endpoint === 'departure'
            ? data_get($schedule, 'departureDateAdjustment')
            : data_get($schedule, 'arrivalDateAdjustment');

        return is_numeric($top) ? (int) $top : 0;
    }

    public function rawDateTimeShapeCategory(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 'absent';
        }
        if (preg_match('/\d{4}-\d{2}-\d{2}/', $raw) === 1) {
            return 'iso_datetime_with_date';
        }
        if (preg_match('/^\d{2}:\d{2}/', $raw) === 1) {
            return 'clock_only';
        }
        if (preg_match('/^\d{4}$/', str_replace(':', '', $raw)) === 1) {
            return 'compact_hhmm';
        }

        return 'other';
    }

    public function normalizedWallClockFromRaw(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $normalized = $this->normalizeSignatureDateTime($value);
        if ($normalized !== '' && str_contains($normalized, '|')) {
            $parts = explode('|', $normalized, 2);
            $clock = $parts[1] ?? '';
            if ($clock !== '') {
                return $this->normalizeWallClockToken($clock);
            }
        }
        $compact = str_replace(' ', '', $value);
        $compact = preg_replace('/(?:Z|[+-]\d{2}:?\d{2})$/i', '', $compact) ?? $compact;
        if (preg_match('/^\d{4}$/', $compact) === 1) {
            $h = (int) substr($compact, 0, 2);
            $m = (int) substr($compact, 2, 2);
            if ($h <= 23 && $m <= 59) {
                return sprintf('%02d:%02d', $h, $m);
            }
        }
        if (preg_match('/^(?:T)?(\d{2}:\d{2})(?::\d{2})?(?:\.\d+)?$/', $compact, $matches) === 1) {
            return $matches[1];
        }

        return $normalized !== '' && ! str_contains($normalized, '|') ? $this->normalizeWallClockToken($normalized) : '';
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @return array<string, mixed>
     */
    public function endpointEvidence(array $schedule, string $endpoint, string $composedIso = ''): array
    {
        $wallFromComposed = $composedIso !== '' ? $this->normalizedWallClockFromRaw($composedIso) : '';
        $wallFromEndpoint = $this->normalizedWallClockFromRaw($this->endpointClockRaw($schedule, $endpoint));

        return array_filter([
            'endpoint_local_time_present' => $this->endpointLocalTimePresent($schedule, $endpoint),
            'endpoint_datetime_present' => $this->endpointDateTimePresent($schedule, $endpoint),
            'chosen_source' => $this->endpointClockSourceShapeCategory($schedule, $endpoint),
            'normalized_wall_clock' => $wallFromComposed !== '' ? $wallFromComposed : ($wallFromEndpoint !== '' ? $wallFromEndpoint : null),
            'date_adjustment_days' => $this->endpointDateAdjustmentDays($schedule, $endpoint),
            'source_shape_category' => $this->endpointClockSourceShapeCategory($schedule, $endpoint),
        ], static fn ($value) => $value !== null && $value !== false);
    }

    public function normalizeSignatureDateTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $value = preg_replace('/\.\d+/', '', $value) ?? $value;
        $value = preg_replace('/[Zz]$/', '', $value) ?? $value;
        $value = preg_replace('/([+-]\d{2}:?\d{2})$/', '', $value) ?? $value;
        $value = str_replace(' ', 'T', $value);

        $date = '';
        $time = '';
        if (preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/', $value, $matches) === 1) {
            $date = $matches[1];
            $time = $matches[2];
        } elseif (preg_match('/^(\d{2}:\d{2})/', $value, $matches) === 1) {
            $time = $matches[1];
        } else {
            return substr($value, 0, 24);
        }

        return $date !== '' ? $date.'|'.$time : '|'.$time;
    }

    private function normalizeWallClockToken(string $clock): string
    {
        if (preg_match('/^(\d{2}):(\d{2})/', trim($clock), $matches) === 1) {
            return $matches[1].':'.$matches[2];
        }

        return trim($clock);
    }
}
