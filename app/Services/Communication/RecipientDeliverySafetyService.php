<?php

namespace App\Services\Communication;

use App\Models\CommunicationLog;
use App\Support\Communication\SmtpDeliveryFailureClassifier;

/**
 * Exact-address permanent SMTP recipient suppression from CommunicationLog evidence.
 *
 * Suppresses when the latest actual delivery outcome for that exact normalized
 * address is failed and either (1) modern meta marks permanent_recipient_reject with
 * retry_recommended=false, or (2) legacy fallback: classification meta absent, stored
 * error unambiguously contains enhanced 5.1.1, and recipient_email is one exact address.
 * Skipped audit rows never override; later sent/delivered clears suppression.
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
            ->first(['id', 'status', 'meta', 'error_message', 'recipient_email']);

        if ($latest === null || (string) $latest->status !== 'failed') {
            return false;
        }

        if (! $this->isSingleExactStoredRecipient((string) ($latest->recipient_email ?? ''), $normalized)) {
            return false;
        }

        $meta = is_array($latest->meta) ? $latest->meta : [];

        // Modern path: any delivery_failure_class key means classifier already ran.
        if (array_key_exists('delivery_failure_class', $meta)) {
            $class = (string) ($meta['delivery_failure_class'] ?? '');
            $retryRecommended = $meta['retry_recommended'] ?? null;

            return $class === SmtpDeliveryFailureClassifier::PERMANENT_RECIPIENT_REJECT
                && ($retryRecommended === false || $retryRecommended === 0);
        }

        // Legacy bridge: pre-classifier failed rows with unambiguous enhanced 5.1.1 only.
        $classified = (new SmtpDeliveryFailureClassifier)->classifyMessage((string) ($latest->error_message ?? ''));

        return $classified['delivery_failure_class'] === SmtpDeliveryFailureClassifier::PERMANENT_RECIPIENT_REJECT
            && $classified['retry_recommended'] === false;
    }

    private function isSingleExactStoredRecipient(string $storedRecipient, string $normalizedLookup): bool
    {
        $stored = $this->normalizeEmail($storedRecipient);
        if ($stored === '' || $stored !== $normalizedLookup) {
            return false;
        }
        if (str_contains($stored, ',') || str_contains($stored, ';') || preg_match('/\s/', $stored) === 1) {
            return false;
        }

        return filter_var($stored, FILTER_VALIDATE_EMAIL) !== false;
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
