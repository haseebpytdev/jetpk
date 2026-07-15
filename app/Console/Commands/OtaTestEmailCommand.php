<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OtaTestEmailCommand extends Command
{
    protected $signature = 'ota:test-email {--to= : Recipient email address}';

    protected $description = 'Send a safe SMTP test email using the application mail configuration';

    public function handle(): int
    {
        $to = strtolower(trim((string) $this->option('to')));
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('Provide a valid recipient: php artisan ota:test-email --to=you@example.com');

            return self::FAILURE;
        }

        $from = (string) (config('mail.from.address') ?: '');
        $subject = 'OTA SMTP test';
        $body = 'This is a safe SMTP connectivity test from '.config('app.name').'.';

        try {
            Mail::raw($body, function ($message) use ($to, $subject): void {
                $message->to($to)->subject($subject);
            });
        } catch (Throwable $e) {
            $this->error('Mail send failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Test email queued/sent to '.$to.' via '.config('mail.default').($from !== '' ? ' (from '.$from.')' : '').'.');

        return self::SUCCESS;
    }
}
