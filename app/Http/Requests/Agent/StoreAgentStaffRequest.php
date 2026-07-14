<?php

namespace App\Http\Requests\Agent;

use App\Policies\AgentStaffPolicy;
use App\Support\Agents\AgentPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AgentStaffPolicy::class)->create($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(AgentPermission::all())],
        ];
    }
}
