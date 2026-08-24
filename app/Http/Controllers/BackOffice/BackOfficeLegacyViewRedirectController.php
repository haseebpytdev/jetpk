<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Admin\AgencyBrandingController;
use App\Http\Controllers\Admin\AgencyCommunicationSettingsController;
use App\Http\Controllers\Admin\AgencyMessageTemplateController;
use App\Http\Controllers\Admin\AgencyNotificationSettingController;
use App\Http\Controllers\Admin\FinanceAdjustmentController;
use App\Http\Controllers\Admin\PromoCodeController;
use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\DashboardBookingResource;
use App\Models\Agent;
use App\Models\AgentWalletTransaction;
use App\Models\Booking;
use App\Models\User;
use App\Support\Branding\PlatformBrandingResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Retires legacy Blade list/show GET routes in favour of the Next dashboard shell.
 * Mutation and JSON data routes remain on Laravel controllers.
 */
final class BackOfficeLegacyViewRedirectController extends Controller
{
    use RespondsWithBackOfficeJson;
    public function adminBookingsIndex(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', Booking::class);

        if ($request->string('product')->toString() === 'group') {
            return redirect()->route('admin.group-bookings.index', [
                'q' => $request->string('search')->toString() ?: null,
                'status' => $request->string('status')->toString() ?: null,
            ]);
        }

        $previewParam = $request->string('preview')->toString();
        if ($previewParam !== '') {
            $this->assertPreviewBookingAccessible($request->user(), $previewParam);
        }

        return redirect()->to($this->bookingsIndexPath('admin', $request));
    }

    public function adminBookingShow(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('view', $booking);

        return redirect()->to($this->bookingShowPath('admin', $booking, $request));
    }

    public function staffBookingsIndex(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', Booking::class);

        $previewParam = $request->string('preview')->toString();
        if ($previewParam !== '') {
            $this->assertPreviewBookingAccessible($request->user(), $previewParam);
        }

        return redirect()->to($this->bookingsIndexPath('staff', $request));
    }

    public function staffBookingShow(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('view', $booking);

        return redirect()->to($this->bookingShowPath('staff', $booking, $request));
    }

    public function adminCustomersIndex(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', User::class);

        return redirect()->to($this->customersIndexPath('admin', $request));
    }

    public function adminCustomerShow(Request $request, User $customer): RedirectResponse
    {
        $this->assertCustomerAccount($customer);
        Gate::authorize('view', $customer);

        return redirect()->to($this->customerShowPath('admin', $customer, $request));
    }

    public function adminAgentsIndex(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', Agent::class);

        return redirect()->to($this->agentsIndexPath('admin', $request));
    }

    public function adminSupportTicketsIndex(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/admin/dashboard/support', $request->query()));
    }

    public function adminSupportTicketShow(Request $request, string $ticket): RedirectResponse
    {
        $query = $request->query();
        $query['id'] = $ticket;

        return redirect()->to($this->pathWithQuery('/admin/dashboard/support', $query));
    }

    public function staffSupportTicketsIndex(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/staff/dashboard/support', $request->query()));
    }

    public function staffSupportTicketShow(Request $request, string $ticket): RedirectResponse
    {
        $query = $request->query();
        $query['id'] = $ticket;

        return redirect()->to($this->pathWithQuery('/staff/dashboard/support', $query));
    }

    public function adminSettingsIndex(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/admin/dashboard/settings', $request->query()));
    }

    public function adminMarkupsIndex(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/admin/dashboard/markups', $request->query()));
    }

    public function adminCommissionsIndex(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/admin/dashboard/commissions', $request->query()));
    }

    public function adminCommissionShow(Request $request, string $agent): RedirectResponse
    {
        $query = $request->query();
        $query['agent'] = $agent;

        return redirect()->to($this->pathWithQuery('/admin/dashboard/commissions', $query));
    }

    public function adminAgentApplicationsIndex(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/admin/dashboard/agents/applications', $request->query()));
    }

    public function adminAgentApplicationShow(Request $request, string $application): RedirectResponse
    {
        $query = $request->query();
        $query['id'] = $application;

        return redirect()->to($this->pathWithQuery('/admin/dashboard/agents/applications', $query));
    }

    public function adminApiSettingsIndex(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/admin/dashboard/integrations', $request->query()));
    }

    public function adminIntegrationsIndex(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/admin/dashboard/integrations', $request->query()));
    }

    public function adminIntegrationsAbhiPay(Request $request): RedirectResponse
    {
        $query = $request->query();
        $query['provider'] = 'abhipay';

        return redirect()->to($this->pathWithQuery('/admin/dashboard/integrations', $query));
    }

    public function adminStaffIndex(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/admin/dashboard/staff', $request->query()));
    }

    public function adminRolesPermissions(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/admin/dashboard/users/roles', $request->query()));
    }

    public function adminSystemHealth(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/admin/dashboard/system/health', $request->query()));
    }

    public function adminGoLiveChecklist(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/admin/dashboard/system/go-live', $request->query()));
    }

    public function adminUsersIndex(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/admin/dashboard/users', $request->query()));
    }

    public function adminUserShow(Request $request, string $user): RedirectResponse
    {
        $query = $request->query();
        $query['selected'] = $user;

        return redirect()->to($this->pathWithQuery('/admin/dashboard/users', $query));
    }

    public function adminDepositsIndex(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/admin/dashboard/deposits', $request->query()));
    }

    public function adminDepositShow(Request $request, string $deposit): RedirectResponse
    {
        $query = $request->query();
        $query['id'] = $deposit;

        return redirect()->to($this->pathWithQuery('/admin/dashboard/deposits', $query));
    }

    public function adminCmsPagesIndex(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/admin/dashboard/cms/pages', $request->query()));
    }

    public function adminReportsIndex(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/admin/dashboard/reports', $request->query()));
    }

    public function staffReportsIndex(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/staff/dashboard/reports', $request->query()));
    }

    public function adminAccountingIndex(Request $request): RedirectResponse|JsonResponse|View
    {
        if ($this->wantsBackOfficeJson($request)) {
            return $this->dispatchFinanceAdjustmentJson($request);
        }

        return redirect()->to($this->pathWithQuery('/admin/dashboard/accounting', $request->query()));
    }

    public function staffAccountingIndex(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/staff/dashboard/accounting', $request->query()));
    }

    public function adminGroupTicketingIndex(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/admin/dashboard/group-ticketing', $request->query()));
    }

    public function adminPageSettingsIndex(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/admin/dashboard/cms', $request->query()));
    }

    public function adminBrandingSettings(Request $request): RedirectResponse|JsonResponse|View
    {
        if ($this->wantsBackOfficeJson($request)) {
            return app(AgencyBrandingController::class)->edit($request);
        }

        return redirect()->to($this->pathWithQuery('/admin/dashboard/settings/general', $request->query()));
    }

    public function adminCommunicationsSettings(Request $request): RedirectResponse|JsonResponse|View
    {
        if ($this->wantsBackOfficeJson($request)) {
            $path = trim($request->path(), '/');
            if (preg_match('#(?:^|/)settings/communications$#', $path) === 1) {
                return app(AgencyCommunicationSettingsController::class)->index($request);
            }
            if (str_contains($path, 'notification-events')) {
                return app(AgencyNotificationSettingController::class)->index($request);
            }
            if (str_contains($path, 'communications/templates')) {
                return app(AgencyMessageTemplateController::class)->index($request);
            }
        }

        return redirect()->to($this->pathWithQuery('/admin/dashboard/settings/notifications', $request->query()));
    }

    public function adminDeploymentChecklist(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/admin/dashboard/system/go-live', $request->query()));
    }

    public function adminGuestCustomerShow(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/admin/dashboard/customers', $request->query()));
    }

    public function adminPromoCodesIndex(Request $request): RedirectResponse|JsonResponse|View
    {
        if ($this->wantsBackOfficeJson($request)) {
            return app(PromoCodeController::class)->index($request);
        }

        return redirect()->to($this->pathWithQuery('/admin/dashboard/settings/promo-codes', $request->query()));
    }

    public function adminAgenciesIndex(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/admin/dashboard/agents', $request->query()));
    }

    public function adminGroupBookingsIndex(Request $request): RedirectResponse
    {
        return redirect()->to($this->pathWithQuery('/admin/dashboard/group-ticketing', $request->query()));
    }

    private function agentsIndexPath(string $portal, Request $request): string
    {
        return $this->pathWithQuery("/{$portal}/dashboard/agents", $this->remapAgentsQuery($request->query()));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function remapAgentsQuery(array $query): array
    {
        if (isset($query['search']) && ! isset($query['q'])) {
            $query['q'] = $query['search'];
            unset($query['search']);
        }

        return $query;
    }

    private function assertCustomerAccount(User $user): void
    {
        if (! $user->isCustomer()) {
            abort(404);
        }
    }

    private function customersIndexPath(string $portal, Request $request): string
    {
        return $this->pathWithQuery("/{$portal}/dashboard/customers", $this->remapCustomersQuery($request->query()));
    }

    private function customerShowPath(string $portal, User $customer, Request $request): string
    {
        $query = $this->remapCustomersQuery($request->query());
        $query['id'] = 'CU-'.$customer->id;

        return $this->pathWithQuery("/{$portal}/dashboard/customers", $query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function remapCustomersQuery(array $query): array
    {
        if (isset($query['search']) && ! isset($query['q'])) {
            $query['q'] = $query['search'];
            unset($query['search']);
        }

        return $query;
    }

    private function assertPreviewBookingAccessible(?User $user, string $previewParam): void
    {
        if ($user === null) {
            abort(403);
        }

        $baseQuery = $this->scopedBookingsQuery($user);
        $match = ctype_digit($previewParam)
            ? (clone $baseQuery)->whereKey((int) $previewParam)->first()
            : (clone $baseQuery)->whereIn('booking_reference', PlatformBrandingResolver::lookupReferenceCandidates($previewParam))->first();

        if ($match === null) {
            abort(403);
        }

        Gate::authorize('view', $match);
    }

    private function bookingsIndexPath(string $portal, Request $request): string
    {
        $query = $this->remapBookingsQuery($request->query());

        return $this->pathWithQuery("/{$portal}/dashboard/bookings", $query);
    }

    private function bookingShowPath(string $portal, Booking $booking, Request $request): string
    {
        $publicId = DashboardBookingResource::publicId($booking);
        $query = $this->remapBookingsQuery($request->query());

        return $this->pathWithQuery("/{$portal}/dashboard/bookings/{$publicId}", $query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function remapBookingsQuery(array $query): array
    {
        if (isset($query['preview']) && ! isset($query['q'])) {
            $query['q'] = $query['preview'];
            unset($query['preview']);
        }

        if (isset($query['search']) && ! isset($query['q'])) {
            $query['q'] = $query['search'];
            unset($query['search']);
        }

        return $query;
    }

    private function dispatchFinanceAdjustmentJson(Request $request): JsonResponse|View
    {
        $controller = app(FinanceAdjustmentController::class);
        $path = trim($request->path(), '/');

        if (preg_match('#finance/adjustments/create$#', $path) === 1) {
            return $controller->create($request);
        }

        if (preg_match('#finance/adjustments/(\d+)/reverse$#', $path, $matches) === 1) {
            $transaction = AgentWalletTransaction::query()->findOrFail((int) $matches[1]);

            return $controller->reverseConfirm($request, $transaction);
        }

        if (preg_match('#finance/adjustments/(\d+)$#', $path, $matches) === 1) {
            $transaction = AgentWalletTransaction::query()->findOrFail((int) $matches[1]);

            return $controller->show($request, $transaction);
        }

        if (preg_match('#finance/adjustments$#', $path) === 1) {
            return $controller->index($request);
        }

        return $this->backOfficeJson([
            'ok' => true,
            'surface' => 'accounting',
            'message' => 'Manual wallet adjustments are available at /admin/finance/adjustments?format=json.',
            'adjustment_index' => '/admin/finance/adjustments?format=json',
            'adjustment_create' => '/admin/finance/adjustments/create?format=json',
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function pathWithQuery(string $path, array $query): string
    {
        $filtered = array_filter($query, static fn ($value) => $value !== null && $value !== '');

        if ($filtered === []) {
            return $path;
        }

        return $path.'?'.http_build_query($filtered);
    }

    private function scopedBookingsQuery(User $user): Builder
    {
        $query = Booking::query()->orderByDesc('created_at');

        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }

        return $query;
    }
}
