<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Models\CommunicationLog;
use App\Services\Communication\OtaNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CommunicationDeliveryLogController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function __construct(
        protected OtaNotificationService $notificationService,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', CommunicationLog::class);

        $agencyId = $request->user()->current_agency_id;
        $filter = $request->string('status')->toString() ?: 'issues';
        $pageSize = max(1, min(50, (int) $request->integer('pageSize', 25)));

        $base = CommunicationLog::query()
            ->with(['booking:id,booking_reference'])
            ->orderByDesc('created_at');

        if (! $request->user()->isPlatformAdmin()) {
            $base->where('agency_id', $agencyId);
        }

        $failedTotal = (clone $base)->whereIn('status', ['failed', 'error'])->count();
        $qaLike = (clone $base)->whereIn('status', ['failed', 'error', 'skipped'])
            ->where(function ($q): void {
                $q->where('recipient_email', 'like', '%@example.%')
                    ->orWhere('recipient_email', 'like', '%qa.%')
                    ->orWhere('event', 'like', '%test%');
            })->count();
        $bookingLinked = (clone $base)->whereIn('status', ['failed', 'error', 'skipped'])
            ->whereNotNull('booking_id')->count();
        $unlinked = (clone $base)->whereIn('status', ['failed', 'error', 'skipped'])
            ->whereNull('booking_id')->count();

        $query = clone $base;
        if ($filter === 'issues') {
            $query->whereIn('status', ['failed', 'skipped', 'error']);
        } elseif ($filter === 'failed') {
            $query->whereIn('status', ['failed', 'error']);
        } elseif ($filter !== 'all') {
            $query->where('status', $filter);
        }

        $logs = $query->paginate($pageSize)->withQueryString();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'filter' => $filter,
                'kpis' => [
                    'failed_total' => $failedTotal,
                    'qa_test_like' => $qaLike,
                    'booking_linked' => $bookingLinked,
                    'unlinked' => $unlinked,
                ],
                'failures' => $logs->getCollection()->map(fn (CommunicationLog $log): array => [
                    'id' => (string) $log->id,
                    'timestamp' => $log->created_at?->toIso8601String(),
                    'channel' => (string) $log->channel,
                    'event' => (string) $log->event,
                    'recipient_masked' => $this->maskRecipient(
                        (string) ($log->recipient_email ?: $log->recipient_phone ?: ''),
                    ),
                    'subject' => (string) ($log->subject ?? ''),
                    'classification' => $this->classifyFailure($log),
                    'safe_error' => Str::limit((string) ($log->error_message ?? ''), 180, '…'),
                    'provider' => (string) ($log->provider ?? ''),
                    'status' => (string) $log->status,
                    'booking_id' => $log->booking_id !== null ? (string) $log->booking_id : null,
                    'booking_reference' => $log->booking?->booking_reference,
                    'retry_eligible' => in_array((string) $log->status, ['failed', 'error', 'skipped'], true),
                    'operator_disposition' => in_array((string) $log->status, ['failed', 'error'], true)
                        ? 'inspect_only'
                        : 'recorded',
                ])->values()->all(),
                'meta' => [
                    'page' => $logs->currentPage(),
                    'pageCount' => $logs->lastPage(),
                    'pageSize' => $logs->perPage(),
                    'total' => $logs->total(),
                ],
                'safety' => [
                    'blind_retry' => false,
                    'delete_forbidden' => true,
                    'recipient_secrets_exposed' => false,
                ],
            ]);
        }

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

    protected function maskRecipient(string $recipient): string
    {
        $recipient = trim($recipient);
        if ($recipient === '') {
            return '—';
        }
        if (str_contains($recipient, '@')) {
            [$local, $domain] = array_pad(explode('@', $recipient, 2), 2, '');
            $localMask = Str::substr($local, 0, 1).str_repeat('*', max(1, Str::length($local) - 1));

            return $localMask.'@'.$domain;
        }

        $digits = preg_replace('/\D+/', '', $recipient) ?? '';
        if (Str::length($digits) < 4) {
            return '****';
        }

        return str_repeat('*', max(0, Str::length($digits) - 4)).Str::substr($digits, -4);
    }

    protected function classifyFailure(CommunicationLog $log): string
    {
        $status = (string) $log->status;
        $email = strtolower((string) ($log->recipient_email ?? ''));
        $event = strtolower((string) ($log->event ?? ''));

        if (str_contains($email, 'example.') || str_contains($email, 'qa.') || str_contains($event, 'test')) {
            return 'qa_test_like';
        }
        if ($log->booking_id !== null) {
            return 'booking_linked';
        }
        if (in_array($status, ['failed', 'error'], true)) {
            return 'unlinked_failure';
        }

        return $status !== '' ? $status : 'unknown';
    }
}
