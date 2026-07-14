<?php

namespace App\Http\Requests\Agent;

use App\Models\User;
use App\Policies\AgentStaffPolicy;
use Illuminate\Foundation\Http\FormRequest;

class ApplyAgentStaffPermissionTemplateRequest extends FormRequest
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
            'confirm_template_apply' => ['required', 'accepted'],
        ];
    }
}
