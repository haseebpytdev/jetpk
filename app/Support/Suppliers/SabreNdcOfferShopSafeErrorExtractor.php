<?php

namespace App\Support\Suppliers;

/**
 * Safe Sabre NDC offer shop HTTP error extraction — no tokens, credentials, PCC, PII, or full bodies.
 */
final class SabreNdcOfferShopSafeErrorExtractor
{
    private const MESSAGE_MAX = 300;

    private const PATH_MAX = 12;

    private const KEY_MAX = 20;

    public function __construct(
        private readonly SabreNdcGroupedItineraryMessageExtractor $messageExtractor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function extract(int $httpStatus, mixed $decodedBody, ?string $rawBody = null, array $context = []): array
    {
        $json = is_array($decodedBody) ? $decodedBody : null;
        $nonJson = $json === null && is_string($rawBody) && trim($rawBody) !== '';

        $applicationStatus = $this->applicationResultsStatus($json);
        $girMessages = $json !== null ? $this->messageExtractor->extract($json) : [
            'message_rows' => [],
            'message_count' => 0,
            'message_code' => null,
            'message_type' => null,
            'message_severity' => null,
            'message_text' => null,
            'sabre_transaction_id' => null,
        ];
        $normalizedOfferCount = (int) ($context['normalized_offer_count'] ?? -1);
        $offerCountRaw = (int) ($context['offer_count_raw'] ?? -1);
        $zeroOfferSuccess = $httpStatus >= 200 && $httpStatus < 300
            && isset($json['groupedItineraryResponse'])
            && array_key_exists('normalized_offer_count', $context)
            && array_key_exists('offer_count_raw', $context)
            && $normalizedOfferCount === 0
            && $offerCountRaw === 0;

        $safeCode = $this->safeErrorCode($httpStatus, $json, $girMessages, $zeroOfferSuccess);
        $safeMessage = $this->safeErrorMessage($httpStatus, $json, $nonJson ? $rawBody : null, $girMessages, $zeroOfferSuccess);

        return array_merge($girMessages, [
            'http_status' => $httpStatus,
            'response_top_level_keys' => $this->topLevelKeys($json),
            'response_shape' => $this->detectResponseShape($json, $nonJson),
            'application_results_status' => $applicationStatus,
            'safe_error_family' => $this->safeErrorFamily($httpStatus, $json, $safeCode, $girMessages, $zeroOfferSuccess),
            'safe_error_code' => $safeCode,
            'safe_error_message' => $safeMessage,
            'validation_paths' => $this->validationPaths($json),
            'error_rows' => $this->errorRows($json),
            'warning_rows' => $this->warningRows($json),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return list<string>
     */
    private function topLevelKeys(?array $json): array
    {
        if ($json === null) {
            return [];
        }

        return array_slice(array_keys($json), 0, self::KEY_MAX);
    }

    private function detectResponseShape(?array $json, bool $nonJson): string
    {
        if ($nonJson) {
            return 'non_json';
        }
        if ($json === null || $json === []) {
            return 'empty';
        }
        if (isset($json['groupedItineraryResponse'])) {
            return 'grouped_itinerary';
        }
        if (isset($json['errors']) || isset($json['error']) || isset($json['errorCode'])) {
            return 'rest_error';
        }
        if (isset($json['message']) || isset($json['status'])) {
            return 'generic_http_error';
        }
        if ($this->applicationResultsStatus($json) !== null) {
            return 'application_results';
        }

        return 'unknown';
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function applicationResultsStatus(?array $json): ?string
    {
        if ($json === null) {
            return null;
        }

        foreach ([
            'OTA_AirLowFareSearchRS.ApplicationResults.status',
            'ApplicationResults.status',
            'groupedItineraryResponse.ApplicationResults.status',
        ] as $path) {
            $value = data_get($json, $path);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return strtoupper(trim((string) $value));
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function safeErrorFamily(
        int $httpStatus,
        ?array $json,
        ?string $safeCode,
        array $girMessages,
        bool $zeroOfferSuccess,
    ): string {
        if ($zeroOfferSuccess) {
            return 'sabre_ndc_zero_offers';
        }

        if ($httpStatus === 401 || $httpStatus === 403) {
            return 'auth_or_entitlement';
        }
        if ($httpStatus >= 500) {
            return 'supplier_server_error';
        }
        if ($this->validationPaths($json) !== []) {
            return 'request_validation';
        }
        if (is_string($safeCode) && (str_starts_with($safeCode, 'validation_') || str_contains($safeCode, 'VALIDATION'))) {
            return 'request_validation';
        }

        $messageCode = trim((string) ($girMessages['message_code'] ?? ''));
        if ($messageCode !== ''
            && $this->messageExtractor->looksLikeMessageCode($messageCode)
            && ! $this->messageExtractor->looksLikeTransactionId($messageCode)) {
            return 'sabre_message_'.$messageCode;
        }

        return 'http_'.$httpStatus;
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @param  array<string, mixed>  $girMessages
     */
    private function safeErrorCode(
        int $httpStatus,
        ?array $json,
        array $girMessages,
        bool $zeroOfferSuccess,
    ): ?string {
        if ($zeroOfferSuccess) {
            return 'ndc_zero_offers';
        }

        if ($json === null) {
            return $httpStatus > 0 ? 'http_'.$httpStatus : 'http_unknown';
        }

        $primaryCode = trim((string) ($girMessages['message_code'] ?? ''));
        if ($primaryCode !== ''
            && $this->messageExtractor->looksLikeMessageCode($primaryCode)
            && ($json['groupedItineraryResponse'] ?? null) !== null
            && $httpStatus >= 400) {
            return $primaryCode;
        }

        if (isset($json['errorCode']) && is_scalar($json['errorCode'])) {
            return 'sabre_'.trim((string) $json['errorCode']);
        }

        $errors = $json['errors'] ?? null;
        if (is_array($errors)) {
            foreach ($errors as $error) {
                if (! is_array($error)) {
                    continue;
                }
                $code = trim((string) ($error['code'] ?? $error['type'] ?? $error['category'] ?? ''));
                if ($code !== '') {
                    return 'sabre_'.$code;
                }
            }
        }

        $appRows = $this->collectApplicationResultRows($json, ['Error', 'Errors']);
        if ($appRows !== []) {
            $first = $appRows[0];
            $code = trim((string) ($first['code'] ?? ''));
            if ($code !== '') {
                return 'sabre_app_'.$code;
            }
        }

        if ($this->validationPaths($json) !== []) {
            return 'validation_failed';
        }

        return 'http_'.$httpStatus;
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @param  array<string, mixed>  $girMessages
     */
    private function safeErrorMessage(
        int $httpStatus,
        ?array $json,
        ?string $rawBody,
        array $girMessages,
        bool $zeroOfferSuccess,
    ): ?string {
        if ($zeroOfferSuccess) {
            $primaryText = trim((string) ($girMessages['message_text'] ?? ''));

            return $primaryText !== '' && $this->messageExtractor->looksLikeReadableMessageText($primaryText)
                ? $this->truncate($primaryText, self::MESSAGE_MAX)
                : null;
        }

        if ($json === null) {
            if ($rawBody !== null && trim($rawBody) !== '') {
                $snippet = preg_replace('/\s+/', ' ', trim($rawBody)) ?? trim($rawBody);

                return $this->truncate($snippet, self::MESSAGE_MAX);
            }

            return $httpStatus > 0
                ? 'Sabre NDC shop returned HTTP '.$httpStatus.' without a JSON body.'
                : 'Sabre NDC shop returned a non-JSON error response.';
        }

        $primaryText = trim((string) ($girMessages['message_text'] ?? ''));
        if ($primaryText !== '' && $this->messageExtractor->looksLikeReadableMessageText($primaryText)) {
            return $this->truncate($primaryText, self::MESSAGE_MAX);
        }

        if (isset($json['message']) && is_scalar($json['message'])) {
            return $this->truncate(trim((string) $json['message']), self::MESSAGE_MAX);
        }

        $errors = $json['errors'] ?? null;
        if (is_array($errors)) {
            foreach ($errors as $error) {
                if (! is_array($error)) {
                    continue;
                }
                foreach (['description', 'detail', 'title', 'message'] as $key) {
                    $text = trim((string) ($error[$key] ?? ''));
                    if ($text !== '') {
                        return $this->truncate($text, self::MESSAGE_MAX);
                    }
                }
            }
        }

        $appRows = $this->collectApplicationResultRows($json, ['Error', 'Errors']);
        if ($appRows !== []) {
            $text = trim((string) ($appRows[0]['message'] ?? ''));
            if ($text !== '') {
                return $this->truncate($text, self::MESSAGE_MAX);
            }
        }

        return 'Sabre NDC shop returned HTTP '.$httpStatus.'.';
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return list<string>
     */
    private function validationPaths(?array $json): array
    {
        if ($json === null) {
            return [];
        }

        $paths = [];

        $errors = $json['errors'] ?? null;
        if (is_array($errors)) {
            foreach ($errors as $error) {
                if (! is_array($error)) {
                    continue;
                }
                $path = trim((string) ($error['field'] ?? $error['path'] ?? $error['source']['pointer'] ?? ''));
                if ($path !== '') {
                    $paths[] = $this->truncate($path, 120);
                }
            }
        }

        $fieldErrors = $json['fieldErrors'] ?? $json['validationErrors'] ?? null;
        if (is_array($fieldErrors)) {
            foreach ($fieldErrors as $fieldError) {
                if (! is_array($fieldError)) {
                    continue;
                }
                $path = trim((string) ($fieldError['field'] ?? $fieldError['path'] ?? ''));
                if ($path !== '') {
                    $paths[] = $this->truncate($path, 120);
                }
            }
        }

        return array_values(array_unique(array_slice($paths, 0, self::PATH_MAX)));
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return list<array<string, string>>
     */
    private function errorRows(?array $json): array
    {
        if ($json === null) {
            return [];
        }

        $rows = [];

        $messages = data_get($json, 'groupedItineraryResponse.messages');
        if (is_array($messages)) {
            foreach ($messages as $message) {
                if (! is_array($message)) {
                    continue;
                }
                $type = strtolower(trim((string) ($message['type'] ?? '')));
                if ($type !== '' && $type !== 'error') {
                    continue;
                }
                $rows[] = $this->rowFromMessage($message);
            }
        }

        foreach ($this->collectApplicationResultRows($json, ['Error', 'Errors']) as $row) {
            $rows[] = $row;
        }

        return array_slice($rows, 0, self::PATH_MAX);
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return list<array<string, string>>
     */
    private function warningRows(?array $json): array
    {
        if ($json === null) {
            return [];
        }

        $rows = [];
        $messages = data_get($json, 'groupedItineraryResponse.messages');
        if (is_array($messages)) {
            foreach ($messages as $message) {
                if (! is_array($message)) {
                    continue;
                }
                if (strtolower(trim((string) ($message['type'] ?? ''))) !== 'warning') {
                    continue;
                }
                $rows[] = $this->rowFromMessage($message);
            }
        }

        foreach ($this->collectApplicationResultRows($json, ['Warning', 'Warnings']) as $row) {
            $rows[] = $row;
        }

        return array_slice($rows, 0, self::PATH_MAX);
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, string>
     */
    private function rowFromMessage(array $message): array
    {
        return array_filter([
            'type' => trim((string) ($message['type'] ?? 'error')),
            'code' => trim((string) ($message['code'] ?? '')),
            'message' => $this->truncate(trim((string) ($message['text'] ?? $message['message'] ?? $message['content'] ?? '')), 160),
        ], fn (string $v): bool => $v !== '');
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  list<string>  $keys
     * @return list<array<string, string>>
     */
    private function collectApplicationResultRows(array $json, array $keys): array
    {
        $app = $this->findApplicationResultsNode($json);
        if ($app === []) {
            return [];
        }

        $rows = [];
        foreach ($keys as $key) {
            $node = $app[$key] ?? null;
            if ($node === null) {
                continue;
            }
            $list = is_array($node) && isset($node[0]) ? $node : [$node];
            foreach ($list as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $rows[] = array_filter([
                    'type' => strtolower($key),
                    'code' => trim((string) ($item['code'] ?? $item['ErrorCode'] ?? '')),
                    'message' => $this->truncate(trim((string) ($item['ShortText'] ?? $item['message'] ?? $item['content'] ?? '')), 160),
                ], fn (string $v): bool => $v !== '');
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function findApplicationResultsNode(array $json): array
    {
        foreach ([
            'OTA_AirLowFareSearchRS.ApplicationResults',
            'ApplicationResults',
            'groupedItineraryResponse.ApplicationResults',
        ] as $path) {
            $node = data_get($json, $path);
            if (is_array($node) && $node !== []) {
                return $node;
            }
        }

        return [];
    }

    private function truncate(string $value, int $max): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max);
    }
}
