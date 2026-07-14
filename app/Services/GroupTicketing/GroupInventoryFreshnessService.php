<?php

namespace App\Services\GroupTicketing;

use App\Support\GroupTicketing\GroupTicketingLivePolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Search-time inventory freshness guard — triggers read-only sync for public group search.
 */
class GroupInventoryFreshnessService
{
    private const LOCK_KEY = 'group-ticketing:inventory-search-sync';

    private const LOCK_SECONDS = 60;

    private const TTL_CACHE_KEY = 'group-ticketing:realtime-sync-at';

    private const SESSION_CONFIRM_PREFIX = 'group-ticketing:live-confirmed:';

    private const SESSION_CONFIRM_SECONDS = 600;

    private static bool $refreshedThisRequest = false;

    private static bool $providerConfirmedThisRequest = false;

    public function __construct(
        private readonly GroupInventoryFacetService $facetService,
        private readonly GroupInventorySyncService $syncService,
    ) {}

    /**
     * @return array{
     *     synced: bool,
     *     skipped: bool,
     *     skip_reason: ?string,
     *     last_synced_at: ?\DateTimeInterface,
     *     minutes_ago: ?int,
     *     refresh_attempted: bool,
     *     refresh_failed: bool,
     *     provider_confirmed: bool,
     *     bookable: bool,
     *     user_notice: ?string
     * }
     */
    public function ensureFreshForSearch(): array
    {
        $lastSyncedAt = $this->facetService->lastInventorySyncAt();
        $minutesAgo = $this->minutesAgo($lastSyncedAt);

        if (self::$refreshedThisRequest && self::$providerConfirmedThisRequest) {
            return $this->confirmedResult($lastSyncedAt, $minutesAgo, skipped: true, skipReason: 'request_dedupe');
        }

        if (! $this->isRealtimeEnabled()) {
            return $this->handleSkippedRefresh(
                'disabled',
                $lastSyncedAt,
                $minutesAgo,
                refreshAttempted: false,
            );
        }

        if (! GroupTicketingLivePolicy::publicResultsMustBeProviderConfirmed()) {
            $ttlSeconds = $this->realtimeTtlSeconds();
            if ($ttlSeconds > 0 && $this->isWithinTtl($ttlSeconds)) {
                return $this->legacySoftSkip('ttl_fresh', $lastSyncedAt, $minutesAgo);
            }
        }

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        if (! $lock->get()) {
            return $this->handleSkippedRefresh('lock_busy', $lastSyncedAt, $minutesAgo, refreshAttempted: false);
        }

        self::$refreshedThisRequest = true;

        try {
            Log::info('group_inventory_realtime_refresh_attempted', [
                'minutes_ago' => $minutesAgo,
                'ttl_seconds' => $this->realtimeTtlSeconds(),
            ]);

            $syncResult = $this->syncService->sync(forceFresh: true);

            if (($syncResult['skipped'] ?? false) === true) {
                Log::warning('group_inventory_realtime_refresh_failed', [
                    'reason' => 'sync_skipped',
                    'message' => $syncResult['message'] ?? null,
                ]);

                return $this->failedResult(
                    $syncResult['message'] ?? 'sync_skipped',
                    $this->facetService->lastInventorySyncAt(),
                );
            }

            $ttlSeconds = $this->realtimeTtlSeconds();
            $this->markRealtimeSyncCompleted($ttlSeconds);
            self::$providerConfirmedThisRequest = true;
            $this->markSessionProviderConfirmed();

            $refreshedAt = $this->facetService->lastInventorySyncAt();

            Log::info('group_inventory_realtime_refresh_success', [
                'synced' => $syncResult['synced'] ?? 0,
                'deactivated' => $syncResult['deactivated'] ?? 0,
            ]);

            return $this->result(
                synced: true,
                skipped: false,
                skipReason: null,
                lastSyncedAt: $refreshedAt,
                minutesAgo: $this->minutesAgo($refreshedAt),
                refreshAttempted: true,
                refreshFailed: false,
                providerConfirmed: true,
                bookable: true,
                userNotice: null,
            );
        } catch (\Throwable $exception) {
            Log::error('group_inventory_realtime_refresh_failed', [
                'message' => $exception->getMessage(),
            ]);

            return $this->failedResult('exception', $this->facetService->lastInventorySyncAt());
        } finally {
            $lock->release();
        }
    }

    public function isSessionProviderConfirmed(): bool
    {
        if (self::$providerConfirmedThisRequest) {
            return true;
        }

        $sessionId = session()->getId();
        if ($sessionId === '') {
            return false;
        }

        return (bool) Cache::get(self::SESSION_CONFIRM_PREFIX.$sessionId);
    }

    public function clearSessionProviderConfirmed(): void
    {
        $sessionId = session()->getId();
        if ($sessionId !== '') {
            Cache::forget(self::SESSION_CONFIRM_PREFIX.$sessionId);
        }
    }

    /**
     * @param  array<string, mixed>|null  $freshness
     */
    public function publicResultsAreBookable(?array $freshness, int $page = 1): bool
    {
        if (! GroupTicketingLivePolicy::publicResultsMustBeProviderConfirmed()) {
            return true;
        }

        if ($page > 1) {
            return $this->isSessionProviderConfirmed();
        }

        return is_array($freshness)
            && ($freshness['provider_confirmed'] ?? false)
            && ($freshness['bookable'] ?? false);
    }

    private function handleSkippedRefresh(
        string $skipReason,
        ?\DateTimeInterface $lastSyncedAt,
        ?int $minutesAgo,
        bool $refreshAttempted,
    ): array {
        Log::info('group_inventory_realtime_refresh_skipped', [
            'reason' => $skipReason,
            'minutes_ago' => $minutesAgo,
        ]);

        if (GroupTicketingLivePolicy::publicResultsMustBeProviderConfirmed()) {
            return $this->result(
                synced: false,
                skipped: true,
                skipReason: $skipReason,
                lastSyncedAt: $lastSyncedAt,
                minutesAgo: $minutesAgo,
                refreshAttempted: $refreshAttempted,
                refreshFailed: true,
                providerConfirmed: false,
                bookable: false,
                userNotice: GroupTicketingLivePolicy::PUBLIC_SEARCH_UNAVAILABLE_MESSAGE,
            );
        }

        return $this->legacySoftSkip($skipReason, $lastSyncedAt, $minutesAgo, $refreshAttempted);
    }

    /**
     * @return array<string, mixed>
     */
    private function legacySoftSkip(
        string $skipReason,
        ?\DateTimeInterface $lastSyncedAt,
        ?int $minutesAgo,
        bool $refreshAttempted = false,
    ): array {
        return $this->result(
            synced: false,
            skipped: true,
            skipReason: $skipReason,
            lastSyncedAt: $lastSyncedAt,
            minutesAgo: $minutesAgo,
            refreshAttempted: $refreshAttempted,
            refreshFailed: false,
            providerConfirmed: false,
            bookable: true,
            userNotice: null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function confirmedResult(
        ?\DateTimeInterface $lastSyncedAt,
        ?int $minutesAgo,
        bool $skipped,
        ?string $skipReason,
    ): array {
        return $this->result(
            synced: false,
            skipped: $skipped,
            skipReason: $skipReason,
            lastSyncedAt: $lastSyncedAt,
            minutesAgo: $minutesAgo,
            refreshAttempted: false,
            refreshFailed: false,
            providerConfirmed: true,
            bookable: true,
            userNotice: null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function failedResult(string $skipReason, ?\DateTimeInterface $lastSyncedAt): array
    {
        $failClosed = GroupTicketingLivePolicy::publicResultsMustBeProviderConfirmed();

        return $this->result(
            synced: false,
            skipped: true,
            skipReason: $skipReason,
            lastSyncedAt: $lastSyncedAt,
            minutesAgo: $this->minutesAgo($lastSyncedAt),
            refreshAttempted: true,
            refreshFailed: true,
            providerConfirmed: false,
            bookable: ! $failClosed,
            userNotice: $failClosed
                ? GroupTicketingLivePolicy::PUBLIC_SEARCH_UNAVAILABLE_MESSAGE
                : 'Inventory refresh temporarily unavailable. Showing latest available results.',
        );
    }

    private function markSessionProviderConfirmed(): void
    {
        $sessionId = session()->getId();
        if ($sessionId === '') {
            return;
        }

        Cache::put(
            self::SESSION_CONFIRM_PREFIX.$sessionId,
            true,
            self::SESSION_CONFIRM_SECONDS,
        );
    }

    private function isRealtimeEnabled(): bool
    {
        if (config('ota.group_ticketing.realtime_search_enabled') !== null) {
            return (bool) config('ota.group_ticketing.realtime_search_enabled');
        }

        return (bool) config('ota.group_ticketing.inventory_search_sync_enabled', true);
    }

    private function realtimeTtlSeconds(): int
    {
        return max(0, (int) config('ota.group_ticketing.realtime_search_ttl_seconds', 0));
    }

    private function isWithinTtl(int $ttlSeconds): bool
    {
        $lastAt = Cache::get(self::TTL_CACHE_KEY);
        if (! is_numeric($lastAt)) {
            return false;
        }

        return (time() - (int) $lastAt) < $ttlSeconds;
    }

    private function markRealtimeSyncCompleted(int $ttlSeconds): void
    {
        $ttl = max($ttlSeconds, 2);

        Cache::put(self::TTL_CACHE_KEY, time(), $ttl);
    }

    private function minutesAgo(?\DateTimeInterface $at): ?int
    {
        if ($at === null) {
            return null;
        }

        return max(0, (int) Carbon::parse($at)->diffInMinutes(now()));
    }

    /**
     * @return array{
     *     synced: bool,
     *     skipped: bool,
     *     skip_reason: ?string,
     *     last_synced_at: ?\DateTimeInterface,
     *     minutes_ago: ?int,
     *     refresh_attempted: bool,
     *     refresh_failed: bool,
     *     provider_confirmed: bool,
     *     bookable: bool,
     *     user_notice: ?string
     * }
     */
    private function result(
        bool $synced,
        bool $skipped,
        ?string $skipReason,
        ?\DateTimeInterface $lastSyncedAt,
        ?int $minutesAgo,
        bool $refreshAttempted,
        bool $refreshFailed,
        bool $providerConfirmed,
        bool $bookable,
        ?string $userNotice,
    ): array {
        return [
            'synced' => $synced,
            'skipped' => $skipped,
            'skip_reason' => $skipReason,
            'last_synced_at' => $lastSyncedAt,
            'minutes_ago' => $minutesAgo,
            'refresh_attempted' => $refreshAttempted,
            'refresh_failed' => $refreshFailed,
            'provider_confirmed' => $providerConfirmed,
            'bookable' => $bookable,
            'user_notice' => $userNotice,
        ];
    }
}
