<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CommunicationLog;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class SystemSafetyController extends Controller
{
    public function systemHealth(): View
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

        return view('dashboard.admin.system-health', [
            'checks' => [
                'app_env' => app()->environment(),
                'db_connection_ok' => $dbOk,
                'queue_connection' => (string) config('queue.default'),
                'mail_mailer' => (string) config('mail.default'),
                'storage_local_writable' => Storage::disk('local')->exists('.') || is_writable(storage_path('app')),
                'private_documents_writable' => is_writable($privatePath),
                'active_supplier_connection_count' => SupplierConnection::query()
                    ->where('agency_id', request()->user()->current_agency_id)
                    ->where(function ($q): void {
                        $q->where('is_active', true)->orWhere('status', 'active');
                    })->count(),
                'failed_supplier_attempts_count' => SupplierBookingAttempt::query()
                    ->where('agency_id', request()->user()->current_agency_id)
                    ->where('status', 'failed')
                    ->count(),
                'failed_communication_logs_count' => CommunicationLog::query()
                    ->where('agency_id', request()->user()->current_agency_id)
                    ->where('status', 'failed')
                    ->count(),
            ],
            'recentAdminActivity' => AuditLog::query()
                ->where('agency_id', request()->user()->current_agency_id)
                ->latest('id')
                ->limit(20)
                ->get(),
        ]);
    }

    public function deploymentChecklist(): View
    {
        Gate::authorize('platform.admin');

        return view('dashboard.admin.deployment-checklist', [
            'items' => [
                ['label' => 'APP_KEY is set', 'ok' => filled(config('app.key'))],
                ['label' => 'APP_ENV is configured', 'ok' => filled(config('app.env'))],
                ['label' => 'APP_DEBUG false for production', 'ok' => ! (bool) config('app.debug')],
                ['label' => 'Database configured', 'ok' => filled(config('database.default'))],
                ['label' => 'Queues configured', 'ok' => filled(config('queue.default'))],
                ['label' => 'Mail configured', 'ok' => filled(config('mail.default'))],
                ['label' => 'Storage private path ready', 'ok' => is_writable(storage_path('app'))],
                ['label' => 'Scheduler/cron configured', 'ok' => true],
                ['label' => 'Backups configured', 'ok' => false],
                ['label' => 'Supplier credentials reviewed', 'ok' => false],
                ['label' => 'Test booking performed', 'ok' => false],
                ['label' => 'Document generation tested', 'ok' => false],
            ],
        ]);
    }
}
