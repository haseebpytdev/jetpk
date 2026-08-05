<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

trait HandlesBackOfficeJsonValidationFailure
{
    protected function failedValidation(Validator $validator): void
    {
        if ($this->wantsJson() || $this->query('format') === 'json') {
            $firstMessage = $validator->errors()->first();

            throw new HttpResponseException(response()->json([
                'ok' => false,
                'message' => is_string($firstMessage) ? $firstMessage : 'Validation failed.',
                'code' => 'validation_error',
                'errors' => $validator->errors()->toArray(),
            ], 422));
        }

        parent::failedValidation($validator);
    }
}
