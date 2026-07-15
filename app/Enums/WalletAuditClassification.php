<?php

namespace App\Enums;

/**
 * Read-only duplicate-wallet audit classification (Finance-Reports-14).
 */
enum WalletAuditClassification: string
{
    case Canonical = 'canonical';
    case HistoricalActive = 'historical_active';
    case CleanupCandidate = 'cleanup_candidate';
    case ReviewRequired = 'review_required';
    case ArchivedDuplicate = 'archived_duplicate';

    public function label(): string
    {
        return match ($this) {
            self::Canonical => 'Canonical',
            self::HistoricalActive => 'Historical active',
            self::CleanupCandidate => 'Cleanup candidate',
            self::ReviewRequired => 'Review required',
            self::ArchivedDuplicate => 'Archived duplicate',
        };
    }

    public function recommendation(): string
    {
        return match ($this) {
            self::Canonical => 'Operational wallet for this agency. Do not delete or merge.',
            self::HistoricalActive => 'Retain for audit trail. Has balance, wallet activity, deposits, or ledger history.',
            self::CleanupCandidate => 'Zero balance with no references. Eligible for archive via admin preview or agent-wallets:archive-candidates --apply.',
            self::ReviewRequired => 'Manual finance review required before any cleanup decision.',
            self::ArchivedDuplicate => 'Archived zero-balance duplicate. Retained for audit trail; not operational.',
        };
    }
}
