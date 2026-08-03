<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait RespondsWithBackOfficeJson
{
    protected function wantsBackOfficeJson(Request $request): bool
    {
        return $request->wantsJson() || $request->query('format') === 'json';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function backOfficeJson(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status);
    }

    /**
     * @param  array<string, list<string>>|null  $errors
     */
    protected function backOfficeJsonError(
        string $message,
        int $status = 422,
        ?string $code = null,
        ?array $errors = null,
    ): JsonResponse {
        $payload = [
            'ok' => false,
            'message' => $message,
        ];

        if ($code !== null) {
            $payload['code'] = $code;
        }

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
