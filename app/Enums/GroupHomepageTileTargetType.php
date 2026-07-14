<?php

namespace App\Enums;

enum GroupHomepageTileTargetType: string
{
    case All = 'all';
    case Sector = 'sector';
    case Category = 'category';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All groups',
            self::Sector => 'Sector / route',
            self::Category => 'Category',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
