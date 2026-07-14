<?php

namespace App\Models;

use Database\Factories\AgencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

#[Fillable(['name', 'slug', 'timezone', 'settings'])]
class Agency extends Model
{
    /** @use HasFactory<AgencyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * Columns safe for restricted SELECT / eager-load (production has no agencies.code).
     *
     * @return list<string>
     */
    public static function restrictedSelectColumns(): array
    {
        $columns = ['id', 'name', 'slug', 'settings'];

        if (Schema::hasColumn((new static)->getTable(), 'code')) {
            $columns[] = 'code';
        }

        return $columns;
    }

    public static function restrictedEagerLoad(): string
    {
        return implode(',', static::restrictedSelectColumns());
    }

    public static function hasLegalNameColumn(): bool
    {
        return Schema::hasColumn((new static)->getTable(), 'legal_name');
    }

    /**
     * Columns for wallet audit agency queries (production has no agencies.legal_name).
     *
     * @return list<string>
     */
    public static function walletAuditSelectColumns(): array
    {
        $columns = ['id', 'name'];

        if (static::hasLegalNameColumn()) {
            $columns[] = 'legal_name';
        }

        return $columns;
    }

    /**
     * Display label for wallet audit reports; prefers agencies.name.
     */
    public function walletAuditDisplayName(): string
    {
        $name = trim((string) ($this->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        if (static::hasLegalNameColumn() && array_key_exists('legal_name', $this->attributes)) {
            $legal = trim((string) ($this->legal_name ?? ''));
            if ($legal !== '') {
                return $legal;
            }
        }

        return 'Agency #'.$this->id;
    }

    /** @return BelongsToMany<User, $this, AgencyUser> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'agency_users')
            ->using(AgencyUser::class)
            ->withPivot(['role', 'agency_role'])
            ->withTimestamps();
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return HasMany<Agent, $this> */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    /** @return HasMany<StaffProfile, $this> */
    public function staffProfiles(): HasMany
    {
        return $this->hasMany(StaffProfile::class);
    }

    /** @return HasMany<MarkupRule, $this> */
    public function markupRules(): HasMany
    {
        return $this->hasMany(MarkupRule::class);
    }

    /** @return HasMany<SupplierConnection, $this> */
    public function supplierConnections(): HasMany
    {
        return $this->hasMany(SupplierConnection::class);
    }

    /** @return HasMany<BookingNote, $this> */
    public function bookingNotes(): HasMany
    {
        return $this->hasMany(BookingNote::class);
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

    /** @return HasMany<BookingPayment, $this> */
    public function bookingPayments(): HasMany
    {
        return $this->hasMany(BookingPayment::class);
    }

    /** @return HasMany<BookingTicket, $this> */
    public function bookingTickets(): HasMany
    {
        return $this->hasMany(BookingTicket::class);
    }

    /** @return HasMany<TicketingAttempt, $this> */
    public function ticketingAttempts(): HasMany
    {
        return $this->hasMany(TicketingAttempt::class);
    }

    /** @return HasMany<CommunicationLog, $this> */
    public function communicationLogs(): HasMany
    {
        return $this->hasMany(CommunicationLog::class);
    }

    /** @return HasMany<BookingDocument, $this> */
    public function bookingDocuments(): HasMany
    {
        return $this->hasMany(BookingDocument::class);
    }

    /** @return HasMany<GuestBookingAccessToken, $this> */
    public function guestBookingAccessTokens(): HasMany
    {
        return $this->hasMany(GuestBookingAccessToken::class);
    }

    /** @return HasMany<AgentCommissionEntry, $this> */
    public function agentCommissionEntries(): HasMany
    {
        return $this->hasMany(AgentCommissionEntry::class);
    }

    /** @return HasMany<AgentCommissionStatement, $this> */
    public function agentCommissionStatements(): HasMany
    {
        return $this->hasMany(AgentCommissionStatement::class);
    }

    /** @return HasMany<BookingCancellationRequest, $this> */
    public function bookingCancellationRequests(): HasMany
    {
        return $this->hasMany(BookingCancellationRequest::class);
    }

    /** @return HasMany<BookingRefund, $this> */
    public function bookingRefunds(): HasMany
    {
        return $this->hasMany(BookingRefund::class);
    }

    /** @return HasOne<AgencySetting, $this> */
    public function agencySetting(): HasOne
    {
        return $this->hasOne(AgencySetting::class);
    }

    /** @return HasMany<AgencyHomepageSection, $this> */
    public function homepageSections(): HasMany
    {
        return $this->hasMany(AgencyHomepageSection::class);
    }

    /** @return HasMany<AgencyMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(AgencyMedia::class);
    }

    /** @return HasOne<AgencyCommunicationSetting, $this> */
    public function communicationSetting(): HasOne
    {
        return $this->hasOne(AgencyCommunicationSetting::class);
    }

    /** @return HasMany<AgencyMessageTemplate, $this> */
    public function messageTemplates(): HasMany
    {
        return $this->hasMany(AgencyMessageTemplate::class);
    }

    /** @return HasMany<AgencyNotificationSetting, $this> */
    public function notificationSettings(): HasMany
    {
        return $this->hasMany(AgencyNotificationSetting::class);
    }
}
