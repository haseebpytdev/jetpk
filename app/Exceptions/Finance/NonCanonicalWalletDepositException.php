<?php

namespace App\Exceptions\Finance;

/**
 * Fail-closed when approving a deposit that targets a historical/non-canonical wallet.
 */
final class NonCanonicalWalletDepositException extends \RuntimeException
{
    public function __construct(
        public readonly int $depositId,
        public readonly int $agencyId,
        public readonly int $depositWalletId,
        public readonly ?int $canonicalWalletId,
        string $message = 'Deposit approval blocked: deposit wallet is not the agency canonical wallet. Reconciliation required.',
    ) {
        parent::__construct($message);
    }
}
