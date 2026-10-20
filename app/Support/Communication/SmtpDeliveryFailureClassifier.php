<?php

namespace App\Support\Communication;

use Throwable;

/**
 * Classifies SMTP delivery exceptions for queued operational mail.
 *
 * Permanent recipient rejects (enhanced 5.1.1) must not burn queue retries.
 * Transient 4xx and unknown failures remain fail-loud / retryable.
 */
final class SmtpDeliveryFailureClassifier
{
    public const PERMANENT_RECIPIENT_REJECT = 'permanent_recipient_reject';

    public const TRANSIENT = 'transient';

    public const UNKNOWN = 'unknown';

    /**
     * @return array{
     *     delivery_failure_class: self::PERMANENT_RECIPIENT_REJECT|self::TRANSIENT|self::UNKNOWN,
     *     smtp_code: ?string,
     *     smtp_enhanced_status: ?string,
     *     retry_recommended: bool
     * }
     */
    public function classify(Throwable $e): array
    {
        return $this->classifyMessage($this->messageCorpus($e));
    }

    /**
     * Classify from a stored SMTP exception corpus (legacy CommunicationLog error_message).
     *
     * @return array{
     *     delivery_failure_class: self::PERMANENT_RECIPIENT_REJECT|self::TRANSIENT|self::UNKNOWN,
     *     smtp_code: ?string,
     *     smtp_enhanced_status: ?string,
     *     retry_recommended: bool
     * }
     */
    public function classifyMessage(string $message): array
    {
        $smtpCode = $this->parseSmtpReplyCode($message);
        $enhanced = $this->parseEnhancedStatus($message);

        // Narrow permanent: mailbox unavailable only (enhanced 5.1.1).
        // Do not treat bare "550" prose without enhanced status as permanent.
        if ($enhanced === '5.1.1') {
            return [
                'delivery_failure_class' => self::PERMANENT_RECIPIENT_REJECT,
                'smtp_code' => $smtpCode ?? '550',
                'smtp_enhanced_status' => '5.1.1',
                'retry_recommended' => false,
            ];
        }

        $isTransientEnhanced = $enhanced !== null && str_starts_with($enhanced, '4.');
        $isTransientReply = $smtpCode !== null && str_starts_with($smtpCode, '4');

        if ($isTransientEnhanced || $isTransientReply) {
            return [
                'delivery_failure_class' => self::TRANSIENT,
                'smtp_code' => $smtpCode,
                'smtp_enhanced_status' => $enhanced,
                'retry_recommended' => true,
            ];
        }

        return [
            'delivery_failure_class' => self::UNKNOWN,
            'smtp_code' => $smtpCode,
            'smtp_enhanced_status' => $enhanced,
            'retry_recommended' => true,
        ];
    }

    private function messageCorpus(Throwable $e): string
    {
        $parts = [$e->getMessage()];
        $prev = $e->getPrevious();
        $depth = 0;
        while ($prev !== null && $depth < 5) {
            $parts[] = $prev->getMessage();
            $prev = $prev->getPrevious();
            $depth++;
        }

        return implode("\n", $parts);
    }

    private function parseSmtpReplyCode(string $message): ?string
    {
        if (preg_match('/got code "(\d{3})"/i', $message, $m) === 1) {
            return $m[1];
        }

        if (preg_match('/\b([245]\d{2})\s+[245]\.\d{1,3}\.\d{1,3}\b/', $message, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function parseEnhancedStatus(string $message): ?string
    {
        if (preg_match('/\b([245]\.\d{1,3}\.\d{1,3})\b/', $message, $m) === 1) {
            return $m[1];
        }

        return null;
    }
}
