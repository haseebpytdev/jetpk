<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformPackageModule extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'platform_package_id',
        'module_key',
        'enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<PlatformPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(PlatformPackage::class, 'platform_package_id');
    }
}
