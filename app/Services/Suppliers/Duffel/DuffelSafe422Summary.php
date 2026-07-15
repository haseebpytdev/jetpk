<?php

namespace App\Services\Suppliers\Duffel;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

/**
 * Extracts non-sensitive fields from Duffel JSON error responses (typically HTTP 422).
 */
final class DuffelSafe422Summary
{
    /**
     * @return array{
     *     duffel_errors: list<array{
     *         code: string,
     *         title: string,
     *         detail: string,
     *         source_pointer: string,
     *         type: string
     *     }>
     * }
     */
    public static function fromResponse(Response $response): array
    {
        $json = $response->json();
        $rows = [];
        if (! is_array($json)) {
            return ['duffel_errors' => []];
        }
        $errors = $json['errors'] ?? [];
        if (! is_array($errors)) {
            return ['duffel_errors' => []];
        }

        foreach ($errors as $error) {
            if (! is_array($error)) {
                continue;
            }
            $source = is_array($error['source'] ?? null) ? $error['source'] : [];
            $rows[] = [
                'code' => Str::limit((string) ($error['code'] ?? ''), 120),
                'title' => Str::limit((string) ($error['title'] ?? ''), 500),
                'detail' => Str::limit((string) ($error['detail'] ?? ''), 1000),
                'source_pointer' => Str::limit((string) ($source['pointer'] ?? ''), 500),
                'type' => Str::limit((string) ($error['type'] ?? ''), 120),
            ];
        }

        return ['duffel_errors' => $rows];
    }

    /**
     * True when Duffel explicitly indicates the offer is gone (stale recovery should run),
     * not a generic malformed client request.
     *
     * @param  list<array{code: string, title: string, detail: string, source_pointer: string, type: string}>  $duffelErrors
     */
    public static function indicatesUnavailableOrExpiredOffer(array $duffelErrors): bool
    {
        foreach ($duffelErrors as $error) {
            $code = strtolower((string) ($error['code'] ?? ''));
            $title = strtolower((string) ($error['title'] ?? ''));
            $detail = strtolower((string) ($error['detail'] ?? ''));
            $pointer = strtolower((string) ($error['source_pointer'] ?? ''));

            if (str_contains($code, 'offer_expired') || str_contains($code, 'offer_no_longer_available')) {
                return true;
            }
            if (str_contains($code, 'resource_not_found') || str_contains($code, 'not_found')) {
                if (str_contains($pointer, 'offer') || str_contains($detail, 'offer') || str_contains($title, 'offer')) {
                    return true;
                }
            }
            if (str_contains($title, 'offer') && (str_contains($title, 'expired') || str_contains($title, 'no longer'))) {
                return true;
            }
            if (str_contains($detail, 'offer') && (str_contains($detail, 'expired') || str_contains($detail, 'no longer available'))) {
                return true;
            }
        }

        return false;
    }
}
