<?php

namespace App\Http\Requests\Developer;

use App\Enums\UserAccountStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDevCpPlatformAdminStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->session()->has('dev_cp_user_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                UserAccountStatus::Active->value,
                UserAccountStatus::Inactive->value,
            ])],
        ];
    }
}
