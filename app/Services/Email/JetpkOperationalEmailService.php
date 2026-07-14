<?php

namespace App\Services\Email;

use App\Data\Email\OperationalEmailData;
use App\Mail\JetpkOperationalEventMail;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Models\User;
use App\Support\Emails\EmailBaseVariables;
use App\Support\Emails\JetpkEmailEventRenderer;
use App\Support\Emails\JetpkOperationalEmailEventRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Unified JetPK operational email delivery: universal shell rendering,
 * per-bucket role-specific copy, deduplication, and structured logging.
 */
class JetpkOperationalEmailService
{
    public function __construct(
        protected JetpkEmailEventRenderer $eventRenderer,
    ) {}

    /**
     * @param  array<string, scalar|null>  $templateVariables
     * @param  array<string, mixed>  $payload
     * @return array{subject: string, html: string, plain_body: string, used_template: bool, template_enabled: bool}
     */
    public function render(
        Agency $agency,
        string $eventKey,
        array $templateVariables = [],
        ?string $deliveryVariant = null,
        ?string $recipientRole = null,
        ?string $recipientDesignation = null,
        string $fallbackSubject = 'Notification',
        string $fallbackBody = '',
        ?Booking $booking = null,
        array $payload = [],
    ): array {
        JetpkOperationalEmailEventRegistry::assertKnownEvent($eventKey);

        $merged = EmailBaseVariables::merge($agency, $booking, $templateVariables);
        if ($recipientRole !== null && $recipientRole !== '') {
            $merged['recipient_role'] = $recipientRole;
        }
        if ($recipientDesignation !== null && $recipientDesignation !== '') {
            $merged['recipient_designation'] = $recipientDesignation;
            $merged['user_designation'] = $recipientDesignation;
        }

        $variantOverrides = $deliveryVariant !== null
            ? JetpkOperationalEmailEventRegistry::variantContentOverrides($eventKey, $deliveryVariant)
            : [];

        foreach ($variantOverrides as $key => $value) {
            if (is_string($value) && $value !== '') {
                $merged['jetpk_variant_'.$key] = $value;
            }
        }

        $rendered = $this->eventRenderer->render(
            eventKey: $eventKey,
            agency: $agency,
            dbTemplate: null,
            runtimeVariables: array_merge($merged, $this->variantRuntimeOverrides($variantOverrides)),
            payload: $payload,
        );

        $plainBody = $this->plainBodyFromHtml($rendered->html, $rendered->content['intro'] ?? $fallbackBody);

        return [
            'subject' => $rendered->subject !== '' ? $rendered->subject : $fallbackSubject,
            'html' => $rendered->html,
            'plain_body' => $plainBody,
            'used_template' => $rendered->usedDbTemplate,
            'template_enabled' => (bool) ($rendered->content['enabled'] ?? true),
        ];
    }

    /**
     * @return 'sent'|'queued'|'skipped'|'failed'
     */
    public function deliver(OperationalEmailData $data, Agency $agency, ?Booking $booking = null, ?User $actor = null): string
    {
        if (! filter_var($data->recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $this->logDelivery($data, $agency, $booking, 'skipped', 'Invalid recipient email.');

            return 'skipped';
        }

        if ($this->isDuplicate($agency, $data)) {
            $this->logDelivery($data, $agency, $booking, 'skipped', 'Duplicate suppressed.');

            return 'skipped';
        }

        try {
            $rendered = $this->render(
                agency: $agency,
                eventKey: $data->eventKey,
                templateVariables: $data->templateVariables,
                deliveryVariant: $data->deliveryVariant,
                recipientRole: $data->recipientRole,
                recipientDesignation: $data->recipientDesignation,
                fallbackSubject: $data->fallbackSubject ?? 'Notification',
                fallbackBody: $data->fallbackBody,
                booking: $booking,
                payload: $data->payload,
            );
        } catch (Throwable $e) {
            $this->logDelivery($data, $agency, $booking, 'failed', $e->getMessage());

            return 'failed';
        }

        try {
            $status = $this->dispatchMail(
                $data->recipientEmail,
                $rendered['subject'],
                $rendered['html'],
                $rendered['plain_body'],
                $data->attachments,
            );
            $this->logDelivery($data, $agency, $booking, $status);

            return $status;
        } catch (Throwable $e) {
            $this->logDelivery($data, $agency, $booking, 'failed', $e->getMessage());

            return 'failed';
        }
    }

    /**
     * @param  list<array{name: string, mime: string, content: string}>  $attachments
     * @return 'sent'|'queued'
     */
    public function dispatchMail(
        string $to,
        string $subject,
        string $html,
        string $plainBody,
        array $attachments = [],
    ): string {
        $mailable = new JetpkOperationalEventMail($html, $subject, $plainBody, $attachments);

        if ($this->shouldQueueMail()) {
            Mail::to($to)->queue($mailable);

            return 'queued';
        }

        Mail::to($to)->send($mailable);

        return 'sent';
    }

    protected function isDuplicate(Agency $agency, OperationalEmailData $data): bool
    {
        $minutes = JetpkOperationalEmailEventRegistry::dedupMinutes();
        if ($minutes <= 0) {
            return false;
        }

        $reference = $data->dedupReference ?? $data->eventKey;
        $bucket = $data->recipientBucket ?? '';

        return CommunicationLog::query()
            ->where('agency_id', $agency->id)
            ->where('event', $data->eventKey)
            ->where('recipient_email', $data->recipientEmail)
            ->whereIn('status', ['sent', 'queued', 'sending'])
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->where(function ($query) use ($bucket, $reference): void {
                $query->where('meta->delivery_bucket', $bucket)
                    ->orWhere('meta->dedup_reference', $reference);
            })
            ->exists();
    }

    protected function logDelivery(
        OperationalEmailData $data,
        Agency $agency,
        ?Booking $booking,
        string $status,
        ?string $reason = null,
    ): void {
        Log::info('jetpk.operational_email.delivery', [
            'event_key' => $data->eventKey,
            'recipient_role' => $data->recipientRole,
            'recipient_designation' => $data->recipientDesignation,
            'recipient_email_masked' => $data->maskedRecipientEmail(),
            'reference_id' => $data->dedupReference,
            'delivery_bucket' => $data->recipientBucket,
            'delivery_variant' => $data->deliveryVariant,
            'mailable' => JetpkOperationalEventMail::class,
            'queued' => $this->shouldQueueMail(),
            'status' => $status,
            'reason' => $reason,
            'agency_id' => $agency->id,
            'booking_id' => $booking?->id,
        ]);
    }

    /**
     * @param  array<string, string>  $variantOverrides
     * @return array<string, string>
     */
    protected function variantRuntimeOverrides(array $variantOverrides): array
    {
        $mapped = [];
        foreach ($variantOverrides as $key => $value) {
            $mapped[$key] = $value;
        }

        return $mapped;
    }

    protected function plainBodyFromHtml(string $html, string $fallback): string
    {
        $text = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html))));

        return $text !== '' ? $text : $fallback;
    }

    protected function shouldQueueMail(): bool
    {
        return ! in_array((string) config('mail.default'), ['log', 'array', 'local'], true)
            && (string) config('queue.default') !== 'sync';
    }
}
