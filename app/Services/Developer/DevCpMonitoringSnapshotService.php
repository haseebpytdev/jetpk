<?php

namespace App\Services\Developer;

use App\Enums\GroupBookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\GroupBooking;
use App\Models\GroupInventory;
use App\Models\PlatformModuleSettingChange;
use App\Models\SecurityEvent;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\SupplierConnectionService;
use App\Support\Platform\PlatformModuleGate;
use App\Support\Platform\PlatformModuleRegistry;
use App\Support\Sabre\SabreCapabilityPosture;
use App\Support\Security\SensitiveDataRedactor;
use App\Support\Suppliers\SabreSupplierChannelConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only snapshots for Developer CP monitoring panels (no secrets).
 */
class DevCpMonitoringSnapshotService
{
    /**
     * @return array<string, mixed>
     */
    public function overviewStats(): array
    {
        return [
            'agencies' => Agency::query()->count(),
            'users' => User::query()->count(),
            'platform_admins' => User::query()->where('account_type', 'platform_admin')->count(),
            'security_events_24h' => SecurityEvent::query()->where('created_at', '>=', now()->subDay())->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function systemHealth(): array
    {
        return [
            'app_debug' => (bool) config('app.debug'),
            'app_env' => (string) config('app.env'),
            'database' => $this->databaseHealth(),
            'failed_jobs' => $this->failedJobsSummary(),
            'recent_errors' => $this->recentLogErrors(),
            'scheduler' => $this->schedulerChecklist(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sabreStatus(): array
    {
        try {
            return $this->buildSabreStatusSnapshot();
        } catch (\Throwable $e) {
            Log::warning('dev_cp_sabre_status_snapshot_failed', [
                'message' => $e->getMessage(),
            ]);

            return $this->minimalSabreStatusSnapshot([
                'Sabre status snapshot unavailable — showing safe defaults only.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSabreStatusSnapshot(): array
    {
        $connectionService = app(SupplierConnectionService::class);
        $posture = app(SabreCapabilityPosture::class);

        $connections = SupplierConnection::query()
            ->where('provider', SupplierProvider::Sabre)
            ->orderBy('agency_id')
            ->orderBy('id')
            ->get(['id', 'agency_id', 'name', 'is_active', 'status', 'environment', 'base_url', 'credentials', 'settings']);

        $connectionRows = $connections->map(function (SupplierConnection $c) use ($connectionService): array {
            $baseUrl = trim((string) ($c->base_url ?: config('suppliers.sabre.default_base_url', '')));
            $channelConfig = SabreSupplierChannelConfig::fromConnection($c);

            return [
                'id' => $c->id,
                'agency_id' => $c->agency_id,
                'name' => $c->name,
                'is_active' => $c->is_active,
                'status' => $c->status?->value ?? (string) $c->status,
                'environment' => $c->environment?->value ?? (string) $c->environment,
                'base_host' => $this->extractHostFromUrl($baseUrl),
                'credential_keys_present' => $connectionService->credentialKeysPresent($c),
                'token_config_present' => $connectionService->credentialKeysPresent($c),
                'sabre_gds_enabled' => $channelConfig->gdsEnabled,
                'sabre_ndc_enabled' => $channelConfig->ndcEnabled,
            ];
        })->all();

        $warnings = [];
        if ($connectionRows === []) {
            $warnings[] = 'No Sabre supplier connections configured.';
        }

        $primaryConnection = null;
        foreach ($connectionRows as $row) {
            if ($row['is_active'] === true) {
                $primaryConnection = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'agency_id' => $row['agency_id'],
                    'environment' => $row['environment'],
                    'status' => $row['status'],
                    'base_host' => $row['base_host'],
                    'credential_keys_present' => $row['credential_keys_present'],
                    'sabre_gds_enabled' => $row['sabre_gds_enabled'] ?? true,
                    'sabre_ndc_enabled' => $row['sabre_ndc_enabled'] ?? false,
                ];
                break;
            }
        }

        if ($connectionRows !== [] && $primaryConnection === null) {
            $warnings[] = 'Sabre connections exist but none are marked active.';
        }

        if ($primaryConnection !== null && ($primaryConnection['credential_keys_present'] ?? false) !== true) {
            $warnings[] = 'Primary active Sabre connection is missing required credential keys.';
        }

        if ($primaryConnection !== null
            && ($primaryConnection['sabre_gds_enabled'] ?? true) === false
            && ($primaryConnection['sabre_ndc_enabled'] ?? false) === false) {
            $warnings[] = 'Primary active Sabre connection has both GDS and NDC channels disabled.';
        }

        $recentFailures = [];
        if (Schema::hasTable('supplier_booking_attempts')) {
            $recentFailures = DB::table('supplier_booking_attempts')
                ->where('provider', 'sabre')
                ->whereIn('status', ['failed', 'error'])
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(['id', 'booking_id', 'status', 'error_code', 'error_message', 'safe_summary', 'created_at'])
                ->map(function ($row): array {
                    $rawSummary = $row->safe_summary;
                    if (is_string($rawSummary)) {
                        $decoded = json_decode($rawSummary, true);
                        $rawSummary = is_array($decoded) ? $decoded : null;
                    }
                    $safeSummaryArray = is_array($rawSummary)
                        ? SensitiveDataRedactor::sanitizeSupplierSummary($rawSummary)
                        : null;
                    $safeSummaryText = is_array($safeSummaryArray)
                        ? $this->truncate(json_encode($safeSummaryArray, JSON_UNESCAPED_UNICODE) ?: '', 200)
                        : null;
                    $message = $safeSummaryText !== null && $safeSummaryText !== ''
                        ? $safeSummaryText
                        : $this->truncate((string) (SensitiveDataRedactor::sanitizeErrorMessage((string) ($row->error_message ?? '')) ?? ''), 120);

                    return [
                        'id' => $row->id,
                        'booking_id' => $row->booking_id,
                        'status' => $row->status,
                        'error_code' => $row->error_code,
                        'error_message' => $message,
                        'safe_summary' => $safeSummaryText,
                        'created_at' => $row->created_at,
                    ];
                })
                ->all();
        }

        $configFlags = [
            'booking_enabled' => (bool) config('suppliers.sabre.booking_enabled'),
            'ticketing_enabled' => (bool) config('suppliers.sabre.ticketing_enabled'),
            'cancel_enabled' => (bool) config('suppliers.sabre.cancel_enabled'),
            'cancel_live_call_enabled' => (bool) config('suppliers.sabre.cancel_live_call_enabled'),
            'booking_live_call_enabled' => (bool) config('suppliers.sabre.booking_live_call_enabled'),
            'verified_multiseg_auto_pnr_enabled' => (bool) config('suppliers.sabre.verified_multiseg_auto_pnr_enabled'),
            'cpnr_connecting_same_carrier_gds_enabled' => (bool) config('suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled'),
            'cpnr_connecting_same_carrier_public_checkout_enabled' => (bool) config('suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled'),
        ];

        return [
            'sabre_gds_enabled' => PlatformModuleGate::routeEnabled('sabre_gds'),
            'sabre_ndc_enabled' => PlatformModuleGate::routeEnabled('sabre_ndc'),
            'booking_enabled' => $configFlags['booking_enabled'],
            'ticketing_enabled' => $configFlags['ticketing_enabled'],
            'config_flags' => $configFlags,
            'primary_connection' => $primaryConnection,
            'warnings' => $warnings,
            'connections' => $connectionRows,
            'mutation_policy' => $posture->mutationPolicySummary(),
            'route_readiness' => [
                'admin_sync_pnr_itinerary_registered' => Route::has('admin.bookings.sync-pnr-itinerary'),
                'staff_sync_pnr_itinerary_registered' => Route::has('staff.bookings.sync-pnr-itinerary'),
                'admin_supplier_booking_registered' => Route::has('admin.bookings.supplier-booking'),
                'staff_supplier_booking_registered' => Route::has('staff.bookings.supplier-booking'),
                'admin_prepare_supplier_pnr_context_registered' => Route::has('admin.bookings.prepare-supplier-pnr-context'),
                'staff_prepare_supplier_pnr_context_registered' => Route::has('staff.bookings.prepare-supplier-pnr-context'),
            ],
            'controlled_pnr_lane' => [
                'lane_exists' => true,
                'readiness_command' => 'sabre:controlled-pnr-readiness',
                'context_command' => 'sabre:controlled-pnr-context',
                'create_command' => 'sabre:controlled-create-pnr',
                'requires_explicit_confirmation' => true,
                'public_auto_pnr_enabled' => (bool) config('suppliers.sabre.verified_multiseg_auto_pnr_enabled', false)
                    && (bool) config('suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled', false),
                'ticketing_enabled' => $configFlags['ticketing_enabled'],
                'cancellation_enabled' => $configFlags['cancel_enabled'],
                'booking_live_call_enabled' => $configFlags['booking_live_call_enabled'],
            ],
            'live_supplier_call_attempted' => false,
            'recent_failures' => $recentFailures,
        ];
    }

    /**
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    private function minimalSabreStatusSnapshot(array $warnings): array
    {
        return [
            'sabre_gds_enabled' => false,
            'sabre_ndc_enabled' => false,
            'booking_enabled' => false,
            'ticketing_enabled' => false,
            'config_flags' => [],
            'primary_connection' => null,
            'warnings' => $warnings,
            'connections' => [],
            'mutation_policy' => [],
            'route_readiness' => [
                'admin_sync_pnr_itinerary_registered' => Route::has('admin.bookings.sync-pnr-itinerary'),
                'staff_sync_pnr_itinerary_registered' => Route::has('staff.bookings.sync-pnr-itinerary'),
                'admin_supplier_booking_registered' => Route::has('admin.bookings.supplier-booking'),
                'staff_supplier_booking_registered' => Route::has('staff.bookings.supplier-booking'),
                'admin_prepare_supplier_pnr_context_registered' => Route::has('admin.bookings.prepare-supplier-pnr-context'),
                'staff_prepare_supplier_pnr_context_registered' => Route::has('staff.bookings.prepare-supplier-pnr-context'),
            ],
            'controlled_pnr_lane' => [
                'lane_exists' => true,
                'readiness_command' => 'sabre:controlled-pnr-readiness',
                'context_command' => 'sabre:controlled-pnr-context',
                'create_command' => 'sabre:controlled-create-pnr',
                'requires_explicit_confirmation' => true,
                'public_auto_pnr_enabled' => false,
                'ticketing_enabled' => false,
                'cancellation_enabled' => false,
                'booking_live_call_enabled' => false,
            ],
            'live_supplier_call_attempted' => false,
            'recent_failures' => [],
        ];
    }

    private function extractHostFromUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '—';
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : '—';
    }

    /**
     * @return array<string, mixed>
     */
    public function groupTicketingStatus(): array
    {
        $inventoryCount = GroupInventory::query()->count();
        $lastSynced = GroupInventory::query()->max('updated_at');

        $statusCounts = [];
        foreach (GroupBookingStatus::cases() as $status) {
            $statusCounts[$status->value] = GroupBooking::query()->where('status', $status)->count();
        }

        $recentIssues = GroupBooking::query()
            ->whereIn('status', [
                GroupBookingStatus::SupplierReleaseFailed,
                GroupBookingStatus::Expired,
                GroupBookingStatus::Cancelled,
            ])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['id', 'reference', 'status', 'release_reason', 'updated_at'])
            ->map(fn (GroupBooking $b): array => [
                'id' => $b->id,
                'reference' => $b->reference,
                'status' => $b->status?->value,
                'release_reason' => $this->truncate((string) ($b->release_reason ?? ''), 80),
                'updated_at' => $b->updated_at?->toIso8601String(),
            ])
            ->all();

        return [
            'inventory_count' => $inventoryCount,
            'last_inventory_sync' => $lastSynced,
            'status_counts' => $statusCounts,
            'scheduled_commands' => [
                'group-ticketing:sync-inventory' => 'daily 02:00',
                'group-ticketing:release-expired' => 'every minute',
            ],
            'recent_issues' => $recentIssues,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardsStatus(): array
    {
        $portals = [
            'admin' => ['module' => 'admin_portal', 'label' => 'Admin dashboard'],
            'staff' => ['module' => 'staff_portal', 'label' => 'Staff dashboard'],
            'agent' => ['module' => 'agent_portal', 'label' => 'Agent dashboard'],
            'customer' => ['module' => 'customer_portal', 'label' => 'Customer dashboard'],
            'public_search' => ['module' => 'public_flight_search', 'label' => 'Public flight search'],
            'checkout' => ['module' => 'customer_checkout', 'label' => 'Customer checkout'],
        ];

        $rows = [];
        foreach ($portals as $key => $meta) {
            $moduleKey = $meta['module'];
            $status = PlatformModuleGate::effectiveStatus($moduleKey);
            $rows[$key] = [
                'label' => $meta['label'],
                'module_key' => $moduleKey,
                'effective_enabled' => $status['planned_enabled'] ?? false,
                'route_guarded' => $status['route_middleware'] ?? false,
                'backend_enforced' => $status['backend_service'] ?? false,
                'display' => $status['display'] ?? '—',
            ];
        }

        return ['portals' => $rows];
    }

    /**
     * @return array<string, mixed>
     */
    public function deploymentStatus(): array
    {
        $marker = $this->deployMarker();
        $recentChanges = PlatformModuleSettingChange::query()
            ->with('developerUser:id,name,email')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (PlatformModuleSettingChange $c): array => [
                'module_key' => $c->module_key,
                'old_enabled' => $c->old_enabled,
                'new_enabled' => $c->new_enabled,
                'source' => $c->source,
                'preset_key' => $c->preset_key,
                'developer' => $c->developerUser?->email,
                'created_at' => $c->created_at?->toIso8601String(),
            ])
            ->all();

        $moduleStats = [
            'total' => count(PlatformModuleRegistry::all()),
            'enabled' => 0,
            'disabled' => 0,
        ];
        foreach (PlatformModuleRegistry::all() as $module) {
            if (PlatformModuleGate::routeEnabled($module->key)) {
                $moduleStats['enabled']++;
            } else {
                $moduleStats['disabled']++;
            }
        }

        return [
            'deploy_marker' => $marker,
            'module_stats' => $moduleStats,
            'recent_module_changes' => $recentChanges,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseHealth(): array
    {
        try {
            DB::connection()->getPdo();

            return [
                'ok' => true,
                'agencies' => Agency::query()->count(),
                'users' => User::query()->count(),
                'bookings' => Schema::hasTable('bookings') ? Booking::query()->count() : null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'error' => 'Connection failed',
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function failedJobsSummary(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return ['count' => 0, 'recent' => []];
        }

        $count = (int) DB::table('failed_jobs')->count();
        $recent = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(10)
            ->get(['id', 'queue', 'failed_at', 'exception'])
            ->map(fn ($row): array => [
                'id' => $row->id,
                'queue' => $row->queue,
                'failed_at' => $row->failed_at,
                'exception' => $this->truncate($this->firstLine((string) $row->exception), 200),
            ])
            ->all();

        return ['count' => $count, 'recent' => $recent];
    }

    /**
     * @return list<string>
     */
    private function recentLogErrors(): array
    {
        $path = storage_path('logs/laravel.log');
        if (! is_readable($path)) {
            return [];
        }

        $lines = [];
        try {
            $content = File::get($path);
            $allLines = explode("\n", $content);
            foreach (array_reverse($allLines) as $line) {
                if ($line === '') {
                    continue;
                }
                if (! str_contains($line, '.ERROR') && ! str_contains($line, '[error]')) {
                    continue;
                }
                $lines[] = $this->redactLogLine($this->truncate($line, 300));
                if (count($lines) >= 20) {
                    break;
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return $lines;
    }

    /**
     * @return list<array{command: string, schedule: string}>
     */
    private function schedulerChecklist(): array
    {
        return [
            ['command' => 'ota:cleanup-expired-access', 'schedule' => 'hourly'],
            ['command' => 'ota:send-daily-report', 'schedule' => 'daily 08:00'],
            ['command' => 'homepage:refresh-featured-fares', 'schedule' => 'daily 05:00'],
            ['command' => 'ota:process-abandoned-flight-searches', 'schedule' => 'every 15 min'],
            ['command' => 'group-ticketing:sync-inventory', 'schedule' => 'daily 02:00'],
            ['command' => 'group-ticketing:release-expired', 'schedule' => 'every minute'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deployMarker(): array
    {
        $path = storage_path('app/deploy_marker.json');
        if (! is_readable($path)) {
            return ['present' => false, 'message' => 'No deploy marker file (storage/app/deploy_marker.json)'];
        }

        try {
            $data = json_decode((string) File::get($path), true, 512, JSON_THROW_ON_ERROR);

            return [
                'present' => true,
                'version' => $data['version'] ?? null,
                'deployed_at' => $data['deployed_at'] ?? null,
                'git_sha' => isset($data['git_sha']) ? substr((string) $data['git_sha'], 0, 12) : null,
            ];
        } catch (\Throwable) {
            return ['present' => false, 'message' => 'Deploy marker unreadable'];
        }
    }

    private function truncate(string $value, int $max): string
    {
        return strlen($value) <= $max ? $value : substr($value, 0, $max - 1).'…';
    }

    private function firstLine(string $value): string
    {
        $parts = explode("\n", $value);

        return trim($parts[0] ?? '');
    }

    private function redactLogLine(string $line): string
    {
        $line = preg_replace('/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/', 'Bearer [REDACTED]', $line) ?? $line;
        $line = preg_replace('/password[=:]\S+/i', 'password=[REDACTED]', $line) ?? $line;

        return $line;
    }
}
