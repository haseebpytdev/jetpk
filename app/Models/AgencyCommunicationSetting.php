<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agency_id',
    'email_enabled',
    'smtp_enabled',
    'smtp_host',
    'smtp_port',
    'smtp_username',
    'smtp_password',
    'smtp_encryption',
    'daily_report_enabled',
    'daily_report_time',
    'weekly_report_enabled',
    'weekly_report_day',
    'weekly_report_time',
    'monthly_report_enabled',
    'monthly_report_day',
    'monthly_report_time',
    'monthly_ledger_enabled',
    'mail_from_name',
    'mail_from_email',
    'reply_to_email',
    'whatsapp_enabled',
    'whatsapp_provider',
    'whatsapp_phone_number_id',
    'whatsapp_business_account_id',
    'whatsapp_access_token',
    'whatsapp_webhook_verify_token',
    'whatsapp_default_country_code',
    'whatsapp_settings',
    'notification_rules',
    'meta',
])]
#[Hidden(['smtp_password', 'whatsapp_access_token', 'whatsapp_webhook_verify_token'])]
class AgencyCommunicationSetting extends Model
{
    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'smtp_enabled' => 'boolean',
            'smtp_password' => 'encrypted',
            'daily_report_enabled' => 'boolean',
            'weekly_report_enabled' => 'boolean',
            'monthly_report_enabled' => 'boolean',
            'monthly_ledger_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'whatsapp_access_token' => 'encrypted',
            'whatsapp_webhook_verify_token' => 'encrypted',
            'whatsapp_settings' => 'array',
            'notification_rules' => 'array',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function maskedSmtpPassword(): ?string
    {
        return $this->smtp_password ? '********' : null;
    }

    public function maskedWhatsappToken(): ?string
    {
        return $this->whatsapp_access_token ? '********' : null;
    }
}
