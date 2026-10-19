<?php

namespace App\Support\Ops;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Non-fatal external routing for operational/security health alerts.
 * Delivery is opt-in via OPS_ALERT_EMAIL; never targets customers.
 */
final class OpsExternalAlertRouter
{
    public const LEVEL_APP = 'app_failure';

    public const LEVEL_QUEUE = 'queue_failure';

    public const LEVEL_SUPPLIER = 'supplier_failure';

    public const LEVEL_SECURITY = 'security_failure';

    public const LEVEL_DISK = 'disk_pressure';

    /**
     * @param  array<string, scalar|null>  $context
     * @return array{delivered: bool, channel: string, dry_run: bool, reason?: string}
     */
    public function route(string $category, string $subject, string $body, array $context = [], bool $dryRun = false): array
    {
        $destination = trim((string) config('ops.alert_email', ''));
        $enabled = (bool) config('ops.external_alerts_enabled', false);

        if ($destination === '' || ! $enabled) {
            Log::info('ops.external_alert.skipped', [
                'category' => $category,
                'reason' => $destination === '' ? 'no_destination' : 'disabled',
                'subject' => $subject,
            ]);

            return [
                'delivered' => false,
                'channel' => 'log_only',
                'dry_run' => $dryRun,
                'reason' => $destination === '' ? 'no_destination' : 'disabled',
            ];
        }

        if ($dryRun || (bool) config('ops.alert_dry_run', false)) {
            Log::info('ops.external_alert.dry_run', [
                'category' => $category,
                'destination_configured' => true,
                'subject' => $subject,
                'context_keys' => array_keys($context),
            ]);

            return [
                'delivered' => false,
                'channel' => 'dry_run',
                'dry_run' => true,
            ];
        }

        try {
            Mail::raw($body, function ($message) use ($destination, $subject): void {
                $message->to($destination)->subject('[JetPakistan Ops] '.$subject);
            });

            Log::info('ops.external_alert.delivered', [
                'category' => $category,
                'channel' => 'mail',
            ]);

            return [
                'delivered' => true,
                'channel' => 'mail',
                'dry_run' => false,
            ];
        } catch (Throwable $e) {
            // Alert delivery must never break the primary business transaction.
            Log::warning('ops.external_alert.failed_non_fatal', [
                'category' => $category,
                'error_class' => $e::class,
            ]);

            return [
                'delivered' => false,
                'channel' => 'mail',
                'dry_run' => false,
                'reason' => 'transport_failed',
            ];
        }
    }
}
