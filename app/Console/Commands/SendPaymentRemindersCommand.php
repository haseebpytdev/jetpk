<?php

namespace App\Console\Commands;

use App\Services\Bookings\PaymentReminderService;
use Illuminate\Console\Command;

class SendPaymentRemindersCommand extends Command
{
    protected $signature = 'ota:send-payment-reminders {--limit= : Max bookings to process}';

    protected $description = 'Send deduplicated payment reminders for unpaid bookings approaching payment_due_at';

    public function handle(PaymentReminderService $reminderService): int
    {
        $limit = $this->option('limit');
        $result = $reminderService->processDueBatch($limit !== null ? (int) $limit : null);

        if ($result['sent'] > 0 || $result['scanned'] > 0) {
            $this->info(sprintf(
                'Payment reminders: scanned=%d sent=%d skipped=%d',
                $result['scanned'],
                $result['sent'],
                $result['skipped'],
            ));
        }

        return self::SUCCESS;
    }
}
