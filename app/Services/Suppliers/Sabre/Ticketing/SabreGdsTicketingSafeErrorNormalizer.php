<?php

namespace App\Services\Suppliers\Sabre\Ticketing;

/**
 * Normalize Sabre ticketing HTTP/JSON errors into safe scalar messages (no raw payloads).
 */
final class SabreGdsTicketingSafeErrorNormalizer
{
    /**
     * @param  array<string, mixed>|null  $json
     * @return array{error_code: string, error_message: string, safe_summary: array<string, mixed>}
     */
    public function fromHttpResponse(int $httpStatus, ?array $json = null): array
    {
        $code = 'ticketing_http_'.$httpStatus;
        $message = 'Sabre ticketing request failed.';

        if (is_array($json)) {
            $appResults = data_get($json, 'AirTicketRS.ApplicationResults');
            if (is_array($appResults)) {
                $status = strtoupper(trim((string) ($appResults['status'] ?? '')));
                if ($status !== '' && $status !== 'COMPLETE') {
                    $code = 'ticketing_application_incomplete';
                    $message = 'Sabre ticketing did not complete successfully.';
                }
            }

            $errors = $json['errors'] ?? null;
            if (is_array($errors) && isset($errors[0]) && is_array($errors[0])) {
                $first = $errors[0];
                $detail = trim((string) ($first['detail'] ?? $first['title'] ?? $first['message'] ?? ''));
                if ($detail !== '') {
                    $message = mb_substr($detail, 0, 200);
                }
                $errCode = trim((string) ($first['code'] ?? ''));
                if ($errCode !== '') {
                    $code = 'ticketing_'.$errCode;
                }
            }

            if (isset($json['errorCode'])) {
                $code = 'ticketing_'.trim((string) $json['errorCode']);
            }
        }

        return [
            'error_code' => $code,
            'error_message' => $message,
            'safe_summary' => [
                'http_status' => $httpStatus,
                'normalized_code' => $code,
                'application_status' => $this->extractApplicationStatus($json),
                'response_shape' => $this->summarizeResponseKeys($json),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array{error_code: string, error_message: string, safe_summary: array<string, mixed>}
     */
    public function fromParseFailure(?array $json = null, string $reason = 'Sabre ticketing response could not be parsed.'): array
    {
        return [
            'error_code' => 'ticketing_parse_failed',
            'error_message' => $reason,
            'safe_summary' => [
                'http_status' => 200,
                'normalized_code' => 'ticketing_parse_failed',
                'application_status' => $this->extractApplicationStatus($json),
                'response_shape' => $this->summarizeResponseKeys($json),
            ],
        ];
    }

    public function fromThrowable(\Throwable $exception): array
    {
        return [
            'error_code' => 'ticketing_unexpected',
            'error_message' => 'Ticketing failed; admin review required.',
            'safe_summary' => [
                'exception_class' => $exception::class,
                'normalized_code' => 'ticketing_unexpected',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function extractApplicationStatus(?array $json): ?string
    {
        if (! is_array($json)) {
            return null;
        }

        $status = data_get($json, 'AirTicketRS.ApplicationResults.status');

        return is_scalar($status) ? trim((string) $status) : null;
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return list<string>
     */
    private function summarizeResponseKeys(?array $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        $keys = array_keys($json);
        if (isset($json['AirTicketRS']) && is_array($json['AirTicketRS'])) {
            $keys = array_merge($keys, array_map(
                static fn (string $key): string => 'AirTicketRS.'.$key,
                array_keys($json['AirTicketRS']),
            ));
        }

        return array_values(array_slice(array_unique($keys), 0, 12));
    }
}
