<?php

namespace App\Enums;

enum AgencyRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Accountant = 'accountant';
    case SalesAgent = 'sales_agent';
    case SupportStaff = 'support_staff';
    case TicketingStaff = 'ticketing_staff';
    case Viewer = 'viewer';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $role) {
            $options[$role->value] = $role->label();
        }

        return $options;
    }

    public static function fromNullable(string|self|null $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Accountant => 'Accountant',
            self::SalesAgent => 'Sales Agent',
            self::SupportStaff => 'Support Staff',
            self::TicketingStaff => 'Ticketing Staff',
            self::Viewer => 'Viewer',
        };
    }

    public function isOwner(): bool
    {
        return $this === self::Owner;
    }
}
