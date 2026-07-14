<?php

namespace App\Support\Suppliers;

/**
 * Safe Sabre grouped-itinerary message extraction — separates transaction IDs from message codes.
 */
final class SabreNdcGroupedItineraryMessageExtractor
{
    private const MESSAGE_MAX = 300;

    private const ROW_MAX = 12;

    /**
     * @param  array<string, mixed>  $response
     * @return array{
     *     message_rows: list<array<string, string>>,
     *     message_count: int,
     *     message_code: ?string,
     *     message_type: ?string,
     *     message_severity: ?string,
     *     message_text: ?string,
     *     sabre_transaction_id: ?string
     * }
     */
    public function extract(array $response): array
    {
        $rows = [];
        $gir = is_array($response['groupedItineraryResponse'] ?? null)
            ? $response['groupedItineraryResponse']
            : [];

        $rows = array_merge(
            $rows,
            $this->rowsFromMessages($gir['messages'] ?? null),
            $this->rowsFromStatistics($gir['statistics'] ?? null),
            $this->rowsFromApplicationResults($gir['ApplicationResults'] ?? null),
            $this->rowsFromApplicationResults($response['ApplicationResults'] ?? null),
            $this->rowsFromApplicationResults(data_get($response, 'OTA_AirLowFareSearchRS.ApplicationResults')),
        );

        $rows = $this->dedupeRows($rows);
        $rows = array_slice($rows, 0, self::ROW_MAX);

        $transactionId = $this->resolveTransactionId($response, $gir, $rows);
        $transactionId ??= $this->resolveTransactionIdFromRawMessages($gir);
        $primary = $this->resolvePrimaryFields($gir, $rows, $transactionId);

        return [
            'message_rows' => $rows,
            'message_count' => count($rows),
            'message_code' => $primary['code'] ?? null,
            'message_type' => $primary['type'] ?? null,
            'message_severity' => $primary['severity'] ?? null,
            'message_text' => $primary['message'] ?? null,
            'sabre_transaction_id' => $primary['transaction_id'] ?? $transactionId,
        ];
    }

    public function looksLikeNumericSabreTraceCode(string $value): bool
    {
        return preg_match('/^\d{3,8}$/', trim($value)) === 1;
    }

    public function looksLikeReadableMessageText(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if ($this->looksLikeNumericSabreTraceCode($value)) {
            return false;
        }

        if ($this->looksLikeTransactionId($value)) {
            return false;
        }

        return preg_match('/[A-Za-z]/', $value) === 1;
    }

    public function looksLikeTransactionId(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if (str_starts_with(strtolower($value), 'sabre_')) {
            return true;
        }

        if (str_contains($value, 'ISELL') || str_contains($value, 'GCC') || str_contains($value, 'GCB') || str_contains($value, 'GCA')) {
            return true;
        }

        return preg_match('/^GC[ABC]\d+-ISELL/i', $value) === 1
            || preg_match('/^[A-Z]{2,}\d+-[A-Z0-9\-]+$/i', $value) === 1;
    }

    public function looksLikeMessageCode(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || $this->looksLikeTransactionId($value)) {
            return false;
        }

        if (preg_match('/^\d{3,8}$/', $value) === 1) {
            return true;
        }

        return preg_match('/^[A-Z]{2,}(\.[A-Z0-9_]+)+$/', $value) === 1;
    }

    /**
     * @return list<array<string, string>>
     */
    private function rowsFromMessages(mixed $messages): array
    {
        if (! is_array($messages)) {
            return [];
        }

        $rows = [];
        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }
            $row = $this->rowFromLooseMessage($message);
            if ($row !== []) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, string>>
     */
    private function rowsFromStatistics(mixed $statistics): array
    {
        if (! is_array($statistics)) {
            return [];
        }

        $rows = [];
        foreach (['messages', 'warnings', 'errors', 'notices'] as $key) {
            $rows = array_merge($rows, $this->rowsFromMessages($statistics[$key] ?? null));
        }

        return $rows;
    }

    /**
     * @return list<array<string, string>>
     */
    private function rowsFromApplicationResults(mixed $applicationResults): array
    {
        if (! is_array($applicationResults) || $applicationResults === []) {
            return [];
        }

        $rows = [];
        foreach (['Error', 'Errors', 'Warning', 'Warnings', 'Notice', 'Notices'] as $key) {
            $node = $applicationResults[$key] ?? null;
            if ($node === null) {
                continue;
            }
            $list = is_array($node) && isset($node[0]) ? $node : [$node];
            foreach ($list as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $rows[] = array_filter([
                    'type' => strtolower(rtrim($key, 's')),
                    'severity' => strtolower(rtrim($key, 's')),
                    'code' => $this->normalizeCode((string) ($item['code'] ?? $item['ErrorCode'] ?? '')),
                    'message' => $this->truncate(trim((string) ($item['ShortText'] ?? $item['text'] ?? $item['message'] ?? $item['content'] ?? '')), self::MESSAGE_MAX),
                ], fn (string $v): bool => $v !== '');
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, string>
     */
    private function rowFromLooseMessage(array $message): array
    {
        $rawCode = trim((string) ($message['code'] ?? $message['messageCode'] ?? $message['MessageCode'] ?? ''));
        $rawText = trim((string) ($message['text'] ?? $message['message'] ?? $message['content'] ?? $message['ShortText'] ?? ''));
        $type = strtolower(trim((string) ($message['type'] ?? $message['severity'] ?? 'info')));
        $severity = strtolower(trim((string) ($message['severity'] ?? $message['type'] ?? 'info')));

        if ($this->looksLikeTransactionId($rawCode) && $this->looksLikeNumericSabreTraceCode($rawText)) {
            return array_filter([
                'type' => $type,
                'severity' => $severity,
                'code' => $rawText,
            ], fn (string $v): bool => $v !== '');
        }

        $text = $this->truncate($rawText, self::MESSAGE_MAX);
        $code = $this->normalizeCode($rawCode);

        if ($code === '' && $this->looksLikeNumericSabreTraceCode($text)) {
            $code = $text;
            $text = '';
        }

        if ($code !== '' && $this->looksLikeTransactionId($code)) {
            $code = '';
        }
        if ($text !== '' && ! $this->looksLikeReadableMessageText($text)) {
            if ($code === '' && $this->looksLikeNumericSabreTraceCode($text)) {
                $code = $text;
            }
            $text = '';
        }

        return array_filter([
            'type' => $type,
            'severity' => $severity,
            'code' => $code,
            'message' => $text,
        ], fn (string $v): bool => $v !== '');
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $gir
     * @param  list<array<string, string>>  $rows
     */
    private function resolveTransactionId(array $response, array $gir, array $rows): ?string
    {
        foreach ([
            data_get($response, 'transactionId'),
            data_get($gir, 'transactionId'),
            data_get($gir, 'statistics.transactionId'),
            data_get($response, 'requestId'),
            data_get($gir, 'requestId'),
        ] as $candidate) {
            if (is_scalar($candidate)) {
                $value = trim((string) $candidate);
                if ($value !== '' && $this->looksLikeTransactionId($value)) {
                    return $this->truncate($value, 64);
                }
            }
        }

        foreach ($rows as $row) {
            foreach ([$row['code'] ?? '', $row['message'] ?? ''] as $candidate) {
                $value = trim((string) $candidate);
                if ($value !== '' && $this->looksLikeTransactionId($value)) {
                    return $this->truncate($value, 64);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $gir
     * @param  list<array<string, string>>  $rows
     * @return array<string, string|null>
     */
    private function resolvePrimaryFields(array $gir, array $rows, ?string $transactionId): array
    {
        $messages = is_array($gir['messages'] ?? null) ? $gir['messages'] : [];
        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }
            $rawCode = trim((string) ($message['code'] ?? ''));
            $rawText = trim((string) ($message['text'] ?? ''));
            if ($this->looksLikeTransactionId($rawCode) && $this->looksLikeNumericSabreTraceCode($rawText)) {
                return [
                    'type' => strtolower(trim((string) ($message['type'] ?? 'info'))),
                    'severity' => strtolower(trim((string) ($message['severity'] ?? $message['type'] ?? 'info'))),
                    'code' => $rawText,
                    'message' => null,
                    'transaction_id' => $transactionId ?? $this->truncate($rawCode, 64),
                ];
            }
        }

        foreach ($rows as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            $message = trim((string) ($row['message'] ?? ''));
            if ($code !== '' || $message !== '') {
                return [
                    'type' => $row['type'] ?? null,
                    'severity' => $row['severity'] ?? null,
                    'code' => $code !== '' ? $code : null,
                    'message' => $this->looksLikeReadableMessageText($message) ? $message : null,
                    'transaction_id' => $transactionId,
                ];
            }
        }

        return [
            'type' => null,
            'severity' => null,
            'code' => null,
            'message' => null,
            'transaction_id' => $transactionId,
        ];
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array<string, string>
     */
    private function primaryMessage(array $rows): array
    {
        foreach ($rows as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            $message = trim((string) ($row['message'] ?? ''));
            if ($code !== '' || $message !== '') {
                return $row;
            }
        }

        return [];
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<array<string, string>>
     */
    private function dedupeRows(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $key = json_encode($row);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        return $out;
    }

    private function normalizeCode(string $code): string
    {
        $code = trim($code);
        if ($code === '' || $this->looksLikeTransactionId($code)) {
            return '';
        }

        return $this->looksLikeMessageCode($code) ? $code : $code;
    }

    /**
     * @param  array<string, mixed>  $gir
     */
    private function resolveTransactionIdFromRawMessages(array $gir): ?string
    {
        $messages = is_array($gir['messages'] ?? null) ? $gir['messages'] : [];
        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }
            $rawCode = trim((string) ($message['code'] ?? ''));
            if ($rawCode !== '' && $this->looksLikeTransactionId($rawCode)) {
                return $this->truncate($rawCode, 64);
            }
            foreach (['text', 'message', 'content'] as $key) {
                $value = trim((string) ($message[$key] ?? ''));
                if ($value !== '' && $this->looksLikeTransactionId($value)) {
                    return $this->truncate($value, 64);
                }
            }
        }

        return null;
    }

    private function truncate(string $value, int $max): string
    {
        return mb_strlen($value) <= $max ? $value : mb_substr($value, 0, $max);
    }
}
