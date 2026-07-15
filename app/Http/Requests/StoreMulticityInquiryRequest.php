<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMulticityInquiryRequest extends FormRequest
{
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
            'search_id' => ['required', 'string', 'max:64'],
            'offer_id' => ['required', 'string', 'max:128'],
            'requester_name' => [Rule::requiredIf($this->user() === null), 'nullable', 'string', 'max:120'],
            'requester_email' => [Rule::requiredIf($this->user() === null), 'nullable', 'email', 'max:190'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
