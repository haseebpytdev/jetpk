<?php

namespace App\Http\Controllers;

use App\Enums\SupplierProvider;
use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Models\Booking;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsTicketingReadiness;
use App\Services\Suppliers\TicketingService;
use App\Support\BackOffice\BackOfficeBookingPresenter;
use App\Support\BackOffice\BackOfficeCapabilitiesPresenter;
use App\Support\Bookings\AdminPiaNdcTicketingPresenter;
use App\Support\Bookings\AdminSabreGdsTicketingPanelsPresenter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BookingTicketingController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function __construct(
        protected TicketingService $ticketingService,
        protected AdminPiaNdcTicketingPresenter $adminPiaNdcTicketingPresenter,
        protected AdminSabreGdsTicketingPanelsPresenter $adminSabreGdsTicketingPanelsPresenter,
        protected BackOfficeCapabilitiesPresenter $capabilitiesPresenter,
    ) {}

    public function issue(Request $request, Booking $booking): RedirectResponse|JsonResponse
    {
        try {
            Gate::authorize('issueTicket', $booking);
        } catch (AuthorizationException $exception) {
            Log::info('ticketing.permission_denied', [
                'booking_id' => $booking->id,
                'agency_id' => $booking->agency_id,
                'actor_id' => $request->user()?->id,
            ]);
            throw $exception;
        }

        $booking->loadMissing('latestSupplierBooking');
        $provider = strtolower(trim((string) ($booking->latestSupplierBooking?->provider ?? $booking->supplier ?? '')));
        $adminManualOverride = false;
        $sabreGdsAdminIssue = false;
        if ($provider === SupplierProvider::PiaNdc->value) {
            $request->validate([
                'admin_confirm_reviewed' => ['accepted'],
            ]);

            $ticketingEligible = $this->ticketingService->isBookingEligibleForTicketing($booking);
            $panel = $this->adminPiaNdcTicketingPresenter->panel($booking, $ticketingEligible);
            if (! ($panel['can_issue'] ?? false)) {
                $message = (string) ($panel['issue_blocked_reason'] ?? 'Issue ticket is not available.');

                if ($this->wantsBackOfficeJson($request)) {
                    return $this->backOfficeJsonError($message, 422, 'ticketing_blocked');
                }

                return back()->withErrors([
                    'ticketing' => $message,
                ]);
            }

            if (! $request->boolean('admin_confirm_reviewed')) {
                throw ValidationException::withMessages([
                    'ticketing' => 'Confirm you reviewed this booking before continuing.',
                ]);
            }

            $adminManualOverride = true;
        } elseif ($provider === SupplierProvider::Sabre->value) {
            $gdsPanel = $this->adminSabreGdsTicketingPanelsPresenter->gdsTicketingPanel($booking);
            if ($gdsPanel['show'] ?? false) {
                $request->validate([
                    'ticketing_confirm' => ['required', 'string'],
                ]);

                if (($gdsPanel['action_state'] ?? '') === SabreGdsTicketingReadiness::ACTION_ISSUE_TICKET
                    && ($gdsPanel['can_execute'] ?? false)) {
                    if (! SabreGdsTicketingReadiness::confirmPhraseMatches($booking, $request->string('ticketing_confirm')->toString())) {
                        throw ValidationException::withMessages([
                            'ticketing' => 'Exact confirmation phrase required: ISSUE-TICKET-FOR-BOOKING-'.$booking->id,
                        ]);
                    }
                    $sabreGdsAdminIssue = true;
                } else {
                    $message = (string) ($gdsPanel['admin_message'] ?? 'Sabre GDS issue ticket is not available for this booking.');

                    if ($this->wantsBackOfficeJson($request)) {
                        return $this->backOfficeJsonError($message, 422, 'ticketing_blocked');
                    }

                    return back()->withErrors([
                        'ticketing' => $message,
                    ]);
                }
            }
        }

        $result = $this->ticketingService->issueTickets($booking, $request->user(), $adminManualOverride, $sabreGdsAdminIssue);
        if (! $result->success) {
            $message = $result->error_message ?: ($result->warnings[0] ?? 'Ticket issuance failed.');
            $code = $result->error_code === 'already_ticketed' ? 'already_ticketed' : 'ticketing_failed';
            $status = $code === 'already_ticketed' ? 409 : 422;

            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($message, $status, $code);
            }

            return back()->withErrors([
                'ticketing' => $message,
            ]);
        }

        $booking->refresh();
        $executionState = ($booking->ticketing_status ?? '') === 'ticketed' ? 'success' : 'pending_reconciliation';

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => $executionState === 'success'
                    ? 'Tickets issued successfully.'
                    : 'Ticketing requires manual review before the booking is ticketed.',
                'execution_state' => $executionState,
                'booking' => BackOfficeBookingPresenter::present($booking),
                'capabilities' => $this->capabilitiesPresenter->presentBookingTicketingCapabilities($request->user(), $booking),
            ]);
        }

        return back()->with('status', 'tickets-issued');
    }
}
