<?php

namespace App\Http\Requests\Agent;

use App\Enums\AgencyRole;
use App\Models\User;
use App\Policies\AgentStaffPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentStaffAgencyRoleRequest extends FormRequest
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
        $assignableRoles = array_values(array_filter(
            AgencyRole::values(),
            static fn (string $value): bool => $value !== AgencyRole::Owner->value,
        ));

        return [
            'agency_role' => ['required', 'string', Rule::in($assignableRoles)],
        ];
    }
}
