<?php

namespace App\Models;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Support\Suppliers\SabreSupplierChannelConfig;
use Database\Factories\SupplierConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

/**
 * Agency-scoped supplier API credentials. Multiple rows per {@see SupplierProvider}
 * are allowed per agency; the human-readable `name` label must be unique per agency+provider (DB + FormRequest).
 */
#[Fillable([
    'agency_id',
    'provider',
    'name',
    'environment',
    'status',
    'base_url',
    'display_name',
    'credentials',
    'is_active',
    'last_tested_at',
    'last_test_status',
    'last_error',
    'settings',
    'meta',
])]
#[Hidden(['credentials'])]
class SupplierConnection extends Model
{
    /** @use HasFactory<SupplierConnectionFactory> */
    use HasFactory;

    private static ?bool $hasHealthyColumn = null;

    private static ?bool $hasMetaColumn = null;

    private static ?bool $hasSettingsColumn = null;

    protected function casts(): array
    {
        return [
            'provider' => SupplierProvider::class,
            'environment' => SupplierEnvironment::class,
            'status' => SupplierConnectionStatus::class,
            'credentials' => 'encrypted:array',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
            'settings' => 'array',
            'meta' => 'array',
        ];
    }

    protected function castAttribute($key, $value)
    {
        if ($key === 'provider' && $value === 'pia') {
            $value = SupplierProvider::PiaNdc->value;
        }

        return parent::castAttribute($key, $value);
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return HasMany<SupplierBookingAttempt, $this> */
    public function supplierBookingAttempts(): HasMany
    {
        return $this->hasMany(SupplierBookingAttempt::class);
    }

    /** @return HasMany<SupplierBooking, $this> */
    public function supplierBookings(): HasMany
    {
        return $this->hasMany(SupplierBooking::class);
    }

    /** @return HasMany<SupplierDiagnosticLog, $this> */
    public function diagnosticLogs(): HasMany
    {
        return $this->hasMany(SupplierDiagnosticLog::class);
    }

    /** @return HasOne<SupplierDiagnosticLog, $this> */
    public function latestReadinessDiagnostic(): HasOne
    {
        return $this->hasOne(SupplierDiagnosticLog::class)
            ->where('action', 'readiness_check')
            ->latest('created_at');
    }

    /** @return HasOne<SupplierDiagnosticLog, $this> */
    public function latestSearchDiagnostic(): HasOne
    {
        return $this->hasOne(SupplierDiagnosticLog::class)
            ->where('action', 'search')
            ->latest('created_at');
    }

    /** @return HasOne<SupplierDiagnosticLog, $this> */
    public function latestOrderDiagnostic(): HasOne
    {
        return $this->hasOne(SupplierDiagnosticLog::class)
            ->where('action', 'create_order')
            ->latest('created_at');
    }

    /**
     * @return array<string, string>
     */
    public function maskedCredentials(): array
    {
        $credentials = $this->credentials ?? [];
        if (! is_array($credentials)) {
            return [];
        }

        $masked = [];
        foreach ($credentials as $key => $value) {
            $text = trim((string) $value);
            if ($text === '') {
                $masked[$key] = '••••••••';

                continue;
            }

            if ($key === 'access_token' && $this->provider === SupplierProvider::Duffel) {
                $prefix = str_starts_with($text, 'duffel_test_') ? 'duffel_test_' : substr($text, 0, min(6, strlen($text)));
                $masked[$key] = $prefix.'••••••••••••';

                continue;
            }

            $tail = strlen($text) > 4 ? substr($text, -4) : $text;
            $masked[$key] = '••••'.$tail;
        }

        return $masked;
    }

    public function isActive(): bool
    {
        return $this->status === SupplierConnectionStatus::Active || $this->is_active;
    }

    /**
     * Active connections remain search-eligible even when a prior health probe was stale/false.
     */
    public function isEligibleForSupplierSearch(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->provider === SupplierProvider::Sabre) {
            return SabreSupplierChannelConfig::anyChannelEnabled($this);
        }

        return true;
    }

    public function supplierHealthHealthy(): bool
    {
        if (self::hasHealthyDatabaseColumn()) {
            $columnHealthy = $this->getAttributes()['healthy'] ?? null;
            if ($columnHealthy !== null) {
                return (bool) $columnHealthy;
            }
        }

        $meta = is_array($this->meta) ? $this->meta : [];
        $health = is_array($meta['supplier_health'] ?? null) ? $meta['supplier_health'] : [];
        if (($health['healthy'] ?? false) === true) {
            return true;
        }

        $settings = is_array($this->settings) ? $this->settings : [];
        $settingsHealth = is_array($settings['supplier_health'] ?? null) ? $settings['supplier_health'] : [];
        if (($settingsHealth['healthy'] ?? false) === true) {
            return true;
        }

        return in_array($this->last_test_status, ['air_shopping_success', 'ready_for_review', 'success'], true);
    }

    public static function hasHealthyDatabaseColumn(): bool
    {
        if (self::$hasHealthyColumn === null) {
            self::$hasHealthyColumn = Schema::hasTable('supplier_connections')
                && Schema::hasColumn('supplier_connections', 'healthy');
        }

        return self::$hasHealthyColumn;
    }

    public static function hasMetaDatabaseColumn(): bool
    {
        if (self::$hasMetaColumn === null) {
            self::$hasMetaColumn = Schema::hasTable('supplier_connections')
                && Schema::hasColumn('supplier_connections', 'meta');
        }

        return self::$hasMetaColumn;
    }

    public static function hasSettingsDatabaseColumn(): bool
    {
        if (self::$hasSettingsColumn === null) {
            self::$hasSettingsColumn = Schema::hasTable('supplier_connections')
                && Schema::hasColumn('supplier_connections', 'settings');
        }

        return self::$hasSettingsColumn;
    }

    public function isLive(): bool
    {
        return $this->environment === SupplierEnvironment::Live;
    }

    public function isSandbox(): bool
    {
        return $this->environment === SupplierEnvironment::Sandbox;
    }

    /**
     * Admin API settings index: rows with stored credentials (excludes audit/foundation placeholder shells).
     *
     * @param  Builder<SupplierConnection>  $query
     * @return Builder<SupplierConnection>
     */
    public function scopeWithStoredCredentials(Builder $query): Builder
    {
        return $query->whereNotNull('credentials');
    }
}
