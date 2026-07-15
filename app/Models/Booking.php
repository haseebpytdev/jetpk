<?php

namespace App\Models;

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Booking identifiers (keep separate; never store airline PNR in API id fields):
 * - booking_reference — internal OTA reference (e.g. ASIF-2026-000123); accessor reference_code for display fallback.
 * - supplier_api_booking_id — supplier/aggregator API booking id (Duffel order, Sabre confirmation id, etc.).
 * - supplier_reference — legacy mirror of API id for older UI; prefer supplier_api_booking_id.
 * - pnr — airline record locator only.
 */
#[Fillable([
    'agency_id',
    'customer_id',
    'agent_id',
    'hold_session_id',
    'supplier',
    'route',
    'airline',
    'travel_date',
    'booking_reference',
    'status',
    'cancellation_status',
    'refund_status',
    'payment_status',
    'payment_due_at',
    'amount_paid',
    'balance_due',
    'promo_code_id',
    'promo_code',
    'promo_discount_amount',
    'payable_before_promo',
    'payable_after_promo',
    'promo_applied_at',
    'source_channel',
    'currency',
    'pnr',
    'supplier_booking_status',
    'ticketing_status',
    'ticketed_at',
    'supplier_reference',
    'supplier_api_booking_id',
    'supplier_hold_status',
    'price_guarantee_expires_at',
    'payment_required_by',
    'supplier_booking_created_at',
    'notes',
    'meta',
    'submitted_at',
    'confirmed_at',
    'cancelled_at',
    'fare_revalidated_at',
    'selected_fare_total',
    'revalidated_fare_total',
    'fare_change_accepted_at',
    'pnr_expires_at',
    'confirmation_method',
    'assigned_staff_id',
    'assigned_at',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'meta' => 'array',
            'travel_date' => 'date',
            'submitted_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'assigned_at' => 'datetime',
            'supplier_booking_created_at' => 'datetime',
            'ticketed_at' => 'datetime',
            'payment_due_at' => 'datetime',
            'price_guarantee_expires_at' => 'datetime',
            'payment_required_by' => 'datetime',
            'pnr_expires_at' => 'datetime',
            'fare_revalidated_at' => 'datetime',
            'fare_change_accepted_at' => 'datetime',
            'selected_fare_total' => 'decimal:2',
            'revalidated_fare_total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'promo_discount_amount' => 'decimal:2',
            'payable_before_promo' => 'decimal:2',
            'payable_after_promo' => 'decimal:2',
            'promo_applied_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return BelongsTo<Agent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** @return BelongsTo<BookingHoldSession, $this> */
    public function holdSession(): BelongsTo
    {
        return $this->belongsTo(BookingHoldSession::class, 'hold_session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    /** @return HasMany<BookingPassenger, $this> */
    public function passengers(): HasMany
    {
        return $this->hasMany(BookingPassenger::class);
    }

    /** @return HasOne<BookingContact, $this> */
    public function contact(): HasOne
    {
        return $this->hasOne(BookingContact::class);
    }

    /** @return HasOne<BookingFareBreakdown, $this> */
    public function fareBreakdown(): HasOne
    {
        return $this->hasOne(BookingFareBreakdown::class);
    }

    /** @return HasMany<BookingStatusLog, $this> */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(BookingStatusLog::class);
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

    /** @return HasOne<SupplierBooking, $this> */
    public function latestSupplierBooking(): HasOne
    {
        return $this->hasOne(SupplierBooking::class)->latestOfMany();
    }

    /** @return HasOne<SupplierBookingAttempt, $this> */
    public function latestSupplierBookingAttempt(): HasOne
    {
        return $this->hasOne(SupplierBookingAttempt::class)->latestOfMany();
    }

    /** @return HasMany<BookingPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class);
    }

    /** @return HasMany<BookingPayment, $this> */
    public function verifiedPayments(): HasMany
    {
        return $this->hasMany(BookingPayment::class)->where('status', BookingPaymentStatus::Verified);
    }

    /** @return HasOne<BookingPayment, $this> */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(BookingPayment::class)->latestOfMany();
    }

    /** @return HasMany<PaymentTransaction, $this> */
    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /** @return BelongsTo<PromoCode, $this> */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    /** @return HasMany<PromoRedemption, $this> */
    public function promoRedemptions(): HasMany
    {
        return $this->hasMany(PromoRedemption::class);
    }

    /** @return HasOne<PaymentTransaction, $this> */
    public function latestPaymentTransaction(): HasOne
    {
        return $this->hasOne(PaymentTransaction::class)->latestOfMany();
    }

    /** @return HasMany<BookingTicket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(BookingTicket::class);
    }

    /** @return HasMany<TicketingAttempt, $this> */
    public function ticketingAttempts(): HasMany
    {
        return $this->hasMany(TicketingAttempt::class);
    }

    /** @return HasOne<TicketingAttempt, $this> */
    public function latestTicketingAttempt(): HasOne
    {
        return $this->hasOne(TicketingAttempt::class)->latestOfMany();
    }

    /** @return HasMany<CommunicationLog, $this> */
    public function communicationLogs(): HasMany
    {
        return $this->hasMany(CommunicationLog::class);
    }

    /** @return HasMany<BookingDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(BookingDocument::class);
    }

    /** @return HasMany<GuestBookingAccessToken, $this> */
    public function guestAccessTokens(): HasMany
    {
        return $this->hasMany(GuestBookingAccessToken::class);
    }

    /** @return HasMany<AgentCommissionEntry, $this> */
    public function commissionEntries(): HasMany
    {
        return $this->hasMany(AgentCommissionEntry::class);
    }

    /** @return HasMany<BookingCancellationRequest, $this> */
    public function cancellationRequests(): HasMany
    {
        return $this->hasMany(BookingCancellationRequest::class);
    }

    /** @return HasOne<BookingCancellationRequest, $this> */
    public function latestCancellationRequest(): HasOne
    {
        return $this->hasOne(BookingCancellationRequest::class)->latestOfMany();
    }

    /** @return HasMany<BookingRefund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(BookingRefund::class);
    }

    /**
     * Internal OTA booking reference (compact alphanumeric). Not the airline PNR or supplier API order id.
     */
    public function getReferenceCodeAttribute(): string
    {
        $ref = trim((string) ($this->booking_reference ?? ''));

        return $ref !== '' ? $ref : ('#'.$this->id);
    }

    /** Portal display reference — same as stored booking_reference (no prefix transformation). */
    public function getDisplayReferenceAttribute(): string
    {
        return $this->reference_code;
    }
}
