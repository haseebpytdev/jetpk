<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Support\Agents\AgentPermission;
use App\Support\Branding\BrandDisplayResolver;
use App\Support\Staff\StaffPermission;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

#[Fillable(['name', 'username', 'email', 'password', 'current_agency_id', 'account_type', 'status', 'invited_at', 'last_login_at', 'social_email_verification_deadline', 'meta', 'must_change_password', 'password_changed_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function hasVerifiedEmail(): bool
    {
        if ($this->account_type !== AccountType::Customer) {
            return true;
        }

        return $this->email_verified_at !== null;
    }

    public function sendEmailVerificationNotification(): void
    {
        if ($this->account_type !== AccountType::Customer) {
            return;
        }

        parent::sendEmailVerificationNotification();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'account_type' => AccountType::class,
            'status' => UserAccountStatus::class,
            'invited_at' => 'datetime',
            'last_login_at' => 'datetime',
            'social_email_verification_deadline' => 'datetime',
            'meta' => 'array',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function currentAgency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'current_agency_id');
    }

    /** @return BelongsToMany<Agency, $this, AgencyUser> */
    public function agencies(): BelongsToMany
    {
        return $this->belongsToMany(Agency::class, 'agency_users')
            ->using(AgencyUser::class)
            ->withPivot(['role', 'agency_role'])
            ->withTimestamps();
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    /** @return HasMany<SavedTraveler, $this> */
    public function savedTravelers(): HasMany
    {
        return $this->hasMany(SavedTraveler::class);
    }

    /** @return HasMany<SupportTicket, $this> */
    public function supportTicketsCreated(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'created_by_user_id');
    }

    /** @return HasMany<StaffProfile, $this> */
    public function staffProfiles(): HasMany
    {
        return $this->hasMany(StaffProfile::class);
    }

    /** @return HasOne<StaffProfile, $this> */
    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    /** @return HasMany<Agent, $this> */
    public function agentProfiles(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    /** @return HasOne<Agent, $this> */
    public function agentProfile(): HasOne
    {
        return $this->hasOne(Agent::class);
    }

    public function agent(): ?Agent
    {
        if ($this->isAgentStaff()) {
            return $this->employerAgent();
        }

        $agencyId = $this->current_agency_id;
        if ($agencyId === null) {
            return null;
        }

        return $this->agentProfiles()
            ->where('agency_id', $agencyId)
            ->first();
    }

    public function employerAgent(): ?Agent
    {
        if ($this->isAgent()) {
            $agencyId = $this->current_agency_id;
            if ($agencyId === null) {
                return $this->agentProfile;
            }

            return $this->agentProfiles()
                ->where('agency_id', $agencyId)
                ->first();
        }

        if (! $this->isAgentStaff()) {
            return null;
        }

        $ownerAgentId = (int) ($this->meta['owner_agent_id'] ?? 0);
        if ($ownerAgentId <= 0) {
            return null;
        }

        return Agent::query()->find($ownerAgentId);
    }

    public function isPlatformAdmin(): bool
    {
        return $this->account_type === AccountType::PlatformAdmin;
    }

    public function isAgencyAdmin(): bool
    {
        return $this->account_type === AccountType::AgencyAdmin;
    }

    public function isStaff(): bool
    {
        return $this->account_type === AccountType::Staff;
    }

    public function isAgent(): bool
    {
        return $this->account_type === AccountType::Agent;
    }

    public function isAgentAdmin(): bool
    {
        return $this->isAgent();
    }

    public function isAgentStaff(): bool
    {
        return $this->account_type === AccountType::AgentStaff;
    }

    public function isAgentPortalUser(): bool
    {
        return $this->isAgent() || $this->isAgentStaff();
    }

    public function hasAgentPermission(string $permission): bool
    {
        if ($this->isAgentAdmin()) {
            return true;
        }

        if (! $this->isAgentStaff()) {
            return false;
        }

        $permissions = $this->meta['agent_permissions'] ?? [];
        if (! is_array($permissions)) {
            return false;
        }

        return in_array($permission, $permissions, true);
    }

    public function hasStaffPermission(string $permission): bool
    {
        if (! $this->isStaff()) {
            return false;
        }

        if (! array_key_exists('staff_permissions', $this->meta ?? [])) {
            return true;
        }

        $permissions = $this->meta['staff_permissions'];
        if (! is_array($permissions)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    }

    /**
     * Requires a positive assignment in meta.staff_permissions (non-empty array).
     * Missing, null, or empty meta never grants access — used for strict gates such as page settings.
     */
    public function hasExplicitStaffPermission(string $permission): bool
    {
        if (! $this->isStaff()) {
            return false;
        }

        $permissions = ($this->meta ?? [])['staff_permissions'] ?? null;
        if (! is_array($permissions) || $permissions === []) {
            return false;
        }

        return in_array($permission, $permissions, true);
    }

    /**
     * @return list<string>
     */
    public function staffPermissions(): array
    {
        if (! $this->isStaff()) {
            return [];
        }

        if (! array_key_exists('staff_permissions', $this->meta ?? [])) {
            return StaffPermission::all();
        }

        $permissions = $this->meta['staff_permissions'];
        if (! is_array($permissions)) {
            return StaffPermission::all();
        }

        return array_values(array_filter(
            $permissions,
            static fn (mixed $permission): bool => is_string($permission)
                && in_array($permission, StaffPermission::all(), true),
        ));
    }

    public function usesLegacyStaffPermissions(): bool
    {
        return $this->isStaff() && ! array_key_exists('staff_permissions', $this->meta ?? []);
    }

    /**
     * Portal user IDs under the same owner agent (admin + staff). Used for shared travelers/support scope.
     *
     * @return list<int>
     */
    public function ownerAgentPortalUserIds(): array
    {
        $agent = $this->agent();
        if ($agent === null) {
            return $this->id ? [(int) $this->id] : [];
        }

        $ids = array_filter([(int) $agent->user_id, (int) $this->id]);

        $staffIds = self::query()
            ->where('account_type', AccountType::AgentStaff)
            ->where('current_agency_id', $agent->agency_id)
            ->where('meta->owner_agent_id', $agent->id)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return array_values(array_unique([...$ids, ...$staffIds]));
    }

    public function belongsToAgencyModel(Agency|int $agency): bool
    {
        $agencyId = $agency instanceof Agency ? $agency->id : $agency;

        return $this->belongsToAgency($agencyId);
    }

    public function isCustomer(): bool
    {
        return $this->account_type === AccountType::Customer;
    }

    public function isSuspended(): bool
    {
        return $this->status === UserAccountStatus::Suspended;
    }

    public function belongsToAgency(int $agencyId): bool
    {
        if ($this->isPlatformAdmin()) {
            return true;
        }

        return $this->agencies()->where('agencies.id', $agencyId)->exists();
    }

    /** @return HasMany<BookingNote, $this> */
    public function bookingNotes(): HasMany
    {
        return $this->hasMany(BookingNote::class);
    }

    /** @return HasMany<BookingPayment, $this> */
    public function submittedPayments(): HasMany
    {
        return $this->hasMany(BookingPayment::class, 'payer_user_id');
    }

    /** @return HasMany<BookingPayment, $this> */
    public function receivedPayments(): HasMany
    {
        return $this->hasMany(BookingPayment::class, 'received_by');
    }

    /** @return HasMany<BookingTicket, $this> */
    public function issuedTickets(): HasMany
    {
        return $this->hasMany(BookingTicket::class, 'issued_by');
    }

    /** @return HasMany<TicketingAttempt, $this> */
    public function ticketingAttempts(): HasMany
    {
        return $this->hasMany(TicketingAttempt::class, 'attempted_by');
    }

    /** @return HasMany<CommunicationLog, $this> */
    public function communicationLogs(): HasMany
    {
        return $this->hasMany(CommunicationLog::class);
    }

    /** @return HasMany<BookingDocument, $this> */
    public function generatedDocuments(): HasMany
    {
        return $this->hasMany(BookingDocument::class, 'generated_by');
    }

    /** @return HasMany<AgentCommissionEntry, $this> */
    public function approvedCommissionEntries(): HasMany
    {
        return $this->hasMany(AgentCommissionEntry::class, 'approved_by');
    }

    /** @return HasMany<AgentCommissionEntry, $this> */
    public function paidCommissionEntries(): HasMany
    {
        return $this->hasMany(AgentCommissionEntry::class, 'paid_by');
    }

    /** @return HasMany<AgentCommissionStatement, $this> */
    public function issuedCommissionStatements(): HasMany
    {
        return $this->hasMany(AgentCommissionStatement::class, 'issued_by');
    }

    /** @return HasMany<BookingCancellationRequest, $this> */
    public function requestedCancellations(): HasMany
    {
        return $this->hasMany(BookingCancellationRequest::class, 'requested_by');
    }

    /** @return HasMany<BookingCancellationRequest, $this> */
    public function approvedCancellations(): HasMany
    {
        return $this->hasMany(BookingCancellationRequest::class, 'approved_by');
    }

    /** @return HasMany<BookingCancellationRequest, $this> */
    public function processedCancellations(): HasMany
    {
        return $this->hasMany(BookingCancellationRequest::class, 'processed_by');
    }

    /** @return HasOne<UserProfile, $this> */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * Virtual default traveler card from account + profile (no saved_travelers row).
     *
     * @return array{
     *     first_name: string,
     *     last_name: string,
     *     full_name: string,
     *     email: string,
     *     phone: ?string,
     *     gender: ?string,
     *     date_of_birth: ?Carbon,
     *     nationality: ?string,
     *     document_type: ?string,
     *     document_number: ?string,
     *     document_expiry: ?Carbon,
     *     issuing_country: ?string,
     *     is_complete: bool,
     *     completeness_issues: list<string>
     * }
     */
    public function profileDefaultTravelerCard(): array
    {
        $profile = $this->profile;
        [$firstName, $lastName] = $this->splitDisplayName();

        $documentType = filled($profile?->passport_number) ? 'passport' : (filled($profile?->national_id) ? 'national_id' : null);
        $documentNumber = $profile?->passport_number ?? $profile?->national_id;

        $card = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => trim($this->name ?? ''),
            'email' => (string) $this->email,
            'phone' => $profile?->phone ?? $profile?->whatsapp,
            'gender' => $profile?->gender,
            'date_of_birth' => $profile?->date_of_birth,
            'nationality' => $profile?->nationality,
            'document_type' => $documentType,
            'document_number' => $documentNumber,
            'document_expiry' => $profile?->passport_expiry_date,
            'issuing_country' => $profile?->passport_issuing_country,
        ];

        $issues = [];
        foreach ([
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'date_of_birth' => 'Date of birth',
            'nationality' => 'Nationality',
            'gender' => 'Gender',
        ] as $field => $label) {
            if (! filled($card[$field])) {
                $issues[] = $label.' is missing';
            }
        }

        if (! filled($card['document_type']) || ! filled($card['document_number'])) {
            $issues[] = 'Travel document is missing';
        }

        if ($card['document_type'] === 'passport') {
            if (! filled($card['document_expiry'])) {
                $issues[] = 'Passport expiry is missing';
            }
            if (! filled($card['issuing_country'])) {
                $issues[] = 'Issuing country is missing';
            }
        }

        $card['completeness_issues'] = $issues;
        $card['is_complete'] = $issues === [];

        return $card;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function splitDisplayName(): array
    {
        $name = trim((string) ($this->name ?? ''));
        if ($name === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) === 1) {
            return [$parts[0], ''];
        }

        $last = array_pop($parts);

        return [implode(' ', $parts), $last];
    }

    public function profilePhotoUrl(): ?string
    {
        $path = $this->profile?->profile_photo_path;
        if ($path === null || trim($path) === '') {
            return null;
        }

        return asset('storage/'.ltrim($path, '/'));
    }

    public function avatarUrl(): ?string
    {
        return $this->profilePhotoUrl()
            ?? $this->socialAccounts()->orderByDesc('id')->value('avatar');
    }

    public function displayInitials(): string
    {
        $nameForInitials = trim((string) ($this->name ?? ''));
        if ($nameForInitials !== '') {
            $parts = preg_split('/\s+/u', $nameForInitials, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($parts) >= 2) {
                return mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[count($parts) - 1], 0, 1));
            }

            return mb_strtoupper(mb_substr($nameForInitials, 0, 2));
        }

        return mb_strtoupper(mb_substr((string) ($this->email ?? ''), 0, 2));
    }

    /** @return HasMany<SocialAccount, $this> */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /** @return HasMany<BookingRefund, $this> */
    public function approvedRefunds(): HasMany
    {
        return $this->hasMany(BookingRefund::class, 'approved_by');
    }

    /** @return HasMany<BookingRefund, $this> */
    public function paidRefunds(): HasMany
    {
        return $this->hasMany(BookingRefund::class, 'paid_by');
    }

    /** @return HasMany<AgencyMedia, $this> */
    public function uploadedAgencyMedia(): HasMany
    {
        return $this->hasMany(AgencyMedia::class, 'uploaded_by');
    }

    public function agentDisplayAgencyName(): string
    {
        $agent = $this->agent();
        if ($agent !== null) {
            return $agent->displayBusinessName();
        }

        return $this->defaultOtaDisplayName();
    }

    public function agentActorName(): string
    {
        $profile = $this->relationLoaded('profile') ? $this->profile : $this->profile()->first();
        if ($profile !== null) {
            $first = trim((string) ($profile->first_name ?? ''));
            $last = trim((string) ($profile->last_name ?? ''));
            $fromProfile = trim($first.' '.$last);
            if ($fromProfile !== '') {
                return $fromProfile;
            }
        }

        $name = trim((string) ($this->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $email = trim((string) ($this->email ?? ''));

        return $email !== '' ? $email : 'Account';
    }

    /**
     * Supplier remark / audit identity for agent portal actors (not sent to Sabre in this sprint).
     */
    public function agentAuditIdentity(): string
    {
        $agencyPart = $this->agent()?->auditAgencyCodePart() ?? 'UnknownAgency';
        $actorPart = $this->agentActorAuditPart();

        return mb_substr('AGT-'.$agencyPart.'-'.$actorPart, 0, 64);
    }

    /**
     * @return array{label: string, amount: string, href: string|null}|null
     */
    public function agentDropdownBalanceSummary(): ?array
    {
        if (! $this->isAgentPortalUser()) {
            return null;
        }

        $canView = $this->isAgentAdmin() || $this->hasAgentPermission(AgentPermission::WalletView);
        if (! $canView) {
            return null;
        }

        $agent = $this->agent();
        if ($agent === null) {
            return null;
        }

        return $agent->walletSummaryForPortal($this);
    }

    protected function agentActorAuditPart(): string
    {
        return Agent::auditCodePartFromLabel($this->agentActorName(), 'UnknownUser');
    }

    protected function defaultOtaDisplayName(): string
    {
        return BrandDisplayResolver::displayName();
    }
}
