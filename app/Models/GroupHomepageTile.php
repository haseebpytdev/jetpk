<?php

namespace App\Models;

use App\Enums\GroupHomepageTileTargetType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'title',
    'image_path',
    'target_type',
    'target_value',
    'is_active',
    'sort_order',
])]
class GroupHomepageTile extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'target_type' => GroupHomepageTileTargetType::class,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function searchQueryParams(): array
    {
        $type = $this->target_type instanceof GroupHomepageTileTargetType
            ? $this->target_type
            : GroupHomepageTileTargetType::tryFrom((string) $this->target_type) ?? GroupHomepageTileTargetType::All;

        return match ($type) {
            GroupHomepageTileTargetType::Sector => ['sector' => trim((string) $this->target_value)],
            GroupHomepageTileTargetType::Category => ['category' => trim((string) $this->target_value)],
            GroupHomepageTileTargetType::All => [],
        };
    }

    public function imageUrl(): ?string
    {
        if (! is_string($this->image_path) || $this->image_path === '') {
            return null;
        }

        $path = ltrim($this->image_path, '/');

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        return asset('storage/'.$path);
    }
}
