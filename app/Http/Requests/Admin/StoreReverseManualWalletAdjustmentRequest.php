<?php

namespace App\Http\Requests\Admin;

use App\Models\AgentWalletTransaction;
use App\Services\Finance\Adjustments\ManualWalletAdjustmentService;
use Illuminate\Foundation\Http\FormRequest;

class StoreReverseManualWalletAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! ($this->user()?->isPlatformAdmin() ?? false)) {
            return false;
        }

        $transaction = $this->route('walletTransaction');

        return $transaction instanceof AgentWalletTransaction
            && app(ManualWalletAdjustmentService::class)->canReverse($transaction);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reversal_reason' => ['required', 'string', 'min:3', 'max:2000'],
            'confirmation' => ['accepted'],
        ];
    }
}
