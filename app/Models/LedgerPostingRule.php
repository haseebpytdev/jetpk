<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'event_type',
    'debit_account_code',
    'credit_account_code',
    'enabled',
    'properties',
])]
class LedgerPostingRule extends Model
{
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'properties' => 'array',
        ];
    }
}
