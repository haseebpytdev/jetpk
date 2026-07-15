<?php

namespace App\Models;

use App\Services\Agents\AgentWalletService;
use App\Support\Agents\AgentPermission;
use App\Support\Branding\BrandDisplayResolver;
use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Route;

#[Fillable([
    'agency_id',
    'user_id',
    'code',
    'commission_percent',
    'is_active',
    'meta',
])]
class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'commission_percent' => 'decimal:2',
            'is_active' => 'boolean',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return HasMany<AgentCommissionEntry, $this> */
    public function commissionEntries(): HasMany
    {
        return $this->hasMany(AgentCommissionEntry::class);
    }

    /** @return HasMany<AgentCommissionStatement, $this> */
    public function commissionStatements(): HasMany
    {
        return $this->hasMany(AgentCommissionStatement::class);
    }

    /** @return HasOne<AgentWallet, $this> */
    public function wallet(): HasOne
    {
        return $this->hasOne(AgentWallet::class);
    }

    /** @return HasMany<AgentDepositRequest, $this> */
    public function depositRequests(): HasMany
    {
        return $this->hasMany(AgentDepositRequest::class);
    }

    public function displayBusinessName(): string
    {
        $meta = is_array($this->meta) ? $this->meta : [];

        foreach (['agency_name', 'company_name'] as $key) {
            $value = trim((string) ($meta[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $this->loadMissing(['agency.agencySetting']);
        $settingsName = trim((string) ($this->agency?->agencySetting?->display_name ?? ''));
        if ($settingsName !== '') {
            return $settingsName;
        }

        $agencyName = trim((string) ($this->agency?->name ?? ''));
        if ($agencyName !== '') {
            return $agencyName;
        }

        return BrandDisplayResolver::displayName();
    }

    public function auditAgencyCodePart(): string
    {
        return self::auditCodePartFromLabel($this->displayBusinessName(), 'UnknownAgency');
    }

    /**
     * @return array{label: string, amount: string, href: string|null}|null
     */
    public function walletSummaryForPortal(User $viewer): ?array
    {
        if (! $viewer->isAgentAdmin() && ! $viewer->hasAgentPermission(AgentPermission::WalletView)) {
            return null;
        }

        if (! Route::has('agent.wallet.show')) {
            return null;
        }

        $summary = app(AgentWalletService::class)->summary($this);
        $currency = (string) ($summary['currency'] ?? 'PKR');
        $available = (float) ($summary['available_balance'] ?? 0);
        $formatted = $currency.' '.number_format($available, 2, '.', ',');

        return [
            'label' => 'Available Balance',
            'amount' => $formatted,
            'href' => route('agent.wallet.show'),
        ];
    }

    public static function auditCodePartFromLabel(?string $label, string $fallback = 'Unknown'): string
    {
        $label = trim((string) $label);
        if ($label === '') {
            return $fallback;
        }

        if (filter_var($label, FILTER_VALIDATE_EMAIL) !== false) {
            return $fallback;
        }

        $parts = preg_split('/\s+/u', $label, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $clean = '';

        foreach ($parts as $part) {
            $chunk = preg_replace('/[^\p{L}\p{N}]+/u', '', $part) ?? '';
            if ($chunk !== '') {
                $clean .= $chunk;
            }
        }

        if ($clean === '') {
            return $fallback;
        }

        return mb_substr($clean, 0, 32);
    }
}
