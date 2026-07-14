<?php

namespace App\Services\Finance\Ledger;

use App\Enums\LedgerAccountType;
use App\Enums\LedgerNormalBalance;
use App\Models\LedgerAccount;
use Illuminate\Support\Collection;

/**
 * Chart-of-accounts lookup and system account seeding.
 */
class LedgerAccountService
{
    /** @var list<array<string, mixed>> */
    public const SYSTEM_ACCOUNTS = [
        ['code' => 'PLATFORM_CASH', 'name' => 'Platform Cash', 'account_type' => LedgerAccountType::Asset, 'normal_balance' => LedgerNormalBalance::Debit],
        ['code' => 'PAYMENT_GATEWAY_CLEARING', 'name' => 'Payment Gateway Clearing', 'account_type' => LedgerAccountType::Asset, 'normal_balance' => LedgerNormalBalance::Debit],
        ['code' => 'AGENCY_WALLET_LIABILITY', 'name' => 'Agency Wallet Liability', 'account_type' => LedgerAccountType::Liability, 'normal_balance' => LedgerNormalBalance::Credit],
        ['code' => 'CUSTOMER_BOOKING_LIABILITY', 'name' => 'Customer Booking Liability', 'account_type' => LedgerAccountType::Liability, 'normal_balance' => LedgerNormalBalance::Credit],
        ['code' => 'SUPPLIER_PAYABLE', 'name' => 'Supplier Payable', 'account_type' => LedgerAccountType::Liability, 'normal_balance' => LedgerNormalBalance::Credit],
        ['code' => 'PLATFORM_MARKUP_REVENUE', 'name' => 'Platform Markup Revenue', 'account_type' => LedgerAccountType::Revenue, 'normal_balance' => LedgerNormalBalance::Credit],
        ['code' => 'SERVICE_FEE_REVENUE', 'name' => 'Service Fee Revenue', 'account_type' => LedgerAccountType::Revenue, 'normal_balance' => LedgerNormalBalance::Credit],
        ['code' => 'AGENCY_COMMISSION_EXPENSE', 'name' => 'Agency Commission Expense', 'account_type' => LedgerAccountType::Expense, 'normal_balance' => LedgerNormalBalance::Debit],
        ['code' => 'AGENCY_COMMISSION_PAYABLE', 'name' => 'Agency Commission Payable', 'account_type' => LedgerAccountType::Liability, 'normal_balance' => LedgerNormalBalance::Credit],
        ['code' => 'CANCELLATION_FEE_REVENUE', 'name' => 'Cancellation Fee Revenue', 'account_type' => LedgerAccountType::Revenue, 'normal_balance' => LedgerNormalBalance::Credit],
        ['code' => 'REFUND_LIABILITY', 'name' => 'Refund Liability', 'account_type' => LedgerAccountType::Liability, 'normal_balance' => LedgerNormalBalance::Credit],
        ['code' => 'MANUAL_ADJUSTMENT_CLEARING', 'name' => 'Manual Adjustment Clearing', 'account_type' => LedgerAccountType::Clearing, 'normal_balance' => LedgerNormalBalance::Debit],
        ['code' => 'ROUNDING_ADJUSTMENT', 'name' => 'Rounding Adjustment', 'account_type' => LedgerAccountType::Expense, 'normal_balance' => LedgerNormalBalance::Debit],
    ];

    /**
     * @return array{created: int, skipped: int, accounts: list<string>}
     */
    public function seedSystemAccounts(bool $dryRun = true): array
    {
        $created = 0;
        $skipped = 0;
        $accounts = [];

        foreach (self::SYSTEM_ACCOUNTS as $definition) {
            $exists = LedgerAccount::query()->where('code', $definition['code'])->exists();
            if ($exists) {
                $skipped++;
                $accounts[] = $definition['code'];

                continue;
            }

            if (! $dryRun) {
                LedgerAccount::query()->create([
                    'code' => $definition['code'],
                    'name' => $definition['name'],
                    'account_type' => $definition['account_type'],
                    'normal_balance' => $definition['normal_balance'],
                    'currency' => 'PKR',
                    'is_system' => true,
                    'is_active' => true,
                ]);
            }

            $created++;
            $accounts[] = $definition['code'];
        }

        return compact('created', 'skipped', 'accounts');
    }

    public function findByCode(string $code, ?int $agencyId = null): ?LedgerAccount
    {
        return LedgerAccount::query()
            ->where('code', $code)
            ->when($agencyId !== null, fn ($q) => $q->where(function ($inner) use ($agencyId) {
                $inner->whereNull('agency_id')->orWhere('agency_id', $agencyId);
            }))
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return Collection<int, LedgerAccount>
     */
    public function listActive(?int $agencyId = null): Collection
    {
        return LedgerAccount::query()
            ->where('is_active', true)
            ->when($agencyId !== null, fn ($q) => $q->where(function ($inner) use ($agencyId) {
                $inner->whereNull('agency_id')->orWhere('agency_id', $agencyId);
            }))
            ->orderBy('code')
            ->get();
    }

    public function accountExists(string $code): bool
    {
        return LedgerAccount::query()->where('code', $code)->exists();
    }

    public function ensureAccountsExist(): void
    {
        if (! $this->accountExists('PLATFORM_CASH')) {
            $this->seedSystemAccounts(dryRun: false);
        }
    }
}
