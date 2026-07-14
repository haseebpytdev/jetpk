<?php

namespace App\Http\Requests\Admin;

use App\Policies\WalletAuditPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArchiveDuplicateWalletsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(WalletAuditPolicy::class)->archive($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'agency_id' => ['required', 'integer', 'exists:agencies,id'],
            'confirmation' => ['required', 'string', Rule::in(['ARCHIVE'])],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmation.in' => 'Type ARCHIVE exactly to confirm this action.',
            'reason.min' => 'Provide an archive reason of at least 10 characters.',
        ];
    }
}
