<?php

namespace App\Http\Requests\Agent;

use App\Enums\UserAccountStatus;
use App\Models\User;
use App\Policies\AgentStaffPolicy;
use App\Support\Agents\AgentPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->route('staff');

        return $staff instanceof User
            && app(AgentStaffPolicy::class)->update($this->user(), $staff);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $staff = $this->route('staff');

        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($staff?->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['nullable', 'string', 'min:8', 'max:128'],
            'status' => ['required', Rule::enum(UserAccountStatus::class)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(AgentPermission::staffSelectable())],
        ];
    }
}
