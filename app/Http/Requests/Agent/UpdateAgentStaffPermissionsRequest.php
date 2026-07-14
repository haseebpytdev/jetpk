<?php

namespace App\Http\Requests\Agent;

use App\Models\User;
use App\Policies\AgentStaffPolicy;
use App\Support\Agents\AgentPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentStaffPermissionsRequest extends FormRequest
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
        return [
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(AgentPermission::staffSelectable())],
        ];
    }
}
