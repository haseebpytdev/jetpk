<?php

namespace App\Http\Requests\Agent;

use App\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateAgentAgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $agent = $this->user()?->agent();

        return $agent instanceof Agent && Gate::allows('updateAgency', $agent);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $agent = $this->user()?->agent();
        $userId = $agent?->user_id;

        return [
            'agency_name' => ['required', 'string', 'max:160'],
            'license_number' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => [
                'nullable',
                'email',
                'max:160',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'logo' => ['nullable', 'image', 'max:2048'],
            'code_prefix' => ['nullable', 'string', 'min:2', 'max:4', 'regex:/^[A-Z0-9]+$/'],
        ];
    }
}
