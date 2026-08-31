<?php

namespace App\Services\Communication;

use App\Models\Booking;

/**
 * Redirects internal ops recipients to controlled sinks for synthetic/local_qa bookings.
 * Does not alter production routing for ordinary commercial bookings.
 * Not controllable via public request parameters.
 */
class QaOperationalCommunicationGuard
{
    /**
     * @param  array{to: array<int, string>, cc: array<int, string>, bcc: array<int, string>, scope: string, buckets: list<string>, skipped_buckets: list<array{bucket: string, reason: string}>}  $bundle
     * @return array{to: array<int, string>, cc: array<int, string>, bcc: array<int, string>, scope: string, buckets: list<string>, skipped_buckets: list<array{bucket: string, reason: string}>, qa_isolation_applied?: bool}
     */
    public function filterRecipientBundle(array $bundle, ?Booking $booking): array
    {
        if (! $this->shouldIsolate($booking)) {
            return $bundle;
        }

        $sinks = $this->sinkEmails();
        if ($sinks === []) {
            return [
                ...$bundle,
                'to' => [],
                'cc' => [],
                'bcc' => [],
                'qa_isolation_applied' => true,
            ];
        }

        $keepCustomerControlled = [];
        foreach (array_merge($bundle['to'], $bundle['cc'], $bundle['bcc']) as $email) {
            if ($this->isControlledDomainEmail($email)) {
                $keepCustomerControlled[] = strtolower(trim($email));
            }
        }

        $to = array_values(array_unique([...$keepCustomerControlled, ...$sinks]));

        return [
            ...$bundle,
            'to' => $to,
            'cc' => [],
            'bcc' => [],
            'qa_isolation_applied' => true,
        ];
    }

    /**
     * @param  list<string>  $emails
     * @return list<string>
     */
    public function filterEmailList(array $emails, ?Booking $booking): array
    {
        if (! $this->shouldIsolate($booking)) {
            return array_values($emails);
        }

        $kept = [];
        foreach ($emails as $email) {
            if ($this->isControlledDomainEmail($email)) {
                $kept[] = strtolower(trim($email));
            }
        }

        return array_values(array_unique([...$kept, ...$this->sinkEmails()]));
    }

    public function shouldIsolate(?Booking $booking): bool
    {
        if (! (bool) config('ota.qa_communication.isolation_enabled', true)) {
            return false;
        }

        if ($booking === null) {
            return false;
        }

        $supplier = strtolower((string) ($booking->supplier ?? ''));
        if (str_contains($supplier, 'local_qa')) {
            return true;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        if (! empty($meta['jp_ops_qa']) || ! empty($meta['local_qa']) || ! empty($meta['qa_synthetic'])) {
            return true;
        }

        $channel = strtolower((string) ($booking->source_channel ?? ''));
        if (str_starts_with($channel, 'jp_ops') || str_contains($channel, 'local_qa')) {
            return true;
        }

        return false;
    }

    public function isControlledDomainEmail(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || ! str_contains($email, '@')) {
            return false;
        }

        $domain = substr($email, strrpos($email, '@') + 1);
        $allowed = config('ota.qa_communication.controlled_domains', ['example.invalid']);

        return in_array($domain, is_array($allowed) ? $allowed : [], true);
    }

    /**
     * @return list<string>
     */
    public function sinkEmails(): array
    {
        $sinks = config('ota.qa_communication.ops_sink_emails', ['qa-ops-sink@example.invalid']);

        return array_values(array_filter(is_array($sinks) ? $sinks : []));
    }
}
