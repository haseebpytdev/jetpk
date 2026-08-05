<?php

namespace App\Http\Requests\Support;

use App\Enums\SupportTicketStatus;
use App\Http\Requests\Concerns\HandlesBackOfficeJsonValidationFailure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupportTicketStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(SupportTicketStatus::values())],
        ];
    }
}
