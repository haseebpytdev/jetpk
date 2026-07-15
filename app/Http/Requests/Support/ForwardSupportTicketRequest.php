<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

class ForwardSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('forwarded_to_agent_id') === '') {
            $this->merge(['forwarded_to_agent_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'forwarded_to_agent_id' => ['nullable', 'integer', 'exists:agents,id'],
        ];
    }
}
