@extends(client_layout('dashboard', 'admin'))

@section('title', 'Communication Settings')

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>Communication Settings</h1>
            <p>SMTP, sender identity, report schedules, and WhatsApp readiness.</p>
        </div>
    </div>
@endsection

@section('content')
<div class="jp-cell-sub" style="margin-bottom: 12px;">
    <a href="{{ client_route('admin.settings.communications.notification-events.index') }}">Notification routing</a>
    {{ display_sep_dot() }}
    <a href="{{ client_route('admin.settings.communications.delivery-log.index') }}">Delivery log</a>
    {{ display_sep_dot() }}
    <a href="{{ client_route('admin.settings.communications.templates.index') }}">Email template registry</a>
</div>

<div class="jp-alert jp-alert--info">
    <strong>SMTP test safety</strong> — Test sends are rate-limited. SMTP failures and communication logs redact credentials and truncate long error bodies.
</div>
<div class="jp-alert jp-alert--info">
    Configure per-event <strong>recipient emails</strong> under <a href="{{ client_route('admin.settings.communications.notification-events.index') }}">Notification routing</a> so booking, payment, and supplier alerts reach your ops inbox.
</div>
@if (config('queue.default') !== 'sync')
    <div class="jp-alert jp-alert--warn">
        Outbound email uses <code>{{ config('queue.default') }}</code>. Production requires a queue worker or mail may stay in <strong>queued</strong> status.
    </div>
@endif

@include('themes.admin.jetpakistan.partials.flash')

<div class="jp-card">
    <form method="post" action="{{ client_route('admin.settings.communications.update') }}">
        @csrf
        @method('PATCH')

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
            <div>
                <h2 class="jp-card__title">Email settings</h2>
                <label style="display: flex; align-items: center; gap: 8px; margin-top: 12px;">
                    <input type="checkbox" name="email_enabled" value="1" {{ $settings->email_enabled ? 'checked' : '' }}>
                    <span>Enable email notifications</span>
                </label>
            </div>

            <div>
                <h2 class="jp-card__title">SMTP settings</h2>
                <label style="display: flex; align-items: center; gap: 8px; margin: 12px 0;">
                    <input type="checkbox" name="smtp_enabled" value="1" {{ $settings->smtp_enabled ? 'checked' : '' }}>
                    <span>Use custom SMTP</span>
                </label>
                <input class="jp-input" name="smtp_host" placeholder="SMTP Host" value="{{ old('smtp_host', $settings->smtp_host) }}" style="margin-bottom: 8px;">
                <input class="jp-input" name="smtp_port" placeholder="SMTP Port" value="{{ old('smtp_port', $settings->smtp_port) }}" style="margin-bottom: 8px;">
                <input class="jp-input" name="smtp_username" placeholder="SMTP Username" value="{{ old('smtp_username', $settings->smtp_username) }}" style="margin-bottom: 8px;">
                <input type="password" class="jp-input" name="smtp_password" placeholder="SMTP Password (leave blank to keep existing)">
                @if($settings->maskedSmtpPassword())
                    <p class="jp-cell-sub">Saved password: {{ $settings->maskedSmtpPassword() }}</p>
                @endif
            </div>

            <div>
                <h2 class="jp-card__title">Sender identity</h2>
                <input class="jp-input" name="mail_from_name" placeholder="From name" value="{{ old('mail_from_name', $settings->mail_from_name) }}" style="margin: 12px 0 8px;">
                <input class="jp-input" name="mail_from_email" placeholder="From email" value="{{ old('mail_from_email', $settings->mail_from_email) }}" style="margin-bottom: 8px;">
                <input class="jp-input" name="reply_to_email" placeholder="Reply-to email" value="{{ old('reply_to_email', $settings->reply_to_email) }}">
            </div>

            <div>
                <h2 class="jp-card__title">Report schedules</h2>
                <label style="display: flex; align-items: center; gap: 8px; margin: 12px 0 8px;">
                    <input type="checkbox" name="daily_report_enabled" value="1" {{ $settings->daily_report_enabled ? 'checked' : '' }}>
                    <span>Enable daily report</span>
                </label>
                <input class="jp-input" type="time" name="daily_report_time" value="{{ old('daily_report_time', $settings->daily_report_time ?? '08:00') }}" style="margin-bottom: 12px;">

                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <input type="checkbox" name="weekly_report_enabled" value="1" {{ $settings->weekly_report_enabled ? 'checked' : '' }}>
                    <span>Enable weekly report</span>
                </label>
                <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                    <select class="jp-select" name="weekly_report_day" style="flex: 1;">
                        @foreach(['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day)
                            <option value="{{ $day }}" {{ old('weekly_report_day', $settings->weekly_report_day ?? 'monday') === $day ? 'selected' : '' }}>{{ ucfirst($day) }}</option>
                        @endforeach
                    </select>
                    <input class="jp-input" type="time" name="weekly_report_time" value="{{ old('weekly_report_time', $settings->weekly_report_time ?? '08:00') }}" style="flex: 1;">
                </div>

                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <input type="checkbox" name="monthly_report_enabled" value="1" {{ $settings->monthly_report_enabled ? 'checked' : '' }}>
                    <span>Enable monthly report</span>
                </label>
                <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                    <input class="jp-input" type="number" min="1" max="28" name="monthly_report_day" placeholder="Day (1-28)" value="{{ old('monthly_report_day', $settings->monthly_report_day ?? 1) }}" style="flex: 1;">
                    <input class="jp-input" type="time" name="monthly_report_time" value="{{ old('monthly_report_time', $settings->monthly_report_time ?? '08:00') }}" style="flex: 1;">
                </div>

                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="monthly_ledger_enabled" value="1" {{ $settings->monthly_ledger_enabled ? 'checked' : '' }}>
                    <span>Enable monthly ledger email</span>
                </label>
            </div>

            <div>
                <h2 class="jp-card__title">WhatsApp readiness</h2>
                <label style="display: flex; align-items: center; gap: 8px; margin: 12px 0 8px;">
                    <input type="checkbox" name="whatsapp_enabled" value="1" {{ $settings->whatsapp_enabled ? 'checked' : '' }}>
                    <span>Enable WhatsApp rules (no sending yet)</span>
                </label>
                <input class="jp-input" name="whatsapp_provider" placeholder="Provider: meta_cloud_api|twilio|custom" value="{{ old('whatsapp_provider', $settings->whatsapp_provider) }}" style="margin-bottom: 8px;">
                <input class="jp-input" name="whatsapp_phone_number_id" placeholder="Phone Number ID" value="{{ old('whatsapp_phone_number_id', $settings->whatsapp_phone_number_id) }}" style="margin-bottom: 8px;">
                <input class="jp-input" name="whatsapp_business_account_id" placeholder="Business Account ID" value="{{ old('whatsapp_business_account_id', $settings->whatsapp_business_account_id) }}" style="margin-bottom: 8px;">
                <input type="password" class="jp-input" name="whatsapp_access_token" placeholder="Access token (leave blank to keep existing)">
                @if($settings->maskedWhatsappToken())
                    <p class="jp-cell-sub">Saved token: {{ $settings->maskedWhatsappToken() }}</p>
                @endif
            </div>
        </div>

        <div style="margin-top: 20px;">
            <button class="jp-btn" type="submit">Save settings</button>
        </div>
    </form>

    <hr style="margin: 24px 0; border: 0; border-top: 1px solid var(--line-soft);">

    <form method="post" action="{{ client_route('admin.settings.communications.test-email') }}" class="jp-filterbar" style="background: transparent; border: 0; padding: 0;">
        @csrf
        <div class="jp-filterbar__field" style="flex: 1;">
            <label class="jp-label" for="test-recipient">Test recipient email</label>
            <input class="jp-input" type="email" id="test-recipient" name="recipient_email" placeholder="ops@example.com" required>
        </div>
        <div class="jp-filterbar__actions">
            <button class="jp-btn jp-btn--outline" type="submit">Send test email</button>
        </div>
    </form>
</div>
@endsection
