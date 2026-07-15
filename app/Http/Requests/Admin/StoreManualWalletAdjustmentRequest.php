<?php

namespace App\Http\Requests\Admin;

use App\Models\Agency;
use App\Models\AgentWallet;
use App\Services\Agents\AgentWalletService;
use App\Services\Finance\Adjustments\ManualWalletAdjustmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreManualWalletAdjustmentRequest extends FormRequest
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
            'agency_id' => ['required', 'integer', 'exists:agencies,id'],
            'wallet_id' => ['nullable', 'integer', 'exists:agent_wallets,id'],
            'adjustment_type' => ['required', Rule::in(['manual_credit', 'manual_debit'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'adjustment_reason' => ['required', Rule::in(ManualWalletAdjustmentService::REASON_CATEGORIES)],
            'adjustment_note' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'uuid'],
            'confirmation' => ['accepted'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('wallet_id')) {
                return;
            }

            $agencyId = (int) $this->input('agency_id');
            if ($agencyId <= 0) {
                return;
            }

            $canonical = app(AgentWalletService::class)->canonicalWalletForAgency($agencyId);
            if ($canonical === null) {
                return;
            }

            if ((int) $this->input('wallet_id') !== (int) $canonical->id) {
                $validator->errors()->add(
                    'wallet_id',
                    'Adjustments must use the canonical agency wallet (#'.$canonical->id.').',
                );
            }
        });
    }

    public function agency(): Agency
    {
        return Agency::query()->findOrFail((int) $this->input('agency_id'));
    }

    public function resolvedWallet(): AgentWallet
    {
        return app(AgentWalletService::class)->getOrCreateCanonicalWalletForAgency($this->agency());
    }
}
