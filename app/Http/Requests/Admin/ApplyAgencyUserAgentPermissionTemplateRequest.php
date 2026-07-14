<?php

namespace App\Http\Requests\Admin;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ApplyAgencyUserAgentPermissionTemplateRequest extends FormRequest
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
            'confirm_template_apply' => ['required', 'accepted'],
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
                $validator->errors()->add('confirm_template_apply', 'Role templates can only be applied to agency staff accounts.');
            }

            if ((int) $user->current_agency_id !== (int) $agency->id) {
                $validator->errors()->add('confirm_template_apply', 'This user does not belong to the selected agency.');
            }
        });
    }
}
