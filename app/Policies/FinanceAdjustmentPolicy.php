<?php

namespace App\Policies;

use App\Enums\AgentWalletTransactionType;
use App\Models\AgentWalletTransaction;
use App\Models\User;
use App\Services\Finance\Adjustments\ManualWalletAdjustmentService;

/**
 * Platform-admin manual wallet adjustments (Finance-Reports-9).
 */
class FinanceAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function view(User $user, AgentWalletTransaction $walletTransaction): bool
    {
        if (! $user->isPlatformAdmin()) {
            return false;
        }

        return in_array($walletTransaction->type, [
            AgentWalletTransactionType::ManualCredit,
            AgentWalletTransactionType::ManualDebit,
        ], true);
    }

    public function reverse(User $user, AgentWalletTransaction $walletTransaction): bool
    {
        if (! $user->isPlatformAdmin()) {
            return false;
        }

        return app(ManualWalletAdjustmentService::class)->canReverse($walletTransaction);
    }
}
