<?php

namespace App\Console\Commands;

use App\Models\GuestBookingAccessToken;
use Illuminate\Console\Command;

class OtaCleanupExpiredAccessCommand extends Command
{
    protected $signature = 'ota:cleanup-expired-access {--days=30 : Hard-delete expired tokens older than this many days}';

    protected $description = 'Clean up expired guest booking access tokens safely';

    public function handle(): int
    {
        $now = now();
        $retentionDays = max(1, (int) $this->option('days'));
        $hardDeleteBefore = $now->copy()->subDays($retentionDays);

        $expiredQuery = GuestBookingAccessToken::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now);

        $totalExpired = (clone $expiredQuery)->count();

        $hardDeleted = GuestBookingAccessToken::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $hardDeleteBefore)
            ->delete();

        $activeUntouched = GuestBookingAccessToken::query()
            ->where(function ($query) use ($now): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            })
            ->count();

        $this->info('OTA guest access token cleanup complete.');
        $this->line('Expired tokens found: '.$totalExpired);
        $this->line('Expired tokens retained: '.max($totalExpired - $hardDeleted, 0));
        $this->line('Expired tokens hard-deleted: '.$hardDeleted);
        $this->line('Active tokens untouched: '.$activeUntouched);

        return self::SUCCESS;
    }
}
