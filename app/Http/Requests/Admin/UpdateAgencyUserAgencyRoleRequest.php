<?php

namespace App\Http\Requests\Admin;

use App\Enums\AccountType;
use App\Enums\AgencyRole;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgencyUserAgencyRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'agency_role' => ['required', 'string', Rule::in(AgencyRole::values())],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $agency = $this->route('agency');
            $user = $this->route('user');

            if (! $agency instanceof Agency || ! $user instanceof User) {
                return;
            }

            if (! in_array($user->account_type, [AccountType::Agent, AccountType::AgentStaff], true)) {
                $validator->errors()->add('agency_role', 'Agency role can only be assigned to agency owners or staff.');
            }

            if ((int) $user->current_agency_id !== (int) $agency->id) {
                $validator->errors()->add('agency_role', 'This user does not belong to the selected agency.');
            }
        });
    }
}
