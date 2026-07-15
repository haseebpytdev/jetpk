<?php

namespace App\Services\Finance\Wallets;

use App\Enums\WalletAuditClassification;

/**
 * Eligibility outcome for archiving a duplicate zero-balance wallet (Finance-Reports-16).
 */
readonly class ArchiveEligibilityResult
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public bool $eligible,
        public array $reasons = [],
        public ?WalletAuditClassification $classification = null,
        public ?int $canonicalWalletId = null,
    ) {}

    public function reason(): string
    {
        return $this->reasons === [] ? '' : implode(' ', $this->reasons);
    }
}
