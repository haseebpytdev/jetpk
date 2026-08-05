<?php

namespace App\Http\Requests\Support;

use App\Http\Requests\Concerns\HandlesBackOfficeJsonValidationFailure;
use Illuminate\Foundation\Http\FormRequest;

class ReplySupportTicketRequest extends FormRequest
{
    use HandlesBackOfficeJsonValidationFailure;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'visibility' => ['sometimes', 'string', 'in:customer_visible,internal'],
        ];
    }
}
