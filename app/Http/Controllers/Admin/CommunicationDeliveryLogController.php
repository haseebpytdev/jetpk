<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunicationLog;
use App\Services\Communication\OtaNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class CommunicationDeliveryLogController extends Controller
{
    public function __construct(
        protected OtaNotificationService $notificationService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CommunicationLog::class);

        $agencyId = $request->user()->current_agency_id;
        $filter = $request->string('status')->toString() ?: 'issues';

        $query = CommunicationLog::query()
            ->with(['booking:id,booking_reference'])
            ->where('channel', 'email')
            ->orderByDesc('created_at');

        if (! $request->user()->isPlatformAdmin()) {
            $query->where('agency_id', $agencyId);
        }

        if ($filter === 'issues') {
            $query->whereIn('status', ['failed', 'skipped']);
        } elseif ($filter !== 'all') {
            $query->where('status', $filter);
        }

        $logs = $query->paginate(25)->withQueryString();

        return view('dashboard.admin.settings.communications.delivery-log', [
            'logs' => $logs,
            'filter' => $filter,
        ]);
    }

    public function resend(Request $request, CommunicationLog $communicationLog): RedirectResponse
    {
        Gate::authorize('resend', $communicationLog);

        if (! $request->user()->isPlatformAdmin()
            && $request->user()->current_agency_id !== $communicationLog->agency_id) {
            abort(403);
        }

        try {
            $this->notificationService->resendCommunicationLog($communicationLog, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['resend' => $e->getMessage()]);
        }

        return back()->with('status', 'communication-resend-queued');
    }
}
