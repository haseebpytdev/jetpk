<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @param  array<string, string>  $attributes
     * @return array<string, mixed>
     */
    protected function validateBackOffice(Request $request, array $rules, array $messages = [], array $attributes = []): array
    {
        try {
            return $request->validate($rules, $messages, $attributes);
        } catch (ValidationException $e) {
            if ($this->wantsBackOfficeJson($request)) {
                $firstMessage = collect($e->errors())->flatten()->first();

                throw new HttpResponseException(
                    $this->backOfficeJsonError(
                        is_string($firstMessage) ? $firstMessage : 'Validation failed.',
                        422,
                        'validation_error',
                        $e->errors(),
                    )
                );
            }

            throw $e;
        }
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
