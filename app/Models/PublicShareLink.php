<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicShareLink extends Model
{
    protected $fillable = [
        'code',
        'link_type',
        'origin',
        'destination',
        'depart_date',
        'return_date',
        'trip_type',
        'adults',
        'children',
        'infants',
        'cabin',
        'display_currency',
        'display_fare',
        'airline_code',
        'airline_name',
        'offer_fingerprint',
        'supplier_offer_expires_at',
        'expires_at',
        'payload',
        'created_by_context',
    ];

    protected function casts(): array
    {
        return [
            'depart_date' => 'date',
            'return_date' => 'date',
            'display_fare' => 'decimal:2',
            'supplier_offer_expires_at' => 'datetime',
            'expires_at' => 'datetime',
            'payload' => 'array',
            'adults' => 'integer',
            'children' => 'integer',
            'infants' => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function publicPath(): string
    {
        $prefix = $this->link_type === 'group_offer' ? 'g' : 'f';

        return '/'.$prefix.'/'.$this->code;
    }
}
