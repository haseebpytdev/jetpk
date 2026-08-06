<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait RespondsWithGuestBookingJson
{
    protected function wantsGuestBookingJson(Request $request): bool
    {
        return $request->wantsJson() || $request->query('format') === 'json';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function guestBookingJson(array $payload, int $status = 200): JsonResponse
    {
        return response()
            ->json($payload, $status)
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    protected function guestBookingAccessDenied(): JsonResponse
    {
        return $this->guestBookingJson([
            'ok' => false,
            'code' => 'access_denied',
            'message' => 'Access denied.',
        ], 403);
    }

    /**
     * @param  array<string, list<string>>|null  $errors
     */
    protected function guestBookingJsonError(
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

        return $this->guestBookingJson($payload, $status);
    }
}
