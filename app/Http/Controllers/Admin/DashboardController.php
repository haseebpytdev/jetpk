<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupplierProvider;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Dashboard\AgencyDashboardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected AgencyDashboardService $dashboardService,
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Booking::class);

        $user = auth()->user();
        $data = $this->dashboardService->build($user);
        $commandCenter = $this->dashboardService->buildAdminCommandCenter($user);
        $supplierReadiness = $this->buildSupplierReadiness($user);
        $supplierHealth = $this->buildSupplierHealth($user);

        return view(client_view('index', 'admin'), [
            'role' => 'Admin',
            'stats' => $data['stats'],
            'recentBookings' => $data['recentBookings'],
            'todayOperations' => $data['todayOperations'],
            'revenueSnapshot' => $data['revenueSnapshot'],
            'hasLiveData' => $data['hasLiveData'],
            'supplierReadiness' => $supplierReadiness,
            'supplierHealth' => $supplierHealth,
            'operationalKpis' => $data['operationalKpis'],
            'needsAttention' => $data['needsAttention'],
            'commandSummary' => $data['commandSummary'],
            'taskActions' => $data['taskActions'],
            'pnrHealth' => $commandCenter['pnrHealth'],
            'paymentCollection' => $commandCenter['paymentCollection'],
            'staffWorkload' => $commandCenter['staffWorkload'],
            'agentPerformance' => $commandCenter['agentPerformance'],
            'recentSupplierFailures' => $commandCenter['recentSupplierFailures'],
            'adminQuickActions' => $commandCenter['adminQuickActions'],
            'supportAlerts' => $this->dashboardService->buildSupportAlerts($user, 'admin'),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildSupplierReadiness(User $user): Collection
    {
        return $this->scopedSupplierConnectionsQuery($user)
            ->orderBy('provider')
            ->get()
            ->map(function (SupplierConnection $connection): array {
                $provider = is_string($connection->provider)
                    ? $connection->provider
                    : $connection->provider->value;

                return [
                    'name' => strtoupper($provider),
                    'code' => strtoupper($provider),
                    'readiness' => $connection->is_active ? 'connected' : 'not_configured',
                    'detail' => $connection->is_active
                        ? 'Provider configured and active.'
                        : 'Provider saved but currently disabled.',
                ];
            });
    }

    /**
     * Build a provider-aware supplier health view, including providers
     * that have no SupplierConnection row yet (so the operator sees
     * "Not configured" rather than absence).
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildSupplierHealth(User $user): Collection
    {
        $connections = $this->scopedSupplierConnectionsQuery($user)
            ->with(['latestSearchDiagnostic', 'latestReadinessDiagnostic'])
            ->get()
            ->keyBy(fn (SupplierConnection $c) => is_string($c->provider) ? $c->provider : $c->provider->value);

        $providerLabels = [
            SupplierProvider::Duffel->value => 'Duffel',
            SupplierProvider::Sabre->value => 'Sabre',
            SupplierProvider::PiaNdc->value => 'PIA NDC',
            SupplierProvider::AirlineDirect->value => 'Airline Direct',
            SupplierProvider::Amadeus->value => 'Amadeus',
            SupplierProvider::Travelport->value => 'Travelport',
        ];

        $orderedProviders = [
            SupplierProvider::Duffel->value,
            SupplierProvider::Sabre->value,
            SupplierProvider::PiaNdc->value,
            SupplierProvider::AirlineDirect->value,
            SupplierProvider::Amadeus->value,
            SupplierProvider::Travelport->value,
        ];

        return collect($orderedProviders)->map(function (string $providerKey) use ($connections, $providerLabels): array {
            $label = $providerLabels[$providerKey] ?? strtoupper($providerKey);
            /** @var SupplierConnection|null $connection */
            $connection = $connections->get($providerKey);

            if ($connection === null) {
                return [
                    'name' => $label,
                    'code' => strtoupper($providerKey),
                    'status' => 'not_configured',
                    'status_label' => 'Not configured',
                    'is_active' => false,
                    'last_search_at' => null,
                    'last_validation_at' => null,
                    'last_error' => null,
                    'manage_route' => 'admin.api-settings',
                    'manage_route_params' => [],
                    'detail' => 'No supplier connection saved for this provider.',
                ];
            }

            $latestSearch = $connection->latestSearchDiagnostic;
            $latestReadiness = $connection->latestReadinessDiagnostic;
            $lastError = (string) ($connection->last_error ?? '');

            $status = match (true) {
                ! $connection->is_active => 'disabled',
                $lastError !== '' => 'error',
                ($latestReadiness?->status ?? '') === 'failed' => 'error',
                default => 'connected',
            };

            $statusLabel = match ($status) {
                'connected' => 'Connected',
                'disabled' => 'Disabled',
                'error' => 'Error',
                default => 'Needs setup',
            };

            return [
                'name' => $label,
                'code' => strtoupper($providerKey),
                'status' => $status,
                'status_label' => $statusLabel,
                'is_active' => (bool) $connection->is_active,
                'last_search_at' => $latestSearch?->created_at?->diffForHumans(),
                'last_validation_at' => $latestReadiness?->created_at?->diffForHumans()
                    ?? $connection->last_tested_at?->diffForHumans(),
                'last_error' => $lastError !== '' ? $lastError : null,
                'manage_route' => 'admin.api-settings.edit',
                'manage_route_params' => ['supplierConnection' => $connection->id],
                'detail' => $connection->is_active
                    ? 'Provider configured and active.'
                    : 'Provider saved but currently disabled.',
            ];
        });
    }

    /**
     * @return Builder<SupplierConnection>
     */
    protected function scopedSupplierConnectionsQuery(User $user): Builder
    {
        $query = SupplierConnection::query();
        if ($user->isPlatformAdmin()) {
            return $query;
        }

        return $query->where('agency_id', $user->current_agency_id);
    }
}
