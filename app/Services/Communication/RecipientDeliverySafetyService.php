<?php

namespace App\Services\Communication;

use App\Models\CommunicationLog;
use App\Support\Communication\SmtpDeliveryFailureClassifier;

/**
 * Exact-address permanent SMTP recipient suppression from CommunicationLog evidence.
 *
 * Suppresses only when the latest actual delivery outcome for that exact normalized
 * address is failed with delivery_failure_class=permanent_recipient_reject and
 * retry_recommended=false. Mailbox health ignores suppression-only skipped audit rows
 * so they cannot clear durable permanent-reject state. Transient/unknown failures and
 * later successful sent/delivered outcomes to the same address do not suppress.
 */
final class RecipientDeliverySafetyService
{
    public const SUPPRESSION_REASON = 'Recipient previously returned a permanent mailbox rejection; delivery suppressed pending address correction.';

    public function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public function isPermanentlyRejected(string $email): bool
    {
        $normalized = $this->normalizeEmail($email);
        if ($normalized === '' || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        // Actual delivery outcomes only — skipped suppression audits must not override.
        $latest = CommunicationLog::query()
            ->whereRaw('LOWER(TRIM(recipient_email)) = ?', [$normalized])
            ->whereIn('status', ['failed', 'sent', 'delivered'])
            ->orderByDesc('id')
            ->first(['id', 'status', 'meta']);

        if ($latest === null || (string) $latest->status !== 'failed') {
            return false;
        }

        $meta = is_array($latest->meta) ? $latest->meta : [];
        $class = (string) ($meta['delivery_failure_class'] ?? '');
        $retryRecommended = $meta['retry_recommended'] ?? null;

        return $class === SmtpDeliveryFailureClassifier::PERMANENT_RECIPIENT_REJECT
            && ($retryRecommended === false || $retryRecommended === 0);
    }

    /**
     * @param  array<int, string>  $emails
     * @return array{allowed: list<string>, suppressed: list<string>}
     */
    public function partitionEmails(array $emails): array
    {
        $allowed = [];
        $suppressed = [];
        $seen = [];

        foreach ($emails as $email) {
            if (! is_string($email)) {
                continue;
            }
            $normalized = $this->normalizeEmail($email);
            if ($normalized === '' || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            if (isset($seen[$normalized])) {
                continue;
            }
            $seen[$normalized] = true;

            if ($this->isPermanentlyRejected($normalized)) {
                $suppressed[] = $normalized;
            } else {
                $allowed[] = $normalized;
            }
        }

        return [
            'allowed' => $allowed,
            'suppressed' => $suppressed,
        ];
    }
}
