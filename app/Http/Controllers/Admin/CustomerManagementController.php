<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Customers\GuestCustomerService;
use App\Services\Customers\CustomerIndexMetricsService;
use App\Support\Identity\ActorIdentifier;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Admin customer CRM — list and profile for account_type=customer only.
 * Access control and role edits remain on Users & Access (UserManagementController).
 */
class CustomerManagementController extends Controller
{
    public function __construct(
        protected GuestCustomerService $guestCustomers,
        protected CustomerIndexMetricsService $customerMetrics,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $actor = $request->user();
        $segment = $request->string('segment')->toString();
        if (! in_array($segment, ['registered', 'guests'], true)) {
            $segment = 'registered';
        }

        if ($segment === 'guests') {
            $guestRows = $this->guestCustomers->paginate($actor, $request);

            return view('dashboard.admin.customers.index', [
                'segment' => $segment,
                'guestCustomers' => $guestRows,
                'customers' => collect(),
                'filters' => $request->only(['search']),
                'kpis' => [
                    'total' => 0,
                    'active' => 0,
                    'google_linked' => 0,
                    'with_bookings' => 0,
                    'profile_incomplete' => 0,
                    'guest_total' => $guestRows->total(),
                ],
            ]);
        }

        $query = $this->scopedCustomerQuery($actor)
            ->select(['id', 'name', 'email', 'status', 'created_at', 'meta'])
            ->with([
                'profile:id,user_id,phone,whatsapp',
                'socialAccounts:id,user_id,provider',
            ])
            ->withCount('bookings')
            ->withMax('bookings as last_booking_at', 'created_at');

        $this->applyIndexFilters($query, $request);

        $users = $query->orderByDesc('id')->paginate(20)->withQueryString();
        $kpis = $this->customerMetrics->registeredKpis($this->scopedCustomerQuery($actor));

        return view('dashboard.admin.customers.index', [
            'segment' => 'registered',
            'guestCustomers' => null,
            'customers' => $users,
            'filters' => $request->only(['search', 'status', 'google_linked', 'created_from', 'created_to']),
            'kpis' => array_merge($kpis, ['guest_total' => 0]),
        ]);
    }

    public function showGuest(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $email = trim((string) $request->query('email', ''));
        $phone = trim((string) $request->query('phone', ''));
        $guestId = (int) $request->query('guest_id', 0);
        $bookings = $this->guestCustomers->bookingsForGuest($request->user(), $email, $phone);
        $guestRecord = [
            'guest_id' => $guestId,
            'first_name' => (string) $request->query('first_name', ''),
            'email' => $email,
            'phone' => $phone,
        ];

        return view('dashboard.admin.customers.guest-show', [
            'guest' => $guestRecord,
            'guestIdentifier' => ActorIdentifier::forGuest($guestRecord),
            'bookings' => $bookings,
        ]);
    }

    public function show(Request $request, User $customer): View
    {
        Gate::authorize('view', $customer);

        $customer->load(['profile', 'socialAccounts']);

        $activeTab = $this->resolveTab($request->string('tab')->toString());

        $bookings = $customer->bookings()
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $savedTravelers = $customer->savedTravelers()
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        $supportTickets = $customer->supportTicketsCreated()
            ->with(['booking'])
            ->orderByDesc('last_reply_at')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $profileTravelerCard = $customer->profileDefaultTravelerCard();

        return view('dashboard.admin.customers.show', [
            'customer' => $customer,
            'customerIdentifier' => ActorIdentifier::forUser($customer),
            'activeTab' => $activeTab,
            'bookings' => $bookings,
            'savedTravelers' => $savedTravelers,
            'supportTickets' => $supportTickets,
            'profileTravelerCard' => $profileTravelerCard,
            'phone' => $this->customerPhone($customer),
            'googleLinked' => $customer->socialAccounts->contains(fn ($account): bool => $account->provider === 'google'),
        ]);
    }

    /**
     * @return Builder<User>
     */
    protected function scopedCustomerQuery(User $actor): Builder
    {
        $query = User::query()->where('account_type', AccountType::Customer);

        if (! $actor->isPlatformAdmin()) {
            $query->where('current_agency_id', $actor->current_agency_id);
        }

        return $query;
    }

    /**
     * @param  Builder<User>  $query
     */
    protected function applyIndexFilters(Builder $query, Request $request): void
    {
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('meta->phone', 'like', '%'.$search.'%')
                    ->orWhereHas('profile', function (Builder $profile) use ($search): void {
                        $profile->where('phone', 'like', '%'.$search.'%')
                            ->orWhere('whatsapp', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $googleLinked = $request->string('google_linked')->toString();
        if ($googleLinked === 'yes') {
            $query->whereHas('socialAccounts', fn (Builder $q): Builder => $q->where('provider', 'google'));
        } elseif ($googleLinked === 'no') {
            $query->whereDoesntHave('socialAccounts', fn (Builder $q): Builder => $q->where('provider', 'google'));
        }

        if ($request->filled('created_from')) {
            $query->whereDate('created_at', '>=', $request->string('created_from')->toString());
        }

        if ($request->filled('created_to')) {
            $query->whereDate('created_at', '<=', $request->string('created_to')->toString());
        }
    }

    /**
     * Approximate profile-incomplete count (aligned with key UserProfile fields).
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    protected function applyProfileIncompleteScope(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereDoesntHave('profile')
                ->orWhereHas('profile', function (Builder $profile): void {
                    $profile->whereNull('date_of_birth')
                        ->orWhereNull('nationality')
                        ->orWhereNull('gender')
                        ->orWhere(function (Builder $inner): void {
                            $inner->whereNull('passport_number')->whereNull('national_id');
                        });
                });
        });
    }

    protected function customerPhone(User $user): ?string
    {
        $profile = $user->profile;
        $phone = trim((string) ($profile?->phone ?? $profile?->whatsapp ?? ''));
        if ($phone !== '') {
            return $phone;
        }

        $metaPhone = $user->meta['phone'] ?? null;

        return filled($metaPhone) ? (string) $metaPhone : null;
    }

    protected function resolveTab(string $tab): string
    {
        $allowed = ['overview', 'bookings', 'travelers', 'support', 'security'];

        return in_array($tab, $allowed, true) ? $tab : 'overview';
    }
}
