<?php

namespace App\Enums;

enum SupportTicketStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function allowsCustomerReply(): bool
    {
        return in_array($this, [self::Open, self::Pending], true);
    }

    public function customerLabel(): string
    {
        return match ($this) {
            self::Open => 'Under review',
            self::Pending => 'Pending',
            self::Resolved, self::Closed => 'Finalised',
        };
    }

    public function customerBadgeClass(): string
    {
        return match ($this) {
            self::Open => 'ota-account-badge--info',
            self::Pending => 'ota-account-badge--warning',
            self::Resolved => 'ota-account-badge--success',
            self::Closed => 'ota-account-badge--muted',
        };
    }
}
