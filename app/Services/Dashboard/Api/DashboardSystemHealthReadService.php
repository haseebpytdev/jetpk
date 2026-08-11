<?php

namespace App\Services\Dashboard\Api;

use App\Models\AuditLog;
use App\Models\CommunicationLog;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DashboardSystemHealthReadService
{
    /**
     * @return array{checks: array<string, mixed>, checklist: list<array{label: string, ok: bool}>}
     */
    public function overview(User $user): array
    {
        Gate::authorize('platform.admin');

        $dbOk = true;
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $dbOk = false;
        }

        $privatePath = storage_path('app/private');
        if (! is_dir($privatePath)) {
            @mkdir($privatePath, 0775, true);
        }

        $agencyId = $user->current_agency_id;

        return [
            'checks' => [
                'appEnv' => app()->environment(),
                'dbConnectionOk' => $dbOk,
                'queueConnection' => (string) config('queue.default'),
                'mailMailer' => (string) config('mail.default'),
                'storageLocalWritable' => Storage::disk('local')->exists('.') || is_writable(storage_path('app')),
                'privateDocumentsWritable' => is_writable($privatePath),
                'activeSupplierConnectionCount' => SupplierConnection::query()
                    ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))
                    ->where(function ($q): void {
                        $q->where('is_active', true)->orWhere('status', 'active');
                    })->count(),
                'failedSupplierAttemptsCount' => SupplierBookingAttempt::query()
                    ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))
                    ->where('status', 'failed')
                    ->count(),
                'failedCommunicationLogsCount' => CommunicationLog::query()
                    ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))
                    ->where('status', 'failed')
                    ->count(),
                'recentAdminActivityCount' => AuditLog::query()
                    ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))
                    ->latest('id')
                    ->limit(20)
                    ->count(),
            ],
            'checklist' => [
                ['label' => 'APP_KEY is set', 'ok' => filled(config('app.key'))],
                ['label' => 'APP_ENV is configured', 'ok' => filled(config('app.env'))],
                ['label' => 'APP_DEBUG false for production', 'ok' => ! (bool) config('app.debug')],
                ['label' => 'Database configured', 'ok' => filled(config('database.default'))],
                ['label' => 'Queues configured', 'ok' => filled(config('queue.default'))],
                ['label' => 'Mail configured', 'ok' => filled(config('mail.default'))],
                ['label' => 'Storage private path ready', 'ok' => is_writable(storage_path('app'))],
            ],
        ];
    }
}
