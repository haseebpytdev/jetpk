<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use Illuminate\Support\Facades\File;

/**
 * CLI-only Hitit cancel evidence package for support escalation (no supplier calls).
 */
class PiaNdcCancelEvidenceService
{
    public function __construct(
        private readonly PiaNdcConfigResolver $configResolver,
        private readonly PiaNdcCancelRequestShapeSummarizer $shapeSummarizer,
        private readonly PiaNdcXmlBuilder $xmlBuilder,
    ) {}

    /**
     * @return array{success: bool, output_path: string, summary: array<string, mixed>}
     */
    public function buildReport(
        SupplierConnection $connection,
        string $orderId,
        string $ownerCode,
        ?string $outputPath = null,
    ): array {
        if ($connection->provider !== SupplierProvider::PiaNdc) {
            throw new PiaNdcValidationException('supplier_provider_mismatch', 422, 'Supplier connection is not PIA NDC.');
        }

        $orderId = trim($orderId);
        $ownerCode = trim($ownerCode);
        if ($orderId === '') {
            throw new PiaNdcValidationException('missing_order_reference', 422, 'Order ID / PNR is required.');
        }

        $config = $this->configResolver->resolve($connection);
        $generatedAt = now()->toIso8601String();
        $outputDirectory = $outputPath !== null && trim($outputPath) !== ''
            ? rtrim(trim($outputPath), '/\\')
            : storage_path('app/diagnostics/pia-ndc/cancel-evidence/'.$connection->id.'/'.$orderId.'/'.now()->format('YmdHis'));

        File::ensureDirectoryExists($outputDirectory);

        $retrieveSummaries = $this->collectDiagnosticSummaries('order-retrieve', $connection->id, $orderId);
        $cancelSummaries = $this->collectDiagnosticSummaries('order-cancel', $connection->id, $orderId);
        $probeSummaries = $this->collectProbeSummaries($connection->id, $orderId);

        $cancelAttempts = $this->sanitizeCancelAttempts(array_merge($cancelSummaries, $probeSummaries));
        $retrieveAttempts = $this->sanitizeRetrieveAttempts($retrieveSummaries);

        $sampleInventory = $this->localHititSampleInventory();
        $officialCancelSamples = $this->officialCancelSampleInventory();
        $sampleAlignment = $this->sampleAlignmentMetadata($officialCancelSamples);
        $operationMap = $this->hititOperationMap();
        $dryRunShapePreview = $this->dryRunShapePreview($config, $orderId, $ownerCode);
        $manualReferences = $this->hititManualReferences();
        $sampleFileSearch = $this->searchHititManualSampleFiles();

        $report = [
            'generated_at' => $generatedAt,
            'supplier_called' => false,
            'connection_id' => $connection->id,
            'owner_code' => $ownerCode,
            'order_id' => $orderId,
            'endpoint_host' => parse_url((string) $config['endpoint_url'], PHP_URL_HOST) ?: '[redacted-host]',
            'agency_id' => (string) $config['agency_id'],
            'environment' => (string) $config['environment'],
            'retrieve_attempts' => $retrieveAttempts,
            'cancel_attempts' => $cancelAttempts,
            'hitit_manual_references' => $manualReferences,
            'hitit_manual_sample_file_search' => $sampleFileSearch,
            'hitit_operation_map' => $operationMap,
            'official_cancel_sample_files' => $officialCancelSamples,
            'sample_alignment' => $sampleAlignment,
            'local_hitit_sample_inventory' => $sampleInventory,
            'dry_run_shape_preview' => $dryRunShapePreview,
            'notes' => [
                'All live cancel execute attempts returned HTTP 500 SOAP java.lang.NullPointerException in R11/R11B testing.',
                'Retrieve after failed cancel still finds the PNR — cancellation did not complete.',
                'R11F: live execute limited to hitit_cancel_preview_sample_exact, hitit_cancel_preview_sample_exact_with_contact_info, and hitit_cancel_preview_sample_exact_with_orderref_owner_attr (doOrderCancelPreview) with --confirm=PREVIEW_OPTION_PNR.',
                'Canonical preview (hitit_cancel_preview_sample_exact) failed with supplier_called=true in R11E live testing.',
                'Contact-info and owner-attribute preview variants are for R11F escalation when supplier_called=true.',
                'All preview attempts are view-only (doOrderCancelPreview); no doOrderCancelCommit was attempted.',
                'hitit_cancel_commit_sample_exact and doOrderCancelCommit remain dry-run/probe only.',
                'Aligned to official samples doOrderCancelPreview_OW_req.xml and doOrderCancelCommit_OW_req.xml.',
                'Manual §3.3: DoOrderCancelPreview (view) then DoOrderCancelCommit (commit).',
            ],
        ];

        file_put_contents(
            $outputDirectory.'/report.json',
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        $supportMessage = $this->buildSupportMessage($report);
        file_put_contents($outputDirectory.'/support_message.txt', $supportMessage);

        $summary = [
            'success' => true,
            'output_path' => $outputDirectory,
            'order_id' => $orderId,
            'retrieve_count' => count($retrieveAttempts),
            'cancel_count' => count($cancelAttempts),
            'support_message_path' => $outputDirectory.'/support_message.txt',
        ];

        return [
            'success' => true,
            'output_path' => $outputDirectory,
            'summary' => $summary,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectDiagnosticSummaries(string $root, int $connectionId, string $orderId): array
    {
        $base = storage_path('app/diagnostics/pia-ndc/'.$root.'/'.$connectionId);
        if (! is_dir($base)) {
            return [];
        }

        $summaries = [];
        foreach (glob($base.'/*/summary.json') ?: [] as $summaryPath) {
            if (! is_string($summaryPath)) {
                continue;
            }
            $decoded = json_decode(file_get_contents($summaryPath) ?: '', true);
            if (! is_array($decoded)) {
                continue;
            }
            if (trim((string) ($decoded['order_id'] ?? '')) !== $orderId) {
                continue;
            }
            $decoded['diagnostic_path'] = dirname($summaryPath);
            $requestPath = dirname($summaryPath).'/request.xml';
            if (is_file($requestPath)) {
                $decoded['request_shape'] = $this->shapeSummarizer->summarize(file_get_contents($requestPath) ?: '');
            }
            $summaries[] = $decoded;
        }

        usort($summaries, fn (array $a, array $b): int => strcmp(
            (string) ($b['correlation_id'] ?? ''),
            (string) ($a['correlation_id'] ?? ''),
        ));

        return $summaries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectProbeSummaries(int $connectionId, string $orderId): array
    {
        $base = storage_path('app/diagnostics/pia-ndc/order-cancel-probe/'.$connectionId);
        if (! is_dir($base)) {
            return [];
        }

        $summaries = [];
        foreach (glob($base.'/*/*/summary.json') ?: [] as $summaryPath) {
            if (! is_string($summaryPath)) {
                continue;
            }
            $decoded = json_decode(file_get_contents($summaryPath) ?: '', true);
            if (! is_array($decoded)) {
                continue;
            }
            if (trim((string) ($decoded['order_id'] ?? '')) !== $orderId) {
                continue;
            }
            $decoded['diagnostic_path'] = dirname($summaryPath);
            $decoded['probe_variant'] = true;
            $requestPath = dirname($summaryPath).'/request.xml';
            if (is_file($requestPath)) {
                $decoded['request_shape'] = $this->shapeSummarizer->summarize(file_get_contents($requestPath) ?: '');
            }
            $summaries[] = $decoded;
        }

        return $summaries;
    }

    /**
     * @param  list<array<string, mixed>>  $attempts
     * @return list<array<string, mixed>>
     */
    private function sanitizeCancelAttempts(array $attempts): array
    {
        $sanitized = [];
        foreach ($attempts as $attempt) {
            $legacyKnownBad = $this->isLegacyKnownBadDiagnostic($attempt);
            $ignoredForSupport = $legacyKnownBad
                || ($attempt['dry_run'] ?? false) === true
                || ($attempt['probe_variant'] ?? false) === true
                || ($attempt['supplier_called'] ?? false) !== true;

            $cancellationStatus = $attempt['cancellation_status'] ?? null;
            if ($legacyKnownBad) {
                $cancellationStatus = 'failed';
            }

            $sanitized[] = array_filter([
                'correlation_id' => $attempt['correlation_id'] ?? null,
                'shape' => $attempt['shape'] ?? null,
                'operation' => $attempt['operation'] ?? null,
                'soap_action' => $attempt['soap_action'] ?? $this->soapActionForOperation((string) ($attempt['operation'] ?? '')),
                'dry_run' => $attempt['dry_run'] ?? null,
                'supplier_called' => $attempt['supplier_called'] ?? null,
                'http_status' => $attempt['http_status'] ?? null,
                'success' => $attempt['success'] ?? null,
                'provider_error_code' => $attempt['provider_error_code'] ?? null,
                'provider_error_message' => $this->redactFreeText((string) ($attempt['provider_error_message'] ?? '')),
                'soap_fault_code' => $attempt['soap_fault_code'] ?? null,
                'soap_fault_string' => $attempt['soap_fault_string'] ?? null,
                'cancellation_status' => $cancellationStatus,
                'cancel_preview_status' => $attempt['cancel_preview_status'] ?? null,
                'order_status' => $attempt['order_status'] ?? null,
                'request_shape' => $attempt['request_shape'] ?? null,
                'diagnostic_path' => $attempt['diagnostic_path'] ?? null,
                'probe_variant' => $attempt['probe_variant'] ?? null,
                'legacy_known_bad_diagnostic' => $legacyKnownBad ? true : null,
                'ignored_for_support_message' => $ignoredForSupport ? true : null,
            ], fn ($value) => $value !== null && $value !== '');
        }

        return $sanitized;
    }

    /**
     * Pre-R11B bug: HTTP 500 / SOAP fault rows incorrectly marked cancellation_status=cancelled.
     *
     * @param  array<string, mixed>  $attempt
     */
    private function isLegacyKnownBadDiagnostic(array $attempt): bool
    {
        $httpStatus = (int) ($attempt['http_status'] ?? 0);
        $status = strtolower(trim((string) ($attempt['cancellation_status'] ?? '')));
        $hasFault = trim((string) ($attempt['soap_fault_string'] ?? '')) !== ''
            || trim((string) ($attempt['soap_fault_code'] ?? '')) !== '';

        return ($httpStatus >= 500 || $hasFault) && $status === 'cancelled';
    }

    /**
     * @param  list<array<string, mixed>>  $officialSamples
     * @return array{sample_files_physical_presence: string, builder_alignment: string}
     */
    private function sampleAlignmentMetadata(array $officialSamples): array
    {
        $serverPresence = $this->sampleFilesPhysicalPresence();

        return [
            'sample_files_physical_presence' => $serverPresence,
            'builder_alignment' => 'implemented_from_official_zip',
            'official_samples_on_server' => array_values(array_filter(
                $officialSamples,
                fn ($row) => is_array($row) && ($row['found_on_server'] ?? false) === true,
            )),
        ];
    }

    private function sampleFilesPhysicalPresence(): string
    {
        $serverDirs = [
            base_path('docs/providers/pia-ndc-samples/PIA-NDC'),
            base_path('docs/providers/pia-ndc-samples-temp/PIA-NDC'),
        ];

        foreach (['doOrderCancelPreview_OW_req.xml', 'doOrderCancelCommit_OW_req.xml'] as $filename) {
            $found = false;
            foreach ($serverDirs as $dir) {
                if (is_file($dir.'/'.$filename)) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                return 'not_deployed_on_server';
            }
        }

        return 'deployed_on_server';
    }

    /**
     * @param  list<array<string, mixed>>  $attempts
     * @return list<array<string, mixed>>
     */
    private function sanitizeRetrieveAttempts(array $attempts): array
    {
        $sanitized = [];
        foreach ($attempts as $attempt) {
            $sanitized[] = array_filter([
                'correlation_id' => $attempt['correlation_id'] ?? null,
                'http_status' => $attempt['http_status'] ?? null,
                'success' => $attempt['success'] ?? null,
                'order_status' => $attempt['order_status'] ?? null,
                'segment_count' => $attempt['segment_count'] ?? null,
                'ticket_numbers' => $attempt['ticket_numbers'] ?? [],
                'has_blocking_ticket_numbers' => $attempt['has_blocking_ticket_numbers'] ?? null,
                'payment_time_limit' => $attempt['payment_time_limit'] ?? null,
                'service_statuses' => $attempt['service_statuses'] ?? null,
                'diagnostic_path' => $attempt['diagnostic_path'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');
        }

        return $sanitized;
    }

    /**
     * @return array<string, mixed>
     */
    private function hititManualReferences(): array
    {
        return [
            'manual_document' => 'HITIT_CRANENDC_MANUAL_20.1',
            'section' => '3.3',
            'workflow' => 'DoOrderCancel',
            'preview_operation' => 'doOrderCancelPreview',
            'preview_message' => 'IATA_OrderReshopRQ with UpdateOrder/CancelOrder',
            'commit_operation' => 'doOrderCancelCommit',
            'commit_message' => 'IATA_OrderChangeRQ with ChangeOrder/CancelOrder',
            'version_history_notes' => [
                'ChangeOrder added to OrderCancelCommit',
                'UpdateOrder added to OrderCancelPreview',
            ],
            'manual_sample_filenames' => array_keys(PiaNdcXmlBuilder::HITIT_MANUAL_CANCEL_SAMPLE_FILES),
            'builder_shapes' => [
                'hitit_cancel_preview_sample_exact' => 'doOrderCancelPreview',
                'hitit_cancel_preview_sample_exact_with_contact_info' => 'doOrderCancelPreview',
                'hitit_cancel_preview_sample_exact_with_orderref_owner_attr' => 'doOrderCancelPreview',
                'hitit_cancel_commit_sample_exact' => 'doOrderCancelCommit',
                'hitit_manual_view_only_exact' => 'doOrderCancelPreview',
                'hitit_manual_commit_exact' => 'doOrderCancelCommit',
            ],
            'legacy_shapes' => PiaNdcXmlBuilder::CANCEL_LEGACY_PROBE_SHAPES,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchHititManualSampleFiles(): array
    {
        $results = [];
        foreach (PiaNdcXmlBuilder::HITIT_MANUAL_CANCEL_SAMPLE_FILES as $manualFilename => $repoEquivalent) {
            $manualPaths = [
                base_path($manualFilename),
                base_path('docs/providers/pia-ndc-samples/PIA-NDC/'.$manualFilename),
                base_path('docs/providers/pia-ndc-samples-temp/PIA-NDC/'.$manualFilename),
            ];
            $manualFound = false;
            foreach ($manualPaths as $path) {
                if (is_file($path)) {
                    $manualFound = true;
                    break;
                }
            }

            $equivalentPath = base_path($repoEquivalent);
            $results[] = [
                'manual_filename' => $manualFilename,
                'manual_file_found' => $manualFound,
                'repo_equivalent' => $repoEquivalent,
                'repo_equivalent_found' => is_file($equivalentPath),
            ];
        }

        return $results;
    }

    /**
     * @return array<string, string>
     */
    private function hititOperationMap(): array
    {
        $operations = config('suppliers.pia_ndc.operations', []);
        if (! is_array($operations)) {
            return [];
        }

        $keys = [
            'cancel',
            'cancel_preview',
            'cancel_commit',
            'order_change',
        ];

        $map = [];
        foreach ($keys as $key) {
            if (! is_array($operations[$key] ?? null)) {
                continue;
            }
            $map[$key] = (string) ($operations[$key]['soap_action'] ?? $key);
        }

        return $map;
    }

    /**
     * @return list<array{file: string, message_type: ?string, operation_hint: ?string}>
     */
    private function localHititSampleInventory(): array
    {
        $fixtureDir = base_path('tests/Fixtures/pia-ndc');
        if (! is_dir($fixtureDir)) {
            return [];
        }

        $patterns = [
            'doOrderCancel',
            'doOrderChange',
            'OrderCancel',
            'OrderReshop',
            'Cancel',
        ];

        $inventory = [];
        foreach (glob($fixtureDir.'/*.xml') ?: [] as $path) {
            if (! is_string($path)) {
                continue;
            }
            $basename = basename($path);
            $matched = false;
            foreach ($patterns as $pattern) {
                if (stripos($basename, $pattern) !== false) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                continue;
            }
            $xml = file_get_contents($path) ?: '';
            $shape = $this->shapeSummarizer->summarize($xml);
            $inventory[] = [
                'file' => 'tests/Fixtures/pia-ndc/'.$basename,
                'message_type' => $shape['message_type'],
                'operation_hint' => $this->operationHintFromFilename($basename),
            ];
        }

        sort($inventory);

        return $inventory;
    }

    /**
     * @return list<array{file: string, found: bool, zip_path: string, operation_hint: ?string}>
     */
    private function officialCancelSampleInventory(): array
    {
        $searchDirs = [
            base_path('tests/Fixtures/pia-ndc'),
            base_path('docs/providers/pia-ndc-samples/PIA-NDC'),
            base_path('docs/providers/pia-ndc-samples-temp/PIA-NDC'),
        ];

        $inventory = [];
        foreach (PiaNdcXmlBuilder::HITIT_OFFICIAL_CANCEL_SAMPLE_FILES as $filename) {
            $foundPath = null;
            $foundOnServer = false;
            foreach ($searchDirs as $dir) {
                $path = $dir.'/'.$filename;
                if (is_file($path)) {
                    $foundPath = $path;
                    if (str_contains(str_replace('\\', '/', $dir), 'docs/providers/pia-ndc-samples')) {
                        $foundOnServer = true;
                    }
                    break;
                }
            }
            $inventory[] = [
                'file' => $filename,
                'zip_path' => 'PIA-NDC/'.$filename,
                'found' => $foundPath !== null,
                'found_on_server' => $foundOnServer,
                'repo_path' => $foundPath !== null
                    ? str_replace(base_path().'/', '', str_replace('\\', '/', $foundPath))
                    : null,
                'operation_hint' => $this->operationHintFromFilename($filename),
            ];
        }

        return $inventory;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    private function dryRunShapePreview(array $config, string $orderId, string $ownerCode): array
    {
        $preview = [];
        foreach (PiaNdcXmlBuilder::CANCEL_PROBE_SHAPES as $shape) {
            try {
                $xml = $this->xmlBuilder->buildCancelDiagnosticRequest($config, $orderId, $ownerCode, $shape);
                $preview[] = [
                    'shape' => $shape,
                    'legacy' => $this->xmlBuilder->isLegacyCancelShape($shape),
                    'execute_allowed' => in_array($shape, PiaNdcXmlBuilder::CANCEL_EXECUTE_ALLOWED_SHAPES, true),
                    'default_operation' => $this->xmlBuilder->defaultCancelOperationForShape($shape),
                    'request_shape' => $this->shapeSummarizer->summarize($xml),
                ];
            } catch (\Throwable $exception) {
                $preview[] = [
                    'shape' => $shape,
                    'build_error' => $exception->getMessage(),
                ];
            }
        }

        return $preview;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function buildSupportMessage(array $report): string
    {
        $orderId = (string) ($report['order_id'] ?? '');
        $ownerCode = (string) ($report['owner_code'] ?? '');
        $agencyId = (string) ($report['agency_id'] ?? '');
        $endpointHost = (string) ($report['endpoint_host'] ?? '');

        $lines = [
            'Subject: PIA NDC 20.1 — Unable to cancel unticketed option PNR (HTTP 500 NullPointerException)',
            '',
            'Hello Hitit / PIA NDC support,',
            '',
            'We are integrating PIA NDC (Hitit Crane 20.1) and cannot cancel an unticketed option PNR created successfully via DoOrderCreate.',
            '',
            'Environment / agency:',
            '- Connection ID: '.($report['connection_id'] ?? ''),
            '- Agency ID: '.$agencyId,
            '- Owner code: '.$ownerCode,
            '- Endpoint host: '.$endpointHost,
            '- Order ID / PNR: '.$orderId,
            '',
            'Order state (latest retrieve):',
        ];

        $latestRetrieve = $report['retrieve_attempts'][0] ?? null;
        if (is_array($latestRetrieve)) {
            $lines[] = '- HTTP status: '.($latestRetrieve['http_status'] ?? 'n/a');
            $lines[] = '- Order status: '.($latestRetrieve['order_status'] ?? 'blank/unknown');
            $lines[] = '- Segment count: '.($latestRetrieve['segment_count'] ?? 'n/a');
            $lines[] = '- Ticket numbers: '.json_encode($latestRetrieve['ticket_numbers'] ?? []);
            $lines[] = '- Payment time limit: '.($latestRetrieve['payment_time_limit'] ?? 'n/a');
        } else {
            $lines[] = '- No retrieve diagnostic summaries found locally for this order.';
        }

        $lines[] = '';
        $failedAttempts = $this->failedAttemptsForSupport($report['cancel_attempts'] ?? []);
        if ($failedAttempts !== []) {
            $lines[] = 'Failed cancel attempts (supplier-called live executes with SOAP fault java.lang.NullPointerException, HTTP 500):';
            foreach ($failedAttempts as $attempt) {
                if (! is_array($attempt)) {
                    continue;
                }
                $statusLabel = $this->supportStatusLabel($attempt);
                $lines[] = sprintf(
                    '- shape=%s operation=%s soap_action=%s http=%s fault=%s %s',
                    (string) ($attempt['shape'] ?? 'n/a'),
                    (string) ($attempt['operation'] ?? 'n/a'),
                    (string) ($attempt['soap_action'] ?? 'n/a'),
                    (string) ($attempt['http_status'] ?? 'n/a'),
                    (string) ($attempt['soap_fault_string'] ?? 'n/a'),
                    $statusLabel,
                );
                if (is_array($attempt['request_shape'] ?? null)) {
                    $lines[] = '  request: '.($attempt['request_shape']['message_type'] ?? 'unknown')
                        .' paths='.json_encode($attempt['request_shape']['request_paths'] ?? []);
                }
            }
        } else {
            $lines[] = 'Failed cancel attempts: none recorded with supplier_called=true for this order.';
        }

        $lines[] = '';
        $lines[] = 'R11F preview variants (view-only; no doOrderCancelCommit attempted):';
        $previewVariantShapes = [
            'hitit_cancel_preview_sample_exact' => 'canonical preview (official sample alignment)',
            'hitit_cancel_preview_sample_exact_with_contact_info' => 'Party ContactInfo / EmailAddress / EmailAddressText',
            'hitit_cancel_preview_sample_exact_with_orderref_owner_attr' => 'ContactInfo + OrderRefID OwnerCode attribute',
        ];
        foreach ($previewVariantShapes as $variantShape => $variantLabel) {
            $attempt = $this->latestSupplierCalledAttemptForShape($report['cancel_attempts'] ?? [], $variantShape);
            if (is_array($attempt)) {
                $lines[] = sprintf(
                    '- %s (%s): supplier_called=true http=%s fault=%s %s',
                    $variantShape,
                    $variantLabel,
                    (string) ($attempt['http_status'] ?? 'n/a'),
                    (string) ($attempt['soap_fault_string'] ?? 'n/a'),
                    $this->supportStatusLabel($attempt),
                );
            } else {
                $lines[] = '- '.$variantShape.' ('.$variantLabel.'): not yet tested live (supplier_called=false)';
            }
        }
        $lines[] = '- Canonical preview (hitit_cancel_preview_sample_exact) failed in R11E when supplier_called=true (HTTP 500 SOAP java.lang.NullPointerException).';
        $lines[] = '- All listed preview attempts use doOrderCancelPreview only; doOrderCancelCommit was not attempted.';

        $lines[] = '';
        $lines[] = 'Official Hitit sample alignment (PIA_NDC_20.1_SCHEMA_UPGRADE_NEW SAMPLES.zip):';
        $samplePresence = (string) (($report['sample_alignment']['sample_files_physical_presence'] ?? '') ?: '');
        $builderAlignment = (string) (($report['sample_alignment']['builder_alignment'] ?? '') ?: '');
        if ($samplePresence === 'not_deployed_on_server') {
            $lines[] = 'Our current dry-run builders are aligned to the official ZIP samples doOrderCancelPreview_OW_req.xml and doOrderCancelCommit_OW_req.xml, but the sample XML files themselves are not stored on the production server.';
            $lines[] = '- sample_files_physical_presence=not_deployed_on_server';
            $lines[] = '- builder_alignment='.$builderAlignment;
        } else {
            $lines[] = '- Dry-run shapes hitit_cancel_preview_sample_exact and hitit_cancel_commit_sample_exact mirror:';
            $lines[] = '  - PIA-NDC/doOrderCancelPreview_OW_req.xml (doOrderCancelPreview / UpdateOrder/CancelOrder)';
            $lines[] = '  - PIA-NDC/doOrderCancelCommit_OW_req.xml (doOrderCancelCommit / ChangeOrder/CancelOrder)';
            foreach ($report['official_cancel_sample_files'] ?? [] as $sample) {
                if (! is_array($sample)) {
                    continue;
                }
                $lines[] = '- '.(string) ($sample['file'] ?? '').' — '.(($sample['found_on_server'] ?? false) ? 'FOUND on server' : 'NOT on server')
                    .(is_string($sample['repo_path'] ?? null) ? ' (repo: '.$sample['repo_path'].')' : '');
            }
            $lines[] = '- builder_alignment='.$builderAlignment;
        }
        $lines[] = '';
        $lines[] = 'Hitit manual (HITIT_CRANENDC_20.1) references:';
        $lines[] = '- Section 3.3 confirms cancellation/refund is handled by DoOrderCancel.';
        $lines[] = '- Preview/view-only step: doOrderCancelPreview (IATA_OrderReshopRQ + UpdateOrder/CancelOrder).';
        $lines[] = '- Final cancel/refund step: doOrderCancelCommit (IATA_OrderChangeRQ + ChangeOrder/CancelOrder).';
        $lines[] = '- Version history: ChangeOrder added to OrderCancelCommit; UpdateOrder added to OrderCancelPreview.';
        $lines[] = '';
        $lines[] = 'Manual sample filenames referenced in documentation:';
        foreach ($report['hitit_manual_sample_file_search'] ?? [] as $sample) {
            if (! is_array($sample)) {
                continue;
            }
            $manualName = (string) ($sample['manual_filename'] ?? '');
            $found = ($sample['manual_file_found'] ?? false) === true ? 'FOUND' : 'NOT FOUND';
            $equiv = (string) ($sample['repo_equivalent'] ?? '');
            $equivFound = ($sample['repo_equivalent_found'] ?? false) === true ? 'yes' : 'no';
            $lines[] = '- '.$manualName.' — '.$found.' (repo equivalent: '.$equiv.', present='.$equivFound.')';
        }
        $missingManual = array_values(array_filter(
            is_array($report['hitit_manual_sample_file_search'] ?? null) ? $report['hitit_manual_sample_file_search'] : [],
            fn ($row) => is_array($row) && ($row['manual_file_found'] ?? false) !== true,
        ));
        if ($missingManual !== []) {
            $lines[] = '';
            $lines[] = 'Request to Hitit/PIA: please provide the official manual sample files:';
            foreach ($missingManual as $row) {
                $lines[] = '- '.(string) ($row['manual_filename'] ?? '');
            }
            $lines[] = 'Our integration aligned dry-run builders to doOrderCancelPreview_OW_req.xml and doOrderCancelCommit_OW_req.xml (shapes hitit_cancel_preview_sample_exact / hitit_cancel_commit_sample_exact).';
        }

        $lines[] = '';
        $lines[] = 'Questions:';
        $lines[] = '1. Please confirm doOrderCancelPreview then doOrderCancelCommit is the correct workflow for an unticketed option PNR (DoOrderCreate, no payment).';
        $lines[] = '2. What is the exact required request body when OrderID equals the PNR/locator (e.g. '.$orderId.')?';
        $lines[] = '3. Is OrderID + OwnerCode sufficient, or is a token/reference from OrderRetrieve required?';
        $lines[] = '4. Why do requests aligned to manual samples still return java.lang.NullPointerException (HTTP 500)?';
        $lines[] = '5. Is cancellation enabled for our production credentials (agency '.$agencyId.', host '.$endpointHost.')?';
        $lines[] = '';
        $lines[] = 'We followed manual §3.3 structure (UpdateOrder preview, ChangeOrder commit) but all attempts failed with SOAP Fault java.lang.NullPointerException.';
        $lines[] = 'Please provide working DoOrderCancel_VIEW_ONLY_req and DoOrderCancel_COMMIT_req samples for our agency profile if different from the published OW examples.';
        $lines[] = '';
        $lines[] = 'Thank you.';

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param  list<array<string, mixed>>  $cancelAttempts
     * @return ?array<string, mixed>
     */
    private function latestSupplierCalledAttemptForShape(array $cancelAttempts, string $shape): ?array
    {
        foreach ($cancelAttempts as $attempt) {
            if (! is_array($attempt)) {
                continue;
            }
            if (($attempt['supplier_called'] ?? false) !== true) {
                continue;
            }
            if (($attempt['dry_run'] ?? false) === true) {
                continue;
            }
            if (($attempt['probe_variant'] ?? false) === true) {
                continue;
            }
            if (trim((string) ($attempt['shape'] ?? '')) !== $shape) {
                continue;
            }

            return $attempt;
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $cancelAttempts
     * @return list<array<string, mixed>>
     */
    private function failedAttemptsForSupport(array $cancelAttempts): array
    {
        return array_values(array_filter($cancelAttempts, function (mixed $attempt): bool {
            if (! is_array($attempt)) {
                return false;
            }
            if (($attempt['ignored_for_support_message'] ?? false) === true) {
                return false;
            }
            if (($attempt['supplier_called'] ?? false) !== true) {
                return false;
            }
            if (($attempt['dry_run'] ?? false) === true) {
                return false;
            }
            if (($attempt['probe_variant'] ?? false) === true) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param  array<string, mixed>  $attempt
     */
    private function supportStatusLabel(array $attempt): string
    {
        if (isset($attempt['cancel_preview_status']) && $attempt['cancel_preview_status'] !== '') {
            return 'cancel_preview_status='.(string) $attempt['cancel_preview_status'];
        }

        $http = (int) ($attempt['http_status'] ?? 0);
        $status = strtolower(trim((string) ($attempt['cancellation_status'] ?? '')));
        $hasFault = trim((string) ($attempt['soap_fault_string'] ?? '')) !== '';

        if (($http >= 500 || $hasFault) && $status === 'cancelled') {
            return 'cancellation_status=failed (legacy misread; ignored)';
        }

        if ($status !== '') {
            return 'cancellation_status='.$status;
        }

        return 'cancellation_status=n/a';
    }

    private function soapActionForOperation(string $operationKey): ?string
    {
        if ($operationKey === '') {
            return null;
        }

        return (string) config('suppliers.pia_ndc.operations.'.$operationKey.'.soap_action', $operationKey);
    }

    private function operationHintFromFilename(string $basename): ?string
    {
        return match (true) {
            str_contains($basename, 'doOrderCancelCommit') => 'doOrderCancelCommit',
            str_contains($basename, 'doOrderCancelPreview') => 'doOrderCancelPreview',
            str_contains($basename, 'doOrderChange') => 'doOrderChange',
            default => null,
        };
    }

    private function redactFreeText(string $text): string
    {
        $redacted = preg_replace(
            '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i',
            '[email]',
            $text,
        );

        return is_string($redacted) ? $redacted : $text;
    }
}
