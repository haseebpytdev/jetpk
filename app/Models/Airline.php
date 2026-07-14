<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Airline extends Model
{
    protected $fillable = [
        'iata_code',
        'icao_code',
        'name',
        'country',
        'logo_path',
        'is_active',
        'search_keywords',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'is_active' => 'bool',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $needle = mb_strtolower($term);

        return $query->where(function (Builder $q) use ($needle): void {
            $q->whereRaw('LOWER(COALESCE(iata_code, "")) LIKE ?', ["%{$needle}%"])
                ->orWhereRaw('LOWER(COALESCE(icao_code, "")) LIKE ?', ["%{$needle}%"])
                ->orWhereRaw('LOWER(COALESCE(name, "")) LIKE ?', ["%{$needle}%"])
                ->orWhereRaw('LOWER(COALESCE(country, "")) LIKE ?', ["%{$needle}%"])
                ->orWhereRaw('LOWER(COALESCE(search_keywords, "")) LIKE ?', ["%{$needle}%"]);
        });
    }
}
