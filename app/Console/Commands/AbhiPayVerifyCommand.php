<?php

namespace App\Console\Commands;

use App\Models\PaymentTransaction;
use App\Services\Payments\PaymentTransactionService;
use Illuminate\Console\Command;

class AbhiPayVerifyCommand extends Command
{
    protected $signature = 'payments:abhipay-verify {clientTransactionId}';

    protected $description = 'Verify an AbhiPay payment transaction by client transaction id.';

    public function handle(PaymentTransactionService $paymentTransactionService): int
    {
        $clientTransactionId = (string) $this->argument('clientTransactionId');
        $transaction = PaymentTransaction::query()
            ->where('client_transaction_id', $clientTransactionId)
            ->first();

        if ($transaction === null) {
            $this->error('Transaction not found.');

            return self::FAILURE;
        }

        $transaction = $paymentTransactionService->verifyTransaction($transaction);

        $this->table(['Field', 'Value'], [
            ['Status', (string) $transaction->status->value],
            ['Gateway order', (string) ($transaction->gateway_order_id ?? '')],
            ['Gateway status', (string) ($transaction->gateway_status ?? '')],
            ['Gateway code', (string) ($transaction->gateway_code ?? '')],
            ['Paid at', (string) ($transaction->paid_at ?? '')],
            ['Verified at', (string) ($transaction->verified_at ?? '')],
        ]);

        return self::SUCCESS;
    }
}
