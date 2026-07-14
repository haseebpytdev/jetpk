<?php

namespace App\Http\Requests\Admin;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgencyUserAgentPermissionsRequest extends FormRequest
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
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(AgentPermission::staffSelectable())],
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

            if ($user->account_type !== AccountType::AgentStaff) {
                $validator->errors()->add('permissions', 'Permission matrix can only be updated for agency staff accounts.');
            }

            if ((int) $user->current_agency_id !== (int) $agency->id) {
                $validator->errors()->add('permissions', 'This user does not belong to the selected agency.');
            }
        });
    }
}
