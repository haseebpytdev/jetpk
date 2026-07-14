<?php

namespace App\Services\Finance\Wallets;

use App\Enums\AgentWalletStatus;

/**
 * Result of a single-wallet archive attempt (Finance-Reports-16).
 */
readonly class ArchiveResult
{
    public function __construct(
        public int $walletId,
        public int $agencyId,
        public string $action,
        public bool $success,
        public string $message,
        public ?AgentWalletStatus $statusBefore = null,
        public ?AgentWalletStatus $statusAfter = null,
        public ?string $actorLabel = null,
    ) {}
}
