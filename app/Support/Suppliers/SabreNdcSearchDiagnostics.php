<?php

namespace App\Support\Suppliers;

use App\Data\FlightSearchRequestData;
use App\Models\SupplierConnection;

/**
 * Safe, timestamped Sabre NDC search trace fields (no PII, credentials, or raw payloads).
 */
final class SabreNdcSearchDiagnostics
{
    /**
     * @param  array<string, mixed>  $laneDiagnostics
     * @return array<string, mixed>
     */
    public static function traceContext(
        FlightSearchRequestData $request,
        SupplierConnection $connection,
        array $laneDiagnostics,
    ): array {
        return [
            'search_id' => (string) ($request->search_id ?? ''),
            'route' => $request->origin.'-'.$request->destination,
            'origin' => $request->origin,
            'destination' => $request->destination,
            'departure_date' => $request->departure_date,
            'trip_type' => $request->trip_type,
            'adults' => $request->adults,
            'children' => $request->children,
            'infants' => $request->infants,
            'selected_sabre_lanes' => is_array($laneDiagnostics['selected_sabre_lanes'] ?? null)
                ? $laneDiagnostics['selected_sabre_lanes']
                : [],
            'connection_id' => $connection->id,
            'ndc_live_search_http_enabled' => (bool) config('suppliers.sabre.ndc.search_enabled', false),
            'gds_results_suppressed' => (bool) ($laneDiagnostics['gds_results_suppressed'] ?? false),
            'gds_called' => false,
            'mutation_attempted' => false,
        ];
    }
}
