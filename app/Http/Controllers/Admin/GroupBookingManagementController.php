<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GroupBookingStatus;
use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Models\GroupBooking;
use App\Models\GroupBookingUserRestriction;
use App\Models\User;
use App\Services\GroupTicketing\GroupBookingRestrictionService;
use App\Services\GroupTicketing\GroupReservationService;
use App\Support\GroupTicketing\GroupBookingListPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin dashboard for group bookings only (no Sabre PNR/ticketing panels).
 */
class GroupBookingManagementController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function __construct(
        protected GroupReservationService $reservationService,
        protected GroupBookingRestrictionService $restrictionService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('platform.admin');

        $query = GroupBooking::query()->with(['inventory', 'user'])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $status = GroupBookingStatus::tryFrom($request->string('status')->toString());
            if ($status !== null) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(function ($q) use ($term): void {
                $q->where('reference', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term));
            });
        }

        $bookings = $query->paginate(25)->withQueryString();

        return view('dashboard.admin.group-bookings.index', [
            'bookings' => $bookings,
            'filters' => $request->only(['status', 'q']),
            'statuses' => GroupBookingStatus::cases(),
        ]);
    }

    public function show(GroupBooking $groupBooking): View
    {
        Gate::authorize('platform.admin');

        $groupBooking->load(['inventory', 'user', 'passengers', 'adminPaymentVerifiedBy']);

        $userRestriction = $groupBooking->user
            ? GroupBookingUserRestriction::query()->where('user_id', $groupBooking->user_id)->first()
            : null;

        return view('dashboard.admin.group-bookings.show', [
            'booking' => $groupBooking,
            'listRow' => GroupBookingListPresenter::toListRow($groupBooking),
            'userRestriction' => $userRestriction,
            'supplierReleasePanel' => $groupBooking->needsSupplierReleaseReconciliation()
                ? GroupBookingListPresenter::supplierReleasePanel($groupBooking)
                : null,
        ]);
    }

    public function restrictions(): View
    {
        Gate::authorize('platform.admin');

        $restrictions = GroupBookingUserRestriction::query()
            ->with(['user', 'resetByUser'])
            ->whereNotNull('blocked_at')
            ->whereNull('reset_at')
            ->orderByDesc('blocked_at')
            ->paginate(25);

        return view('dashboard.admin.group-bookings.restrictions', [
            'restrictions' => $restrictions,
        ]);
    }

    public function resetRestriction(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('platform.admin');

        $request->validate(['reset_note' => ['nullable', 'string', 'max:500']]);

        $this->restrictionService->reset($user, $request->user(), $request->input('reset_note'));

        return back()->with('success', 'Group booking restriction reset for '.$user->email.'.');
    }

    public function verifyPayment(Request $request, GroupBooking $groupBooking): RedirectResponse|JsonResponse
    {
        Gate::authorize('platform.admin');

        try {
            $this->reservationService->verifyPayment($groupBooking, auth()->user());
        } catch (\InvalidArgumentException $e) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($e->getMessage(), 409, 'payment_blocked');
            }

            return back()->withErrors(['payment' => $e->getMessage()]);
        } catch (\Throwable $exception) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($exception->getMessage(), 422, 'payment_failed');
            }

            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        $groupBooking->refresh();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'group_booking' => $this->presentGroupBooking($groupBooking),
            ]);
        }

        return back()->with('success', 'Payment verified and booking confirmed.');
    }

    public function rejectPayment(Request $request, GroupBooking $groupBooking): RedirectResponse|JsonResponse
    {
        Gate::authorize('platform.admin');

        $this->validateBackOffice($request, ['rejection_note' => ['nullable', 'string', 'max:500']]);

        try {
            $this->reservationService->rejectPayment($groupBooking, auth()->user(), $request->input('rejection_note'));
        } catch (\InvalidArgumentException $e) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($e->getMessage(), 409, 'payment_blocked');
            }

            return back()->withErrors(['payment' => $e->getMessage()]);
        } catch (\Throwable $exception) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($exception->getMessage(), 422, 'payment_failed');
            }

            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        $groupBooking->refresh();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'group_booking' => $this->presentGroupBooking($groupBooking),
            ]);
        }

        return back()->with('success', 'Payment rejected and booking released.');
    }

    public function retrySupplierRelease(Request $request, GroupBooking $groupBooking): RedirectResponse|JsonResponse
    {
        Gate::authorize('platform.admin');

        try {
            $this->reservationService->retrySupplierRelease($groupBooking);
        } catch (\Throwable $exception) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($exception->getMessage(), 422, 'supplier_release_retry_failed');
            }

            return back()->withErrors(['supplier_release' => $exception->getMessage()]);
        }

        $groupBooking->refresh();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'group_booking' => $this->presentGroupBooking($groupBooking),
            ]);
        }

        return back()->with('success', 'Supplier release retry completed.');
    }

    public function reconcileManualSupplierCancel(Request $request, GroupBooking $groupBooking): RedirectResponse|JsonResponse
    {
        Gate::authorize('platform.admin');

        $this->validateBackOffice($request, [
            'confirmation' => ['required', 'string', 'in:SUPPLIER_MANUAL_CANCEL_CONFIRMED'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->reservationService->reconcileAfterManualSupplierCancel(
                $groupBooking,
                $request->input('note', 'owner_manual_supplier_cancel'),
            );
        } catch (\Throwable $exception) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($exception->getMessage(), 422, 'manual_reconcile_failed');
            }

            return back()->withErrors(['supplier_release' => $exception->getMessage()]);
        }

        $groupBooking->refresh();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'group_booking' => $this->presentGroupBooking($groupBooking),
            ]);
        }

        return back()->with('success', 'Local booking reconciled after confirmed manual supplier cancel.');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentGroupBooking(GroupBooking $groupBooking): array
    {
        $panel = $groupBooking->needsSupplierReleaseReconciliation()
            ? $groupBooking->supplierReleaseReconciliationPanel()
            : null;

        return [
            'id' => (string) $groupBooking->id,
            'reference' => $groupBooking->reference,
            'status' => $groupBooking->status->value,
            'seat_count' => (int) $groupBooking->seat_count,
            'supplier_reservation_id' => $groupBooking->supplier_reservation_id,
            'supplier_release_panel' => $panel,
        ];
    }
}
