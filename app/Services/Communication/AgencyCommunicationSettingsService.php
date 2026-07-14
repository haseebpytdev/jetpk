<?php

namespace App\Services\Communication;

use App\Mail\CommunicationSettingsTestMail;
use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\AgencyMessageTemplate;
use App\Models\AuditLog;
use App\Models\CommunicationLog;
use App\Models\User;
use App\Support\Emails\EmailBaseVariables;
use App\Support\Emails\EmailTemplateStringRenderer;
use App\Support\Emails\SettingsTestEmailRenderer;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class AgencyCommunicationSettingsService
{
    public function __construct(
        protected EmailTemplateStringRenderer $stringRenderer,
    ) {}

    public function getOrCreateSettings(Agency $agency): AgencyCommunicationSetting
    {
        $setting = AgencyCommunicationSetting::query()->firstOrCreate(
            ['agency_id' => $agency->id],
            ['notification_rules' => ['email' => true, 'whatsapp' => false]]
        );

        return $setting->fresh();
    }

    public function updateSettings(Agency $agency, User $actor, array $data): AgencyCommunicationSetting
    {
        $settings = $this->getOrCreateSettings($agency);
        $settings->fill($data);
        $settings->save();

        $this->writeAudit($agency, $actor, 'communication_settings_updated', [
            'email_enabled' => $settings->email_enabled,
            'smtp_enabled' => $settings->smtp_enabled,
            'smtp_host' => $settings->smtp_host,
            'smtp_port' => $settings->smtp_port,
            'smtp_username' => $settings->smtp_username,
            'smtp_password' => $settings->maskedSmtpPassword(),
            'whatsapp_enabled' => $settings->whatsapp_enabled,
            'whatsapp_provider' => $settings->whatsapp_provider,
            'whatsapp_access_token' => $settings->maskedWhatsappToken(),
        ]);

        return $settings;
    }

    public function updateTemplate(Agency $agency, User $actor, string $event, string $channel, array $data): AgencyMessageTemplate
    {
        $template = AgencyMessageTemplate::query()->firstOrNew([
            'agency_id' => $agency->id,
            'event' => $event,
            'channel' => $channel,
        ]);
        $template->fill($data);
        $template->save();

        $this->writeAudit($agency, $actor, 'communication_template_updated', [
            'event' => $event,
            'channel' => $channel,
            'is_enabled' => $template->is_enabled,
        ]);

        return $template;
    }

    public function resetTemplate(Agency $agency, User $actor, string $event, string $channel): void
    {
        AgencyMessageTemplate::query()
            ->where('agency_id', $agency->id)
            ->where('event', $event)
            ->where('channel', $channel)
            ->delete();

        $this->writeAudit($agency, $actor, 'communication_template_reset', [
            'event' => $event,
            'channel' => $channel,
        ]);
    }

    /** @return array{subject: string|null, body: string, used_template: bool, is_enabled: bool} */
    public function renderTemplate(Agency $agency, string $event, string $channel, array $variables): array
    {
        $template = AgencyMessageTemplate::query()
            ->where('agency_id', $agency->id)
            ->where('event', $event)
            ->where('channel', $channel)
            ->first();

        if ($template === null) {
            return [
                'subject' => null,
                'body' => '',
                'used_template' => false,
                'is_enabled' => true,
            ];
        }

        $merged = EmailBaseVariables::merge($agency, null, $variables);

        return [
            'subject' => $this->stringRenderer->render((string) $template->subject, $merged, [
                'event_key' => $event,
            ])->output,
            'body' => $this->stringRenderer->render((string) $template->body, $merged, [
                'event_key' => $event,
            ])->output,
            'used_template' => true,
            'is_enabled' => $template->is_enabled,
        ];
    }

    public function testEmailSettings(Agency $agency, User $actor, string $recipientEmail): CommunicationLog
    {
        $settings = $this->getOrCreateSettings($agency);
        $rendered = app(SettingsTestEmailRenderer::class)->render($agency);

        $log = CommunicationLog::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $actor->id,
            'channel' => 'email',
            'event' => SettingsTestEmailRenderer::EVENT,
            'recipient_email' => $recipientEmail,
            'subject' => $rendered->subject,
            'message' => $rendered->innerBody,
            'status' => 'queued',
            'provider' => config('mail.default'),
            'meta' => [
                'smtp_enabled' => $settings->smtp_enabled,
                'modern_layout' => true,
                'used_db_template' => $rendered->usedDbTemplate,
            ],
        ]);

        try {
            Mail::to($recipientEmail)->send(
                new CommunicationSettingsTestMail($rendered->html, $rendered->subject)
            );

            $log->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
        } catch (Throwable $e) {
            $errorMessage = $this->redactOutboundMailError($e->getMessage(), $settings->smtp_password);
            $log->forceFill([
                'status' => 'failed',
                'error_message' => $errorMessage,
            ])->save();
        }

        return $log;
    }

    /** Redact secrets so SMTP failures never echo passwords/tokens in logs. */
    private function redactOutboundMailError(string $message, ?string $smtpPassword): string
    {
        $m = $message;
        if (filled($smtpPassword)) {
            $m = str_replace((string) $smtpPassword, '[REDACTED]', $m);
        }
        $m = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer [redacted]', $m) ?? $m;
        $m = preg_replace('/(api[_-]?key|access[_-]?token|client[_-]?secret)\s*[:=]\s*\S+/i', '$1=[redacted]', $m) ?? $m;

        return Str::limit((string) $m, 2000);
    }

    /** @return array{status: string, missing_fields: array<int, string>} */
    public function testWhatsappReadiness(Agency $agency, User $actor): array
    {
        $settings = $this->getOrCreateSettings($agency);
        $required = [
            'whatsapp_provider' => $settings->whatsapp_provider,
            'whatsapp_phone_number_id' => $settings->whatsapp_phone_number_id,
            'whatsapp_business_account_id' => $settings->whatsapp_business_account_id,
            'whatsapp_access_token' => $settings->whatsapp_access_token,
        ];

        $missing = collect($required)->filter(fn ($value) => blank($value))->keys()->values()->all();
        $status = $missing === [] ? 'ready_for_review' : 'missing_fields';

        $this->writeAudit($agency, $actor, 'whatsapp_readiness_checked', ['status' => $status, 'missing_fields' => $missing]);

        return ['status' => $status, 'missing_fields' => $missing];
    }

    private function writeAudit(Agency $agency, User $actor, string $action, array $newValues): void
    {
        AuditLog::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => Agency::class,
            'auditable_id' => $agency->id,
            'properties' => [
                'old_values' => [],
                'new_values' => SensitiveDataRedactor::redact($newValues),
            ],
        ]);
    }
}
