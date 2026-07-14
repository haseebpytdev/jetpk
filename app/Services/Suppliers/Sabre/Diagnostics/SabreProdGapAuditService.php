<?php

namespace App\Services\Suppliers\Sabre\Diagnostics;

use App\Services\Suppliers\Sabre\Core\SabreCapabilityMatrixService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Code-level Sabre prod gap audit — verifies implementation presence vs Binham reference scope.
 *
 * Aligns with {@see SabreCapabilityMatrixService} for posture columns; pass/fail is code existence only.
 */
final class SabreProdGapAuditService
{
    public function __construct(
        private SabreCapabilityMatrixService $capabilityMatrix = new SabreCapabilityMatrixService,
    ) {}

    /**
     * @return array{
     *     audit_version: string,
     *     pass: int,
     *     fail: int,
     *     partial: int,
     *     manual: int,
     *     matrix_mismatches: int,
     *     capabilities: list<array<string, mixed>>
     * }
     */
    public function run(): array
    {
        $capabilities = [
            $this->auditCapability('gds_revalidation', [
                'service' => 'App\\Services\\Suppliers\\Sabre\\Gds\\SabreGdsRevalidationService',
                'methods' => ['revalidateForBooking', 'revalidateDraft'],
                'commands' => ['sabre:gds-revalidate'],
            ]),
            $this->auditCapability('multi_city_revalidation', [
                'service' => 'App\\Services\\Suppliers\\Sabre\\Gds\\SabreGdsRevalidationService',
                'methods' => ['revalidateMulticityDraft'],
                'commands' => ['sabre:gds-revalidate-multicity'],
            ]),
            $this->auditCapability('multi_city_booking', [
                'service' => 'App\\Services\\Suppliers\\Sabre\\Booking\\SabreBookingService',
                'methods' => ['createBooking', 'prepareBookingPayload'],
                'commands' => ['sabre:gds-create-pnr-production'],
                'config' => ['suppliers.sabre.complex_itinerary_pnr_enabled'],
            ]),
            $this->auditCapability('gds_pnr_create', [
                'service' => 'App\\Services\\Suppliers\\Sabre\\Booking\\SabreBookingService',
                'methods' => ['createBooking', 'createSupplierBooking'],
                'commands' => ['sabre:gds-create-pnr-production', 'sabre:controlled-create-pnr'],
            ]),
            $this->auditCapability('ticket_issue', [
                'service' => 'App\\Services\\Suppliers\\Sabre\\Ticketing\\SabreGdsTicketingService',
                'methods' => ['issueTickets'],
                'commands' => ['sabre:gds-issue-ticket'],
            ]),
            $this->auditCapability('ticket_documents', [
                'service' => 'App\\Services\\Suppliers\\Sabre\\Ticketing\\SabreGdsTicketDocumentService',
                'methods' => ['retrieve'],
                'commands' => ['sabre:gds-ticket-documents'],
            ]),
            $this->auditCapability('void', [
                'service' => 'App\\Services\\Suppliers\\Sabre\\Ticketing\\SabreGdsVoidTicketService',
                'methods' => ['voidTicket'],
                'commands' => ['sabre:gds-void-ticket'],
                'manual_note' => 'Binham uses cancelBooking; true ticket void may require configured voidFlightTickets path.',
            ]),
            $this->auditCapability('refund', [
                'service' => 'App\\Services\\Suppliers\\Sabre\\Ticketing\\SabreGdsRefundTicketService',
                'methods' => ['refundTicket'],
                'commands' => ['sabre:gds-refund-ticket'],
                'manual_note' => 'Binham refund is manual Red Workspace; OTA records manual refund workflow when live API unavailable.',
            ]),
            $this->auditCapability('cancel', [
                'service' => 'App\\Services\\Suppliers\\Sabre\\Cancel\\SabreBookingCancelService',
                'methods' => ['cancelForBooking'],
                'commands' => ['sabre:production-cancel-evidence', 'sabre:inspect-cancel-booking'],
            ]),
            $this->auditCapability('ndc_reprice', [
                'service' => 'App\\Services\\Suppliers\\Sabre\\Ndc\\SabreNdcRepriceOrderService',
                'methods' => ['repriceOrder'],
                'commands' => ['sabre:ndc-reprice-order'],
            ]),
            $this->auditCapability('ndc_order_change', [
                'service' => 'App\\Services\\Suppliers\\Sabre\\Ndc\\SabreNdcOrderChangeService',
                'methods' => ['changeOrder'],
                'commands' => ['sabre:ndc-order-change'],
            ]),
            $this->auditCapability('ndc_retrieve', [
                'service' => 'App\\Services\\Suppliers\\Sabre\\Ndc\\SabreNdcOrderRetrieveService',
                'methods' => ['retrieveOrder'],
                'commands' => ['sabre:ndc-retrieve-order'],
            ]),
            $this->auditCapability('multi_city_search', [
                'service' => 'App\\Services\\Suppliers\\Sabre\\Gds\\SabreFlightSearchRequestBuilder',
                'methods' => ['build'],
                'commands' => ['sabre:multicity-search-probe'],
            ]),
        ];

        $pass = 0;
        $fail = 0;
        $partial = 0;
        $manual = 0;
        $matrixMismatches = 0;

        foreach ($capabilities as &$cap) {
            $cap = $this->attachMatrixPosture($cap);
            if (($cap['matrix_mismatch'] ?? false) === true) {
                $matrixMismatches++;
            }

            match ($cap['status']) {
                'complete' => $pass++,
                'partial' => $partial++,
                'provider_unsupported_manual' => $manual++,
                default => $fail++,
            };
        }
        unset($cap);

        return [
            'audit_version' => 'sabre_prod_gap_audit_v2',
            'pass' => $pass,
            'fail' => $fail,
            'partial' => $partial,
            'manual' => $manual,
            'matrix_mismatches' => $matrixMismatches,
            'capabilities' => $capabilities,
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    private function auditCapability(string $key, array $spec): array
    {
        $issues = [];
        $serviceClass = (string) ($spec['service'] ?? '');
        $servicePath = $this->classToPath($serviceClass);

        if ($servicePath === null || ! File::exists($servicePath)) {
            $issues[] = 'missing_service_class';
        } else {
            if (! class_exists($serviceClass)) {
                $issues[] = 'service_class_not_autoloadable';
            } else {
                foreach ($spec['methods'] ?? [] as $method) {
                    if (! method_exists($serviceClass, $method)) {
                        $issues[] = 'missing_method_'.$method;
                    }
                }
            }
        }

        foreach ($spec['commands'] ?? [] as $command) {
            if (! $this->commandRegistered($command)) {
                $issues[] = 'missing_command_'.$command;
            }
        }

        $status = 'complete';
        if ($issues !== []) {
            $status = 'missing';
        } elseif (isset($spec['manual_note'])) {
            $status = 'provider_unsupported_manual';
        }

        return [
            'key' => $key,
            'status' => $status,
            'ota_service' => $serviceClass,
            'ota_commands' => $spec['commands'] ?? [],
            'issues' => $issues,
            'manual_note' => $spec['manual_note'] ?? null,
            'evidence_command' => $spec['commands'][0] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $cap
     * @return array<string, mixed>
     */
    private function attachMatrixPosture(array $cap): array
    {
        $matrixKey = SabreCapabilityMatrixService::prodGapMatrixKeyMap()[$cap['key'] ?? ''] ?? null;
        $matrixRow = $matrixKey !== null ? $this->capabilityMatrix->get($matrixKey) : null;

        $codeImplemented = $matrixRow !== null
            ? ($matrixRow['code_implemented'] ?? 'no')
            : 'unknown';
        $production = $matrixRow['production'] ?? 'unknown';
        $liveHttp = $matrixRow['live_http'] ?? 'unknown';
        $evidence = $matrixRow['evidence'] ?? 'unknown';
        $manual = $matrixRow['manual'] ?? 'unknown';
        $command = $matrixRow['command'] ?? ($cap['evidence_command'] ?? null);

        $codeExists = in_array($cap['status'] ?? '', ['complete', 'provider_unsupported_manual'], true);
        $matrixSaysImplemented = $codeImplemented === 'yes';
        $matrixMismatch = $codeExists !== $matrixSaysImplemented;

        return array_merge($cap, [
            'matrix_key' => $matrixKey,
            'code_implemented' => $codeImplemented,
            'production' => $production,
            'live_http' => $liveHttp,
            'evidence' => $evidence,
            'manual' => $manual,
            'command' => $command,
            'matrix_mismatch' => $matrixMismatch,
        ]);
    }

    private function commandRegistered(string $signature): bool
    {
        $base = explode(' ', trim($signature))[0];

        try {
            $commands = array_keys(Artisan::all());

            return in_array($base, $commands, true);
        } catch (\Throwable) {
            return false;
        }
    }

    private function classToPath(string $class): ?string
    {
        if (! str_starts_with($class, 'App\\')) {
            return null;
        }

        $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 4));

        return base_path('app'.DIRECTORY_SEPARATOR.$relative.'.php');
    }
}
