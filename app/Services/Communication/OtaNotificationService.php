<?php

namespace App\Services\Communication;

use App\Enums\AccountType;
use App\Mail\BookingUniversalNotification;
use App\Mail\OtaOperationalNotificationMail;
use App\Models\Agency;
use App\Models\AgencyNotificationSetting;
use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Models\User;
use App\Support\Branding\BrandDisplayResolver;
use App\Support\Emails\EmailBaseVariables;
use App\Support\Emails\JetpkEmailEventRenderer;
use App\Support\Emails\JetpkOperationalEmailEventRegistry;
use App\Support\Emails\OtaOperationalEmailRenderer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class OtaNotificationService
{
    public function __construct(
        protected AgencyCommunicationSettingsService $communicationSettingsService,
        protected NotificationRecipientResolver $recipientResolver,
        protected NotificationPayloadSanitizer $payloadSanitizer,
        protected NotificationTemplateRenderer $templateRenderer,
        protected OtaOperationalEmailRenderer $operationalEmailRenderer,
        protected JetpkEmailEventRenderer $jetpkEmailEventRenderer,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, scalar|null>  $templateVariables
     * @param  list<array{name: string, mime: string, content: string}>  $attachments
     */
    public function send(
        Agency $agency,
        string $eventKey,
        array $payload = [],
        ?Booking $booking = null,
        ?User $actor = null,
        ?string $fallbackSubject = null,
        string $fallbackBody = 'A new OTA event was recorded.',
        array $templateVariables = [],
        array $attachments = [],
        array $recipientContext = [],
    ): void {
        $fallbackSubject ??= BrandDisplayResolver::displayName($agency->agencySetting).' Notification';
        $recipientContext = $this->bookingRecipientContext($booking, $actor, $recipientContext);

        if (
            JetpkOperationalEmailEventRegistry::isJetpkClient()
            && JetpkOperationalEmailEventRegistry::requiresPerBucketDelivery($eventKey)
            && ! ($recipientContext['_per_bucket_child'] ?? false)
        ) {
            $this->sendPerBucketDeliveries(
                $agency,
                $eventKey,
                $payload,
                $booking,
                $actor,
                $fallbackSubject,
                $fallbackBody,
                $templateVariables,
                $attachments,
                $recipientContext,
            );

            return;
        }

        try {
            $settings = $this->communicationSettingsService->getOrCreateSettings($agency);
            $eventSetting = $this->getOrCreateEventSetting($agency, $eventKey);
            $recipientBundle = $this->recipientResolver->resolve($agency, $eventKey, $booking, $actor, $recipientContext);
            $this->logSkippedRecipientBuckets($agency, $eventKey, $booking, $recipientBundle['skipped_buckets']);
            $scope = $recipientBundle['scope'];
            $safePayload = $this->payloadSanitizer->sanitizeForScope($payload, $scope);

            $mergedVariables = EmailBaseVariables::merge($agency, $booking, $templateVariables);
            if (isset($recipientContext['delivery_variant']) && is_string($recipientContext['delivery_variant'])) {
                $variantOverrides = JetpkOperationalEmailEventRegistry::variantContentOverrides(
                    $eventKey,
                    $recipientContext['delivery_variant'],
                );
                $mergedVariables = array_merge($mergedVariables, $variantOverrides);
            }

            $rendered = $this->renderOperationalEmail(
                $agency,
                $eventKey,
                $mergedVariables,
                $fallbackSubject,
                $fallbackBody,
                $booking,
            );
        } catch (Throwable $e) {
            Log::warning('ota.notification.render_failed', [
                'agency_id' => $agency->id,
                'booking_id' => $booking?->id,
                'booking_reference' => (string) ($booking?->reference_code ?? ''),
                'event' => $eventKey,
                'class' => self::class,
                'method' => 'send',
                'message' => $e->getMessage(),
            ]);

            return;
        }

        $universalPayload = $this->universalPayloadFrom($safePayload);
        if ($universalPayload !== null) {
            $universalPayload['subject'] = $rendered->subject;
        }

        if (! $settings->email_enabled || ! $eventSetting->enabled || ! $rendered->templateEnabled) {
            $this->logSkipped($agency, $eventKey, $booking, $actor, $recipientBundle, $safePayload, 'Notification disabled by settings/template.', $recipientContext);

            return;
        }

        if ($recipientBundle['to'] === []) {
            $this->logSkipped($agency, $eventKey, $booking, $actor, $recipientBundle, $safePayload, 'No recipients resolved.', $recipientContext);

            return;
        }

        $log = CommunicationLog::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking?->id,
            'user_id' => $actor?->id ?? $booking?->customer_id,
            'channel' => 'email',
            'event' => $eventKey,
            'recipient_email' => implode(', ', $recipientBundle['to']),
            'subject' => $rendered->subject,
            'message' => $rendered->plainBody,
            'status' => $this->isImmediateMailer() ? 'sending' : 'queued',
            'provider' => (string) config('mail.default'),
            'meta' => [
                'cc' => $recipientBundle['cc'],
                'bcc' => $recipientBundle['bcc'],
                'scope' => $scope,
                'payload' => $safePayload,
                'attachments' => array_map(fn (array $a): array => ['name' => $a['name'], 'mime' => $a['mime']], $attachments),
                'modern_layout' => ! JetpkOperationalEmailEventRegistry::isJetpkClient(),
                'jetpk_universal_layout' => JetpkOperationalEmailEventRegistry::isJetpkClient(),
                'delivery_bucket' => $recipientContext['delivery_bucket'] ?? null,
                'delivery_variant' => $recipientContext['delivery_variant'] ?? null,
                'universal_mailable' => $universalPayload !== null,
                'notification_type' => $universalPayload['type'] ?? $eventKey,
                'recipient_type' => $scope,
                'attempts' => 1,
                'used_db_template' => $rendered->usedTemplate,
                'recipient_buckets' => $recipientBundle['buckets'],
                'skipped_recipient_buckets' => $recipientBundle['skipped_buckets'],
                'deduplicated_recipient_buckets' => $this->deduplicatedRecipientBuckets($eventKey, $recipientContext),
                'agent_staff_creator_source' => $recipientContext['agent_staff_creator_source'] ?? null,
                'agent_staff_creator_user_id' => $recipientContext['agent_staff_creator_user_id'] ?? null,
                'booking_creator_user_id' => $recipientContext['booking_creator_user_id'] ?? null,
                'booking_creator_role' => $recipientContext['booking_creator_role'] ?? null,
                'booking_creator_source' => $recipientContext['booking_creator_source'] ?? null,
            ],
        ]);

        try {
            $deliveryStatus = $this->dispatchMail(
                $recipientBundle['to'],
                $recipientBundle['cc'],
                $recipientBundle['bcc'],
                $rendered->subject,
                $rendered->html,
                $rendered->plainBody,
                $attachments,
                $log->id,
                $universalPayload,
            );
            $log->forceFill([
                'status' => $deliveryStatus,
                'sent_at' => $deliveryStatus === 'sent' ? now() : null,
            ])->save();
        } catch (Throwable $e) {
            $log->forceFill([
                'status' => 'failed',
                'error_message' => $this->safeError($e->getMessage(), $settings->smtp_password),
            ])->save();
        }
    }

    /**
     * Resend a previously logged email (failed or skipped). Creates a new communication log row.
     *
     * @throws InvalidArgumentException
     */
    public function resendCommunicationLog(CommunicationLog $original, User $actor): CommunicationLog
    {
        $agency = Agency::query()->findOrFail($original->agency_id);
        $settings = $this->communicationSettingsService->getOrCreateSettings($agency);

        $to = collect(explode(',', (string) $original->recipient_email))
            ->map(fn (string $s): string => trim($s))
            ->filter(fn (string $e): bool => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();

        if ($to === []) {
            throw new InvalidArgumentException('Original log has no valid recipient addresses.');
        }

        $meta = is_array($original->meta) ? $original->meta : [];
        $cc = isset($meta['cc']) && is_array($meta['cc']) ? array_values(array_filter($meta['cc'], fn ($e): bool => is_string($e) && filter_var($e, FILTER_VALIDATE_EMAIL))) : [];
        $bcc = isset($meta['bcc']) && is_array($meta['bcc']) ? array_values(array_filter($meta['bcc'], fn ($e): bool => is_string($e) && filter_var($e, FILTER_VALIDATE_EMAIL))) : [];

        $subject = (string) ($original->subject ?? 'Notification resend');
        $plainBody = (string) ($original->message ?? '');
        $wrapped = $this->operationalEmailRenderer->wrapStoredBody(
            $agency,
            (string) $original->event,
            $subject,
            $plainBody,
        );

        $log = CommunicationLog::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $original->booking_id,
            'user_id' => $actor->id,
            'channel' => 'email',
            'event' => $original->event,
            'recipient_email' => implode(', ', $to),
            'subject' => $subject,
            'message' => $plainBody,
            'status' => 'sending',
            'provider' => (string) config('mail.default'),
            'meta' => [
                'cc' => $cc,
                'bcc' => $bcc,
                'resend' => true,
                'resend_of_log_id' => $original->id,
                'modern_layout' => true,
            ],
        ]);

        try {
            $deliveryStatus = $this->dispatchMail(
                $to,
                $cc,
                $bcc,
                $wrapped->subject,
                $wrapped->html,
                $wrapped->plainBody,
                [],
                $log->id,
            );
            $log->forceFill([
                'status' => $deliveryStatus,
                'sent_at' => $deliveryStatus === 'sent' ? now() : null,
                'error_message' => null,
            ])->save();
        } catch (Throwable $e) {
            $log->forceFill([
                'status' => 'failed',
                'error_message' => $this->safeError($e->getMessage(), $settings->smtp_password),
            ])->save();
        }

        return $log->fresh();
    }

    /**
     * @param  array<int, string>  $to
     * @param  array<int, string>  $cc
     * @param  array<int, string>  $bcc
     * @param  list<array{name: string, mime: string, content: string}>  $attachments
     */
    private function dispatchMail(
        array $to,
        array $cc,
        array $bcc,
        string $subject,
        string $htmlBody,
        string $plainBody,
        array $attachments,
        ?int $communicationLogId = null,
        ?array $universalPayload = null,
    ): string {
        if ($this->shouldQueueMail()) {
            dispatch(function () use ($to, $cc, $bcc, $subject, $htmlBody, $plainBody, $attachments, $communicationLogId, $universalPayload): void {
                try {
                    $this->sendMailNow($to, $cc, $bcc, $subject, $htmlBody, $plainBody, $attachments, $universalPayload);
                    if ($communicationLogId !== null) {
                        CommunicationLog::query()->whereKey($communicationLogId)->update([
                            'status' => 'sent',
                            'sent_at' => now(),
                            'error_message' => null,
                        ]);
                    }
                } catch (Throwable $e) {
                    if ($communicationLogId !== null) {
                        CommunicationLog::query()->whereKey($communicationLogId)->update([
                            'status' => 'failed',
                            'error_message' => Str::limit($e->getMessage(), 2000),
                        ]);
                    }
                }
            });

            return 'queued';
        }

        $this->sendMailNow($to, $cc, $bcc, $subject, $htmlBody, $plainBody, $attachments, $universalPayload);

        return 'sent';
    }

    /**
     * @param  array<int, string>  $to
     * @param  array<int, string>  $cc
     * @param  array<int, string>  $bcc
     * @param  list<array{name: string, mime: string, content: string}>  $attachments
     */
    private function sendMailNow(
        array $to,
        array $cc,
        array $bcc,
        string $subject,
        string $htmlBody,
        string $plainBody,
        array $attachments,
        ?array $universalPayload = null,
    ): void {
        $mail = $universalPayload !== null
            ? new BookingUniversalNotification($universalPayload)
            : new OtaOperationalNotificationMail($htmlBody, $subject, $plainBody, $attachments);
        $pending = Mail::to($to);
        if ($cc !== []) {
            $pending->cc($cc);
        }
        if ($bcc !== []) {
            $pending->bcc($bcc);
        }
        $pending->send($mail);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function universalPayloadFrom(array $payload): ?array
    {
        $candidate = $payload['universal_email'] ?? null;

        return is_array($candidate) && isset($candidate['type'], $candidate['subject'])
            ? $candidate
            : null;
    }

    private function getOrCreateEventSetting(Agency $agency, string $eventKey): AgencyNotificationSetting
    {
        if (! Schema::hasTable('agency_notification_settings')) {
            return new AgencyNotificationSetting([
                'agency_id' => $agency->id,
                'event_key' => $eventKey,
                'channel' => 'email',
                'enabled' => true,
                'recipient_scope' => 'admin',
                'digest_mode' => 'immediate',
            ]);
        }

        return AgencyNotificationSetting::query()->firstOrCreate(
            [
                'agency_id' => $agency->id,
                'event_key' => $eventKey,
                'channel' => 'email',
            ],
            [
                'enabled' => true,
                'recipient_scope' => 'admin',
                'digest_mode' => 'immediate',
            ]
        );
    }

    /**
     * @param  array{to: array<int, string>, cc: array<int, string>, bcc: array<int, string>, scope: string, buckets: list<string>, skipped_buckets: list<array{bucket: string, reason: string}>}  $recipientBundle
     * @param  array<string, mixed>  $payload
     */
    private function logSkipped(
        Agency $agency,
        string $eventKey,
        ?Booking $booking,
        ?User $actor,
        array $recipientBundle,
        array $payload,
        string $reason,
        array $recipientContext = [],
    ): void {
        $recipients = $recipientBundle['to'];

        CommunicationLog::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking?->id,
            'user_id' => $actor?->id ?? $booking?->customer_id,
            'channel' => 'email',
            'event' => $eventKey,
            'recipient_email' => $recipients !== [] ? implode(', ', $recipients) : null,
            'status' => 'skipped',
            'error_message' => $reason,
            'provider' => (string) config('mail.default'),
            'meta' => [
                'booking_reference' => (string) ($booking?->reference_code ?? ''),
                'payload' => $payload,
                'recipient_type' => $recipientBundle['scope'],
                'recipient_buckets' => $recipientBundle['buckets'],
                'skipped_recipient_buckets' => $recipientBundle['skipped_buckets'],
                'skipped_reason' => $reason,
                'deduplicated_recipient_buckets' => $this->deduplicatedRecipientBuckets($eventKey, $recipientContext),
                'agent_staff_creator_source' => $recipientContext['agent_staff_creator_source'] ?? null,
                'agent_staff_creator_user_id' => $recipientContext['agent_staff_creator_user_id'] ?? null,
                'booking_creator_user_id' => $recipientContext['booking_creator_user_id'] ?? null,
                'booking_creator_role' => $recipientContext['booking_creator_role'] ?? null,
                'booking_creator_source' => $recipientContext['booking_creator_source'] ?? null,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function bookingRecipientContext(?Booking $booking, ?User $actor, array $context): array
    {
        if ($booking !== null) {
            $creatorContext = $this->bookingCreatorContext($booking);
            if ($actor !== null && $this->isAgencySideBookingActor($actor)) {
                $context['booking_creator_user_id'] ??= $actor->id;
                $context['booking_creator_role'] ??= $actor->account_type instanceof AccountType
                    ? $actor->account_type->value
                    : (string) $actor->account_type;
                $context['booking_creator_source'] ??= 'direct_actor';
            } elseif (($creatorContext['creator_user_id'] ?? 0) > 0) {
                $context['booking_creator_user_id'] ??= (int) $creatorContext['creator_user_id'];
                $context['booking_creator_role'] ??= is_string($creatorContext['creator_role'] ?? null)
                    ? $creatorContext['creator_role']
                    : null;
                $source = $creatorContext['creator_source'] ?? null;
                $context['booking_creator_source'] ??= is_string($source) && trim($source) !== ''
                    ? $source
                    : 'booking_creator_context';
            }
        }

        if ($actor?->isAgentStaff()) {
            $context['agent_staff_creator_email'] ??= $actor->email;
            $context['agent_staff_creator_user_id'] ??= $actor->id;
            $context['agent_staff_creator_source'] ??= 'direct_actor';

            return $context;
        }

        $creatorContext = $this->bookingCreatorContext($booking);
        $storedAgentStaffCreatorId = (int) ($creatorContext['agent_staff_creator_user_id'] ?? 0);
        if ($storedAgentStaffCreatorId > 0) {
            $context['agent_staff_creator_user_id'] ??= $storedAgentStaffCreatorId;
            $context['agent_staff_creator_source'] ??= 'booking_creator_context';
        } elseif (in_array('agent_staff_creator', $context['notify_buckets'] ?? [], true)) {
            $context['agent_staff_creator_source'] ??= 'missing';
        }

        return $context;
    }

    private function isAgencySideBookingActor(User $actor): bool
    {
        return $actor->isAgent() || $actor->isAgentStaff() || $actor->isAgencyAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingCreatorContext(?Booking $booking): array
    {
        $meta = is_array($booking?->meta) ? $booking->meta : [];
        $context = $meta['creator_context'] ?? [];

        return is_array($context) ? $context : [];
    }

    /**
     * @param  array<string, mixed>  $recipientContext
     * @return list<array{bucket: string, reason: string}>
     */
    private function deduplicatedRecipientBuckets(string $eventKey, array $recipientContext): array
    {
        $buckets = $recipientContext['deduplicated_buckets'] ?? [];
        if ($buckets === [] && in_array($eventKey, ['payment_verified', 'payment_rejected'], true)) {
            $buckets = ['customer_party', 'agent_booking'];
        }

        if (! is_array($buckets)) {
            return [];
        }

        return collect($buckets)
            ->filter(fn ($bucket): bool => is_string($bucket) && trim($bucket) !== '')
            ->map(fn (string $bucket): array => [
                'bucket' => trim($bucket),
                'reason' => 'Skipped because the direct booking communication customer email is the single customer-facing path for this event.',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{bucket: string, reason: string}>  $skippedBuckets
     */
    private function logSkippedRecipientBuckets(Agency $agency, string $eventKey, ?Booking $booking, array $skippedBuckets): void
    {
        foreach ($skippedBuckets as $skippedBucket) {
            Log::warning('OTA notification recipient bucket skipped.', [
                'agency_id' => $agency->id,
                'booking_id' => $booking?->id,
                'event' => $eventKey,
                'bucket' => $skippedBucket['bucket'],
                'reason' => $skippedBucket['reason'],
            ]);
        }
    }

    private function safeError(string $message, ?string $smtpPassword): string
    {
        $m = $message;
        if (filled($smtpPassword)) {
            $m = str_replace((string) $smtpPassword, '[REDACTED]', $m);
        }
        $m = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer [redacted]', $m) ?? $m;
        $m = preg_replace('/duffel_(test|live)_[A-Za-z0-9._\-]+/i', 'duffel_$1_[redacted]', $m) ?? $m;
        $m = preg_replace('/(api[_-]?key|access[_-]?token|client[_-]?secret)\s*[:=]\s*\S+/i', '$1=[redacted]', $m) ?? $m;

        return Str::limit((string) $m, 2000);
    }

    private function isImmediateMailer(): bool
    {
        return in_array((string) config('mail.default'), ['log', 'array', 'local'], true);
    }

    private function shouldQueueMail(): bool
    {
        return ! $this->isImmediateMailer()
            && (string) config('queue.default') !== 'sync';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, scalar|null>  $templateVariables
     * @param  list<array{name: string, mime: string, content: string}>  $attachments
     * @param  array<string, mixed>  $recipientContext
     */
    private function sendPerBucketDeliveries(
        Agency $agency,
        string $eventKey,
        array $payload,
        ?Booking $booking,
        ?User $actor,
        ?string $fallbackSubject,
        string $fallbackBody,
        array $templateVariables,
        array $attachments,
        array $recipientContext,
    ): void {
        $buckets = JetpkOperationalEmailEventRegistry::bucketsForEvent($eventKey);
        $seenEmails = [];

        foreach ($buckets as $bucket) {
            $resolved = $this->recipientResolver->resolveBucket($agency, $bucket, $booking, $actor, $recipientContext);
            if ($resolved['skipped']) {
                Log::warning('jetpk.operational_email.bucket_skipped', [
                    'agency_id' => $agency->id,
                    'event' => $eventKey,
                    'bucket' => $bucket,
                    'reason' => $resolved['reason'],
                ]);

                continue;
            }

            $variant = JetpkOperationalEmailEventRegistry::variantForBucket($eventKey, $bucket);
            foreach ($resolved['emails'] as $email) {
                $normalized = strtolower(trim($email));
                if (isset($seenEmails[$normalized])) {
                    Log::info('jetpk.operational_email.duplicate_suppressed', [
                        'event' => $eventKey,
                        'bucket' => $bucket,
                        'recipient_email_masked' => $this->maskEmail($email),
                    ]);

                    continue;
                }
                $seenEmails[$normalized] = true;

                $childContext = array_merge($recipientContext, [
                    '_per_bucket_child' => true,
                    'notify_buckets' => [$bucket],
                    'delivery_bucket' => $bucket,
                    'delivery_variant' => $variant,
                ]);

                match ($bucket) {
                    'user' => $childContext['user_email'] = $email,
                    'staff' => $childContext['staff_email'] = $email,
                    'applicant' => $childContext['applicant_email'] = $email,
                    default => null,
                };

                $this->send(
                    agency: $agency,
                    eventKey: $eventKey,
                    payload: $payload,
                    booking: $booking,
                    actor: $actor,
                    fallbackSubject: $fallbackSubject,
                    fallbackBody: $fallbackBody,
                    templateVariables: $templateVariables,
                    attachments: $attachments,
                    recipientContext: $childContext,
                );
            }
        }
    }

    /**
     * @param  array<string, scalar|null>  $variables
     */
    private function renderOperationalEmail(
        Agency $agency,
        string $eventKey,
        array $variables,
        string $fallbackSubject,
        string $fallbackBody,
        ?Booking $booking = null,
    ): \App\Support\Emails\OtaOperationalEmailRendered {
        if (JetpkOperationalEmailEventRegistry::isJetpkClient()) {
            try {
                JetpkOperationalEmailEventRegistry::assertKnownEvent($eventKey);
                $rendered = $this->jetpkEmailEventRenderer->render(
                    eventKey: $eventKey,
                    agency: $agency,
                    runtimeVariables: $variables,
                );
                $plain = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $rendered->html))));

                return new \App\Support\Emails\OtaOperationalEmailRendered(
                    subject: $rendered->subject !== '' ? $rendered->subject : $fallbackSubject,
                    html: $rendered->html,
                    plainBody: $plain !== '' ? $plain : $fallbackBody,
                    usedTemplate: $rendered->usedDbTemplate,
                    templateEnabled: (bool) ($rendered->content['enabled'] ?? true),
                    profile: \App\Support\Branding\CompanyEmailProfileResolver::resolve($agency),
                );
            } catch (Throwable $e) {
                Log::warning('jetpk.operational_email.render_fallback', [
                    'event' => $eventKey,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $this->operationalEmailRenderer->render(
            $agency,
            $eventKey,
            $variables,
            $fallbackSubject,
            $fallbackBody,
            $booking,
        );
    }

    private function maskEmail(string $email): string
    {
        $email = strtolower(trim($email));
        if (! str_contains($email, '@')) {
            return '[invalid]';
        }
        [$local, $domain] = explode('@', $email, 2);

        return substr($local, 0, 1).'***@'.$domain;
    }
}
