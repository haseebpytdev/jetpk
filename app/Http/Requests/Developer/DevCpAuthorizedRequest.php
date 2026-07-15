<?php

namespace App\Http\Requests\Developer;

use App\Models\ClientProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

abstract class DevCpAuthorizedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->session()->has('dev_cp_user_id');
    }

    /**
     * Require explicit confirmation when editing the deployment master profile.
     */
    protected function validateMasterEditConfirmation(?ClientProfile $profile): void
    {
        if ($profile === null || ! $profile->is_master_profile) {
            return;
        }

        if ($this->input('confirm_master_edit') !== '1') {
            throw ValidationException::withMessages([
                'confirm_master_edit' => 'You must confirm editing the master deployment profile.',
            ]);
        }
    }
}
