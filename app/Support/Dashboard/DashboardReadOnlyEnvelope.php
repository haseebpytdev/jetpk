<?php

namespace App\Support\Dashboard;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Read-only dashboard API response envelope — aligned with Next.js dash-read-only-v1 contract.
 */
final class DashboardReadOnlyEnvelope
{
    public const SCHEMA_VERSION = 'dash-read-only-v1';

    public const SOURCE = 'laravelReadOnly';

    /**
     * @param  array<string, mixed>|null  $pagination
     * @param  array<string, mixed>|null  $filters
     * @param  list<array{code: string, message: string}>  $warnings
     */
    public static function success(
        mixed $data,
        ?array $pagination = null,
        ?array $filters = null,
        array $warnings = [],
        ?string $referenceTime = null,
        ?string $staleAfter = null,
        ?int $recordCount = null,
    ): JsonResponse {
        $generatedAt = now()->toIso8601String();
        $reference = $referenceTime ?? $generatedAt;

        return response()
            ->json([
                'data' => $data,
                'meta' => [
                    'source' => self::SOURCE,
                    'fetchedAt' => $generatedAt,
                    'referenceTime' => $reference,
                    'staleAfter' => $staleAfter,
                    'requestIdSafe' => self::safeRequestId(),
                    'recordCount' => $recordCount,
                    'fixtureRevision' => null,
                    'schemaVersion' => self::SCHEMA_VERSION,
                ],
                'pagination' => $pagination,
                'filters' => $filters,
                'source' => self::SOURCE,
                'generatedAt' => $generatedAt,
                'referenceTime' => $reference,
                'warnings' => $warnings,
                'schemaVersion' => self::SCHEMA_VERSION,
            ])
            ->header('Cache-Control', 'private, no-store, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    public static function error(string $code, string $message, int $status, ?string $referenceIdSafe = null): JsonResponse
    {
        return response()
            ->json([
                'error' => [
                    'code' => $code,
                    'message' => $message,
                    'status' => $status,
                    'referenceIdSafe' => $referenceIdSafe ?? 'HTTP-'.$status,
                ],
                'meta' => [
                    'source' => self::SOURCE,
                    'schemaVersion' => self::SCHEMA_VERSION,
                ],
            ], $status)
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * @return array{page: int, pageSize: int, total: int, pageCount: int}
     */
    public static function pagination(int $page, int $pageSize, int $total): array
    {
        $pageSize = max(1, $pageSize);
        $pageCount = (int) max(1, (int) ceil($total / $pageSize));

        return [
            'page' => max(1, $page),
            'pageSize' => $pageSize,
            'total' => $total,
            'pageCount' => $pageCount,
        ];
    }

    public static function safeRequestId(): string
    {
        return 'DASH-'.strtoupper(Str::random(8));
    }
}
