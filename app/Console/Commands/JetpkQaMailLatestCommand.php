<?php

namespace App\Console\Commands;

use App\Support\Qa\JetpkQaMailbox;
use Illuminate\Console\Command;

/**
 * CLI-only reader for the ephemeral QA mailbox. No public route.
 */
class JetpkQaMailLatestCommand extends Command
{
    protected $signature = 'jetpk:qa-mail:latest {--recipient= : Optional exact recipient filter}';

    protected $description = 'Show sanitized latest QA mailbox capture (CLI only)';

    public function handle(): int
    {
        if (! JetpkQaMailbox::isEnabled()) {
            $this->warn('JETPK_QA_MAIL_SINK_ENABLED is not true.');
        }

        $row = JetpkQaMailbox::latest($this->option('recipient') ?: null);
        if ($row === null) {
            $this->error('No QA mailbox messages found.');

            return self::FAILURE;
        }

        $this->line('id='.(string) ($row['id'] ?? ''));
        $this->line('recipient='.(string) ($row['recipient'] ?? ''));
        $this->line('subject='.(string) ($row['subject'] ?? ''));
        $this->line('created_at='.(string) ($row['created_at'] ?? ''));
        $this->line('expires_at='.(string) ($row['expires_at'] ?? ''));
        $this->line('has_html='.(isset($row['html_body']) && is_string($row['html_body']) && $row['html_body'] !== '' ? 'yes' : 'no'));
        $this->line('has_text='.(isset($row['text_body']) && is_string($row['text_body']) && $row['text_body'] !== '' ? 'yes' : 'no'));
        $url = is_string($row['verification_url'] ?? null) ? (string) $row['verification_url'] : '';
        if ($url !== '') {
            // Sanitize query token length in CLI output — keep path proof only.
            $safe = preg_replace('#(signature=)[^&\s]+#i', '$1[redacted]', $url) ?? $url;
            $this->line('verification_url_present=yes');
            $this->line('verification_url_sanitized='.$safe);
        } else {
            $this->line('verification_url_present=no');
        }

        return self::SUCCESS;
    }
}
