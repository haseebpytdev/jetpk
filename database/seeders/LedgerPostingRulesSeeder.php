<?php

namespace Database\Seeders;

use App\Models\LedgerPostingRule;
use Illuminate\Database\Seeder;

class LedgerPostingRulesSeeder extends Seeder
{
    /** @var list<array<string, mixed>> */
    public const RULES = [
        ['event_type' => 'agency_deposit_approved', 'debit_account_code' => 'PLATFORM_CASH', 'credit_account_code' => 'AGENCY_WALLET_LIABILITY'],
        ['event_type' => 'booking_payment_verified', 'debit_account_code' => 'PAYMENT_GATEWAY_CLEARING', 'credit_account_code' => 'CUSTOMER_BOOKING_LIABILITY'],
        ['event_type' => 'booking_refund_approved', 'debit_account_code' => 'CUSTOMER_BOOKING_LIABILITY', 'credit_account_code' => 'REFUND_LIABILITY'],
        ['event_type' => 'booking_refund_paid', 'debit_account_code' => 'REFUND_LIABILITY', 'credit_account_code' => 'PLATFORM_CASH'],
        ['event_type' => 'agency_commission_earned', 'debit_account_code' => 'AGENCY_COMMISSION_EXPENSE', 'credit_account_code' => 'AGENCY_COMMISSION_PAYABLE'],
        ['event_type' => 'markup_revenue_recognized', 'debit_account_code' => 'CUSTOMER_BOOKING_LIABILITY', 'credit_account_code' => 'PLATFORM_MARKUP_REVENUE'],
        ['event_type' => 'wallet_admin_credit', 'debit_account_code' => 'PLATFORM_CASH', 'credit_account_code' => 'AGENCY_WALLET_LIABILITY', 'properties' => ['scope' => 'backfill']],
        ['event_type' => 'wallet_admin_debit', 'debit_account_code' => 'AGENCY_WALLET_LIABILITY', 'credit_account_code' => 'PLATFORM_CASH', 'properties' => ['scope' => 'backfill']],
        ['event_type' => 'wallet_booking_hold', 'debit_account_code' => 'AGENCY_WALLET_LIABILITY', 'credit_account_code' => 'CUSTOMER_BOOKING_LIABILITY', 'properties' => ['scope' => 'backfill']],
        ['event_type' => 'wallet_booking_release', 'debit_account_code' => 'CUSTOMER_BOOKING_LIABILITY', 'credit_account_code' => 'AGENCY_WALLET_LIABILITY', 'properties' => ['scope' => 'backfill']],
        ['event_type' => 'wallet_adjustment', 'debit_account_code' => 'MANUAL_ADJUSTMENT_CLEARING', 'credit_account_code' => 'AGENCY_WALLET_LIABILITY', 'properties' => ['scope' => 'backfill']],
        ['event_type' => 'manual_wallet_credit', 'debit_account_code' => 'MANUAL_ADJUSTMENT_CLEARING', 'credit_account_code' => 'AGENCY_WALLET_LIABILITY'],
        ['event_type' => 'manual_wallet_debit', 'debit_account_code' => 'AGENCY_WALLET_LIABILITY', 'credit_account_code' => 'MANUAL_ADJUSTMENT_CLEARING'],
        ['event_type' => 'manual_wallet_credit_reversal', 'debit_account_code' => 'MANUAL_ADJUSTMENT_CLEARING', 'credit_account_code' => 'AGENCY_WALLET_LIABILITY'],
        ['event_type' => 'manual_wallet_debit_reversal', 'debit_account_code' => 'AGENCY_WALLET_LIABILITY', 'credit_account_code' => 'MANUAL_ADJUSTMENT_CLEARING'],
    ];

    public function run(): void
    {
        foreach (self::RULES as $rule) {
            LedgerPostingRule::query()->updateOrCreate(
                ['event_type' => $rule['event_type']],
                [
                    'debit_account_code' => $rule['debit_account_code'],
                    'credit_account_code' => $rule['credit_account_code'],
                    'enabled' => true,
                    'properties' => $rule['properties'] ?? null,
                ],
            );
        }
    }
}
