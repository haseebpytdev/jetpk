<?php

namespace App\Support\Suppliers;

/**
 * Safe Sabre grouped-itinerary (GIR) counters for Sabre NDC HTTP-200 zero-offer responses.
 */
final class SabreNdcGroupedItineraryDiagnostics
{
    public function __construct(
        private readonly SabreNdcGroupedItineraryMessageExtractor $messageExtractor,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public function summarize(array $response): array
    {
        $gir = $response['groupedItineraryResponse'] ?? null;
        if (! is_array($gir)) {
            return array_merge($this->emptyShape(), $this->messageExtractor->extract($response));
        }

        $groups = is_array($gir['itineraryGroups'] ?? null) ? $gir['itineraryGroups'] : [];
        $itineraryCount = 0;
        $pricingInformationCount = 0;

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }
            $itins = is_array($group['itineraries'] ?? null) ? $group['itineraries'] : [];
            $itineraryCount += count($itins);
            foreach ($itins as $itin) {
                if (! is_array($itin)) {
                    continue;
                }
                $pricing = is_array($itin['pricingInformation'] ?? null) ? $itin['pricingInformation'] : [];
                $pricingInformationCount += count($pricing);
            }
        }

        $scheduleDescCount = is_array($gir['scheduleDescs'] ?? null) ? count($gir['scheduleDescs']) : 0;
        $legDescCount = is_array($gir['legDescs'] ?? null) ? count($gir['legDescs']) : 0;
        $messages = $this->messageExtractor->extract($response);
        $messageRows = is_array($messages['message_rows'] ?? null) ? $messages['message_rows'] : [];

        $errorFamilies = [];
        foreach ($messageRows as $row) {
            $type = strtolower((string) ($row['type'] ?? ''));
            $code = trim((string) ($row['code'] ?? ''));
            if (in_array($type, ['error', 'warning'], true)) {
                $errorFamilies[] = $code !== '' ? 'sabre_'.$code : 'sabre_'.$type;
            }
        }

        $appStatus = data_get($gir, 'statistics.itineraryCount') !== null
            ? 'statistics_present'
            : (data_get($response, 'status') ?? data_get($gir, 'status'));

        return array_merge($messages, [
            'response_shape' => 'grouped_itinerary',
            'offer_count' => $itineraryCount,
            'offer_count_raw' => $itineraryCount,
            'itinerary_group_count' => count($groups),
            'itinerary_count' => $itineraryCount,
            'schedule_desc_count' => $scheduleDescCount,
            'leg_desc_count' => $legDescCount,
            'pricing_information_count' => $pricingInformationCount,
            'warning_rows' => array_values(array_filter(
                $messageRows,
                fn (array $row): bool => in_array(strtolower((string) ($row['type'] ?? '')), ['warning'], true)
                    || in_array(strtolower((string) ($row['severity'] ?? '')), ['warning'], true),
            )),
            'error_rows' => array_values(array_filter(
                $messageRows,
                fn (array $row): bool => in_array(strtolower((string) ($row['type'] ?? '')), ['error'], true)
                    || in_array(strtolower((string) ($row['severity'] ?? '')), ['error'], true),
            )),
            'application_status' => is_scalar($appStatus) ? (string) $appStatus : null,
            'error_families' => array_values(array_unique($errorFamilies)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyShape(): array
    {
        return [
            'response_shape' => 'unknown',
            'offer_count' => 0,
            'offer_count_raw' => 0,
            'itinerary_group_count' => 0,
            'itinerary_count' => 0,
            'schedule_desc_count' => 0,
            'leg_desc_count' => 0,
            'pricing_information_count' => 0,
            'error_families' => ['malformed_response'],
        ];
    }
}
