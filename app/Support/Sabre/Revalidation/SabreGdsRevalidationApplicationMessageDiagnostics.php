<?php

namespace App\Support\Sabre\Revalidation;

use App\Support\Suppliers\SabreNdcGroupedItineraryMessageExtractor;

/**
 * Classifies Sabre BFM revalidation HTTP 200 application messages without exposing raw bodies or transaction IDs.
 */
final class SabreGdsRevalidationApplicationMessageDiagnostics
{
    /** @var list<string> */
    private const BLOCKING_WARNING_FRAGMENTS = [
        'NO COMBINABLE FARES',
        'NO FARES',
        'RBD',
        'CLASS USED',
        'ERROR DURING PROCESSING',
        'UNABLE TO PROCESS',
        'NOT AVAILABLE',
    ];

    /** @var list<string> */
    private const INFORMATIONAL_SEVERITIES = [
        'INFO',
        'INFORMATION',
        'NOTICE',
        'SUCCESS',
        'COMPLETE',
        'OK',
    ];

    /** @var list<string> */
    private const BLOCKING_ERROR_SEVERITIES = [
        'ERROR',
        'FATAL',
        'FAIL',
        'FAILED',
    ];

    public function __construct(
        private readonly SabreNdcGroupedItineraryMessageExtractor $messageExtractor,
    ) {}

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    public function analyze(?array $json): array
    {
        if (! is_array($json) || $json === []) {
            return $this->emptyDiagnostics();
        }

        $rows = $this->collectClassifiedRows($json);
        $blockingErrors = [];
        $blockingWarnings = [];
        $informationalWarnings = [];
        $statisticsRows = [];
        $categories = [];
        $codes = [];
        $severities = [];
        $locations = [];

        foreach ($rows as $row) {
            $classification = (string) ($row['classification'] ?? 'informational');
            $category = (string) ($row['category'] ?? 'message');
            $location = (string) ($row['location'] ?? 'unknown');
            $code = trim((string) ($row['code'] ?? ''));
            $severity = strtoupper(trim((string) ($row['severity'] ?? '')));

            $categories[] = $category;
            $locations[] = $location;
            if ($code !== '' && ! $this->looksSensitiveCode($code)) {
                $codes[] = $code;
            }
            if ($severity !== '') {
                $severities[] = $severity;
            }

            match ($classification) {
                'blocking_error' => $blockingErrors[] = $row,
                'blocking_warning' => $blockingWarnings[] = $row,
                'statistics_diagnostic' => $statisticsRows[] = $row,
                default => $informationalWarnings[] = $row,
            };
        }

        $gir = is_array($json['groupedItineraryResponse'] ?? null) ? $json['groupedItineraryResponse'] : [];
        $statisticsPresent = is_array($gir['statistics'] ?? null) && $gir['statistics'] !== [];
        $responseMessagesPresent = $rows !== [];
        $successIndicator = $this->resolveSuccessIndicator($json, $gir, $blockingErrors, $blockingWarnings);

        $payload = array_filter([
            'application_error_count' => count($blockingErrors),
            'application_warning_count' => count($blockingWarnings) + count($informationalWarnings),
            'application_message_categories' => array_values(array_unique($categories)),
            'application_message_codes' => array_slice(array_values(array_unique($codes)), 0, 12),
            'application_message_severity_types' => array_values(array_unique($severities)),
            'blocking_application_error_present' => $blockingErrors !== [],
            'blocking_application_warning_present' => $blockingWarnings !== [],
            'informational_warning_present' => $informationalWarnings !== [] || $statisticsRows !== [],
            'application_errors_present' => $blockingErrors !== [] || $blockingWarnings !== [] || $informationalWarnings !== [],
            'application_warnings_present' => $blockingWarnings !== [] || $informationalWarnings !== [] || $statisticsRows !== [],
            'response_statistics_present' => $statisticsPresent,
            'response_messages_present' => $responseMessagesPresent,
            'response_message_locations' => array_values(array_unique($locations)),
            'supplier_response_success_indicator_present' => $successIndicator['present'],
            'supplier_response_success_indicator_state' => $successIndicator['state'],
            'statistics_diagnostic_count' => count($statisticsRows),
            'informational_warning_count' => count($informationalWarnings),
        ], fn ($value, $key) => $this->retainDiagnosticValue($value, (string) $key), ARRAY_FILTER_USE_BOTH);

        return $payload;
    }

    private function retainDiagnosticValue(mixed $value, string $key): bool
    {
        if (in_array($key, [
            'blocking_application_error_present',
            'blocking_application_warning_present',
            'informational_warning_present',
            'application_errors_present',
            'application_warnings_present',
            'response_statistics_present',
            'response_messages_present',
            'supplier_response_success_indicator_present',
        ], true)) {
            return true;
        }

        return $value !== null && $value !== [] && $value !== false;
    }

    /**
     * @param  array<string, mixed>  $diagnostics
     */
    public function hasBlockingMessages(array $diagnostics): bool
    {
        return ($diagnostics['blocking_application_error_present'] ?? false) === true
            || ($diagnostics['blocking_application_warning_present'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $diagnostics
     * @return array<string, mixed>
     */
    public function toErrorDigest(array $diagnostics): array
    {
        $codes = is_array($diagnostics['application_message_codes'] ?? null)
            ? $diagnostics['application_message_codes']
            : [];

        $failureClass = 'application_warning';
        if (($diagnostics['blocking_application_error_present'] ?? false) === true) {
            $failureClass = 'application_error';
        } elseif (($diagnostics['blocking_application_warning_present'] ?? false) === true) {
            $failureClass = 'application_warning';
        } elseif (($diagnostics['informational_warning_present'] ?? false) === true) {
            $failureClass = 'application_informational';
        }

        return array_filter([
            'response_error_codes' => $codes,
            'response_error_messages' => [],
            'revalidation_failure_class' => $failureClass,
            'application_message_diagnostics' => $this->safeDigestSlice($diagnostics),
        ], static fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $diagnostics
     * @return array<string, mixed>
     */
    public function safeDigestSlice(array $diagnostics): array
    {
        return array_filter([
            'application_error_count' => $diagnostics['application_error_count'] ?? 0,
            'application_warning_count' => $diagnostics['application_warning_count'] ?? 0,
            'application_message_categories' => $diagnostics['application_message_categories'] ?? [],
            'application_message_codes' => $diagnostics['application_message_codes'] ?? [],
            'application_message_severity_types' => $diagnostics['application_message_severity_types'] ?? [],
            'blocking_application_error_present' => ($diagnostics['blocking_application_error_present'] ?? false) === true,
            'blocking_application_warning_present' => ($diagnostics['blocking_application_warning_present'] ?? false) === true,
            'informational_warning_present' => ($diagnostics['informational_warning_present'] ?? false) === true,
            'response_statistics_present' => ($diagnostics['response_statistics_present'] ?? false) === true,
            'response_messages_present' => ($diagnostics['response_messages_present'] ?? false) === true,
            'response_message_locations' => $diagnostics['response_message_locations'] ?? [],
            'supplier_response_success_indicator_present' => ($diagnostics['supplier_response_success_indicator_present'] ?? false) === true,
            'supplier_response_success_indicator_state' => $diagnostics['supplier_response_success_indicator_state'] ?? null,
        ], static fn ($value, $key) => in_array($key, [
            'blocking_application_error_present',
            'blocking_application_warning_present',
            'informational_warning_present',
            'response_statistics_present',
            'response_messages_present',
            'supplier_response_success_indicator_present',
        ], true) || ($value !== null && $value !== [] && $value !== false), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDiagnostics(): array
    {
        return [
            'application_error_count' => 0,
            'application_warning_count' => 0,
            'blocking_application_error_present' => false,
            'blocking_application_warning_present' => false,
            'informational_warning_present' => false,
            'application_errors_present' => false,
            'application_warnings_present' => false,
            'response_statistics_present' => false,
            'response_messages_present' => false,
            'supplier_response_success_indicator_present' => false,
            'supplier_response_success_indicator_state' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<array<string, mixed>>
     */
    private function collectClassifiedRows(array $json): array
    {
        $rows = [];
        $sources = [
            ['path' => 'warnings', 'category' => 'warning', 'location' => 'root.warnings'],
            ['path' => 'messages', 'category' => 'message', 'location' => 'root.messages'],
            ['path' => 'errors', 'category' => 'error', 'location' => 'root.errors'],
            ['path' => 'error.warnings', 'category' => 'warning', 'location' => 'error.warnings'],
            ['path' => 'error.messages', 'category' => 'message', 'location' => 'error.messages'],
            ['path' => 'error.errors', 'category' => 'error', 'location' => 'error.errors'],
            ['path' => 'result.warnings', 'category' => 'warning', 'location' => 'result.warnings'],
            ['path' => 'result.messages', 'category' => 'message', 'location' => 'result.messages'],
            ['path' => 'result.errors', 'category' => 'error', 'location' => 'result.errors'],
            ['path' => 'data.warnings', 'category' => 'warning', 'location' => 'data.warnings'],
            ['path' => 'data.messages', 'category' => 'message', 'location' => 'data.messages'],
            ['path' => 'data.errors', 'category' => 'error', 'location' => 'data.errors'],
            ['path' => 'groupedItineraryResponse.messages', 'category' => 'message', 'location' => 'gir.messages'],
            ['path' => 'groupedItineraryResponse.statistics.messages', 'category' => 'statistics', 'location' => 'gir.statistics.messages'],
            ['path' => 'groupedItineraryResponse.statistics.warnings', 'category' => 'statistics', 'location' => 'gir.statistics.warnings'],
            ['path' => 'groupedItineraryResponse.statistics.errors', 'category' => 'statistics', 'location' => 'gir.statistics.errors'],
            ['path' => 'groupedItineraryResponse.statistics.notices', 'category' => 'statistics', 'location' => 'gir.statistics.notices'],
            ['path' => 'groupedItineraryResponse.ApplicationResults', 'category' => 'application_results', 'location' => 'gir.ApplicationResults'],
            ['path' => 'ApplicationResults', 'category' => 'application_results', 'location' => 'root.ApplicationResults'],
        ];

        foreach ($sources as $source) {
            $node = data_get($json, $source['path']);
            $this->appendRowsFromNode($rows, $node, $source['category'], $source['location']);
        }

        $extracted = $this->messageExtractor->extract($json);
        foreach ($extracted['message_rows'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rows[] = $this->classifyRow([
                'category' => 'gir_message',
                'location' => 'gir.extracted',
                'code' => (string) ($row['code'] ?? ''),
                'severity' => strtoupper((string) ($row['severity'] ?? $row['type'] ?? '')),
                'type' => strtoupper((string) ($row['type'] ?? '')),
            ]);
        }

        return $this->dedupeRows($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function appendRowsFromNode(array &$rows, mixed $node, string $category, string $location): void
    {
        if ($node === null) {
            return;
        }
        if (is_string($node) && trim($node) !== '') {
            $rows[] = $this->classifyRow([
                'category' => $category,
                'location' => $location,
                'code' => '',
                'severity' => 'INFO',
                'type' => strtoupper($category),
            ]);

            return;
        }
        if (! is_array($node)) {
            return;
        }
        if ($this->isApplicationResultsNode($node)) {
            foreach (['Error' => 'ERROR', 'Errors' => 'ERROR', 'Warning' => 'WARNING', 'Warnings' => 'WARNING', 'Notice' => 'INFO', 'Notices' => 'INFO'] as $key => $severity) {
                $child = $node[$key] ?? null;
                if ($child === null) {
                    continue;
                }
                $list = is_array($child) && array_is_list($child) ? $child : [$child];
                foreach ($list as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $rows[] = $this->classifyRow([
                        'category' => $category,
                        'location' => $location.'.'.$key,
                        'code' => (string) ($item['code'] ?? $item['ErrorCode'] ?? ''),
                        'severity' => $severity,
                        'type' => rtrim($key, 's'),
                    ]);
                }
            }

            return;
        }
        if (array_is_list($node)) {
            foreach (array_slice($node, 0, 24) as $item) {
                $this->appendRowsFromNode($rows, $item, $category, $location);
            }

            return;
        }
        $rows[] = $this->classifyRow([
            'category' => $category,
            'location' => $location,
            'code' => (string) ($node['code'] ?? $node['errorCode'] ?? $node['type'] ?? $node['Number'] ?? ''),
            'severity' => strtoupper((string) ($node['severity'] ?? $node['type'] ?? $node['status'] ?? '')),
            'type' => strtoupper((string) ($node['type'] ?? $category)),
            'text' => strtoupper((string) ($node['message'] ?? $node['detail'] ?? $node['title'] ?? $node['text'] ?? $node['ShortText'] ?? '')),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function classifyRow(array $row): array
    {
        $category = (string) ($row['category'] ?? 'message');
        $location = (string) ($row['location'] ?? 'unknown');
        $code = trim((string) ($row['code'] ?? ''));
        $severity = strtoupper(trim((string) ($row['severity'] ?? '')));
        $type = strtoupper(trim((string) ($row['type'] ?? '')));
        $text = strtoupper(trim((string) ($row['text'] ?? '')));

        if ($this->messageExtractor->looksLikeTransactionId($code)) {
            $code = '';
        }

        $classification = 'informational';
        if ($category === 'statistics' || str_starts_with($location, 'gir.statistics.')) {
            $classification = in_array($severity, self::BLOCKING_ERROR_SEVERITIES, true) ? 'blocking_error' : 'statistics_diagnostic';
        } elseif ($category === 'error' || in_array($severity, self::BLOCKING_ERROR_SEVERITIES, true) || $type === 'ERROR') {
            $classification = 'blocking_error';
        } elseif ($severity === 'WARNING' || $type === 'WARNING') {
            $classification = $this->isBlockingWarningText($text) || $this->isBlockingWarningCode($code)
                ? 'blocking_warning'
                : 'informational';
        } elseif (in_array($severity, self::INFORMATIONAL_SEVERITIES, true) || in_array($type, self::INFORMATIONAL_SEVERITIES, true)) {
            $classification = 'informational';
        }

        return [
            'classification' => $classification,
            'category' => $category,
            'location' => $location,
            'code' => $code,
            'severity' => $severity,
            'type' => $type,
        ];
    }

    private function isBlockingWarningText(string $text): bool
    {
        if ($text === '') {
            return false;
        }
        foreach (self::BLOCKING_WARNING_FRAGMENTS as $fragment) {
            if (str_contains($text, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function isBlockingWarningCode(string $code): bool
    {
        $code = strtoupper(trim($code));

        return $code !== '' && str_starts_with($code, 'MIP') && $code !== 'MIP5053';
    }

    private function looksSensitiveCode(string $code): bool
    {
        return $this->messageExtractor->looksLikeTransactionId($code);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function isApplicationResultsNode(array $node): bool
    {
        foreach (['Error', 'Errors', 'Warning', 'Warnings', 'Notice', 'Notices', 'status'] as $key) {
            if (array_key_exists($key, $node)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function dedupeRows(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $key = implode('|', [
                (string) ($row['classification'] ?? ''),
                (string) ($row['category'] ?? ''),
                (string) ($row['location'] ?? ''),
                (string) ($row['code'] ?? ''),
                (string) ($row['severity'] ?? ''),
            ]);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  array<string, mixed>  $gir
     * @param  list<array<string, mixed>>  $blockingErrors
     * @param  list<array<string, mixed>>  $blockingWarnings
     * @return array{present: bool, state: ?string}
     */
    private function resolveSuccessIndicator(array $json, array $gir, array $blockingErrors, array $blockingWarnings): array
    {
        $status = strtoupper(trim((string) (
            data_get($gir, 'ApplicationResults.status')
            ?? data_get($json, 'ApplicationResults.status')
            ?? data_get($gir, 'statistics.status')
            ?? ''
        )));

        if ($status !== '') {
            return ['present' => true, 'state' => strtolower($status)];
        }

        if (is_array($gir['itineraryGroups'] ?? null) && $gir['itineraryGroups'] !== []) {
            return [
                'present' => true,
                'state' => ($blockingErrors === [] && $blockingWarnings === []) ? 'itinerary_groups_present' : 'itinerary_groups_with_messages',
            ];
        }

        return ['present' => false, 'state' => null];
    }
}
