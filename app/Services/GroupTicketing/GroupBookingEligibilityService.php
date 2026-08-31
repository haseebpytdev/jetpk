<?php

namespace App\Services\GroupTicketing;

use App\Models\User;
use App\Services\Commerce\CommerceCheckoutSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Authoritative Group checkout eligibility (auth + role + customer_group_booking_enabled).
 * Independent of normal Flight guest_booking_enabled.
 */
final class GroupBookingEligibilityService
{
    public function __construct(
        private readonly CommerceCheckoutSettingsService $commerceCheckoutSettings,
    ) {}

    /**
     * @return array{
     *   eligible: bool,
     *   reason: string,
     *   message: string,
     *   customer_group_booking_enabled: bool
     * }
     */
    public function evaluate(?User $user, ?int $agencyId = null): array
    {
        $customerGroupEnabled = $this->commerceCheckoutSettings->isCustomerGroupBookingEnabled($agencyId);

        if ($user === null) {
            return [
                'eligible' => false,
                'reason' => 'auth_required',
                'message' => 'Sign in to continue to group checkout.',
                'customer_group_booking_enabled' => $customerGroupEnabled,
            ];
        }

        if ($user->isSuspended()) {
            return [
                'eligible' => false,
                'reason' => 'inactive',
                'message' => 'Your account cannot book group offers right now.',
                'customer_group_booking_enabled' => $customerGroupEnabled,
            ];
        }

        // Approved agent portal users remain eligible regardless of the customer switch.
        if ($user->isAgentPortalUser()) {
            return [
                'eligible' => true,
                'reason' => 'agent',
                'message' => '',
                'customer_group_booking_enabled' => $customerGroupEnabled,
            ];
        }

        // Preserve existing staff/admin booking authority (not redefined by R7).
        if ($user->isStaff() || $user->isPlatformAdmin() || $user->isAgencyAdmin()) {
            return [
                'eligible' => true,
                'reason' => 'staff',
                'message' => '',
                'customer_group_booking_enabled' => $customerGroupEnabled,
            ];
        }

        if ($user->isCustomer()) {
            if ($customerGroupEnabled) {
                return [
                    'eligible' => true,
                    'reason' => 'customer',
                    'message' => '',
                    'customer_group_booking_enabled' => $customerGroupEnabled,
                ];
            }

            return [
                'eligible' => false,
                'reason' => 'customer_group_booking_disabled',
                'message' => 'This Group offer is currently available for registered JetPakistan Agents.',
                'customer_group_booking_enabled' => false,
            ];
        }

        return [
            'eligible' => false,
            'reason' => 'role_denied',
            'message' => 'Your account cannot book this Group offer.',
            'customer_group_booking_enabled' => $customerGroupEnabled,
        ];
    }

    public function gateResponse(Request $request, ?User $user, ?int $agencyId = null): RedirectResponse|JsonResponse|null
    {
        $result = $this->evaluate($user, $agencyId);
        if ($result['eligible']) {
            return null;
        }

        if ($request->wantsJson() || $request->query('format') === 'json') {
            $status = $result['reason'] === 'auth_required' ? 401 : 403;

            return response()->json([
                'success' => false,
                'ok' => false,
                'status' => $result['reason'],
                'message' => $result['message'],
                'customer_group_booking_enabled' => $result['customer_group_booking_enabled'],
                'redirect_url' => $result['reason'] === 'auth_required'
                    ? '/login?redirect='.rawurlencode($request->getRequestUri())
                    : null,
            ], $status);
        }

        if ($result['reason'] === 'auth_required') {
            return redirect()->guest('/login?redirect='.rawurlencode($request->getRequestUri()));
        }

        return redirect()
            ->route('group-ticketing.search')
            ->with('warning', $result['message']);
    }
}
