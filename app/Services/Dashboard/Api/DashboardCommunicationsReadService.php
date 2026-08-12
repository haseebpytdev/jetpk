<?php

namespace App\Services\Dashboard\Api;

use App\Models\CommunicationLog;
use App\Models\User;
use App\Support\Dashboard\DashboardPermissionResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardCommunicationsReadService
{
    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, filters: array<string, mixed>, summary: array<string, int|string>}
     */
    public function paginate(User $user, Request $request): array
    {
        DashboardPermissionResolver::assertPermission($user, 'settings.view');
        Gate::forUser($user)->authorize('viewAny', CommunicationLog::class);

        $query = $this->scopedQuery($user)->with(['booking:id,booking_reference']);
        $this->applyFilters($query, $request);

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = max(5, min(50, (int) $request->query('pageSize', 25)));
        $query->orderByDesc('created_at');

        $paginator = (clone $query)->paginate($pageSize, ['*'], 'page', $page);
        $items = $paginator->getCollection()
            ->map(fn (CommunicationLog $log): array => $this->present($log))
            ->values()
            ->all();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'pageSize' => $paginator->perPage(),
                'total' => $paginator->total(),
                'pageCount' => $paginator->lastPage(),
            ],
            'filters' => $this->activeFilters($request),
            'summary' => $this->summary($user, $request),
        ];
    }

    /**
     * @return Builder<CommunicationLog>
     */
    protected function scopedQuery(User $user): Builder
    {
        $query = CommunicationLog::query();
        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }

        return $query;
    }

    /**
     * @param  Builder<CommunicationLog>  $query
     */
    protected function applyFilters(Builder $query, Request $request): void
    {
        $status = trim((string) $request->query('status', 'failed'));
        if ($status === 'failed') {
            $query->whereIn('status', ['failed', 'error']);
        } elseif ($status !== 'all' && $status !== '') {
            $query->where('status', $status);
        }

        $channel = trim((string) $request->query('channel', ''));
        if ($channel !== '' && $channel !== 'all') {
            $query->where('channel', $channel);
        }

        $event = trim((string) $request->query('event', ''));
        if ($event !== '') {
            $query->where('event', 'like', '%'.$event.'%');
        }

        $search = trim((string) ($request->query('q', $request->query('search', ''))));
        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('event', 'like', '%'.$search.'%')
                    ->orWhere('error_message', 'like', '%'.$search.'%')
                    ->orWhere('recipient_email', 'like', '%'.$search.'%')
                    ->orWhere('subject', 'like', '%'.$search.'%');
            });
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function activeFilters(Request $request): array
    {
        return [
            'status' => (string) $request->query('status', 'failed'),
            'channel' => (string) $request->query('channel', 'all'),
            'event' => (string) $request->query('event', ''),
            'q' => (string) ($request->query('q', $request->query('search', ''))),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    protected function summary(User $user, Request $request): array
    {
        $base = $this->scopedQuery($user)->whereIn('status', ['failed', 'error']);

        $qaLike = (clone $base)->where(function (Builder $inner): void {
            $inner->where('event', 'settings_test_email')
                ->orWhere('event', 'like', '%test%')
                ->orWhere('event', 'like', '%demo%');
        })->count();

        $withBooking = (clone $base)->whereNotNull('booking_id')->count();
        $totalFailed = (clone $base)->count();

        return [
            'failedTotal' => $totalFailed,
            'qaOrTestLike' => $qaLike,
            'linkedToBooking' => $withBooking,
            'unlinked' => max(0, $totalFailed - $withBooking),
            'note' => 'Classification is heuristic. Do not delete audit rows or blind-retry from this view.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(CommunicationLog $log): array
    {
        $status = (string) $log->status;
        $event = (string) ($log->event ?? '');
        $qaLike = str_contains(strtolower($event), 'test')
            || str_contains(strtolower($event), 'demo')
            || $event === 'settings_test_email';

        return [
            'id' => (string) $log->id,
            'status' => $status,
            'channel' => (string) ($log->channel ?? ''),
            'event' => $event,
            'provider' => (string) ($log->provider ?? ''),
            'recipientMasked' => $this->maskRecipient(
                (string) ($log->recipient_email ?? ''),
                (string) ($log->recipient_phone ?? ''),
            ),
            'subject' => (string) ($log->subject ?? ''),
            'errorMessage' => (string) ($log->error_message ?? ''),
            'bookingId' => $log->booking_id !== null ? (string) $log->booking_id : null,
            'bookingReference' => $log->booking?->booking_reference,
            'agencyId' => $log->agency_id !== null ? (string) $log->agency_id : null,
            'createdAt' => optional($log->created_at)?->toIso8601String(),
            'sentAt' => optional($log->sent_at)?->toIso8601String(),
            'classificationHint' => $qaLike ? 'qa_or_test_like' : ($log->booking_id ? 'booking_linked' : 'unlinked'),
            'retryEligible' => in_array($status, ['failed', 'skipped'], true),
            'operatorAction' => 'Review only — no automatic retry from this surface.',
        ];
    }

    protected function maskRecipient(string $email, string $phone): string
    {
        if ($email !== '') {
            $parts = explode('@', $email, 2);
            if (count($parts) !== 2) {
                return '***';
            }
            $local = $parts[0];
            $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

            return $visible.'***@'.$parts[1];
        }

        if ($phone !== '') {
            $digits = preg_replace('/\D+/', '', $phone) ?? '';
            if (strlen($digits) < 4) {
                return '***';
            }

            return str_repeat('*', max(0, strlen($digits) - 4)).substr($digits, -4);
        }

        return '—';
    }
}
