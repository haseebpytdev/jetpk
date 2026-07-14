@extends(client_layout('dashboard', 'admin'))

@section('title', 'Communication Settings')

@section('page-header')
    <h1 class="jp-page-title">Communication Settings</h1>
@endsection

@section('content')
    <div class="text-secondary small mb-2">
        <a href="{{ route('admin.settings.communications.notification-events.index') }}">Notification routing</a>
        <span class="text-muted mx-1">{{ display_sep_dot() }}</span>
        <a href="{{ route('admin.settings.communications.delivery-log.index') }}">Delivery log</a>
        <span class="text-muted mx-1">{{ display_sep_dot() }}</span>
        <a href="{{ route('admin.settings.communications.templates.index') }}">Email template registry</a>
    </div>
    <div class="alert alert-light border small mb-4">
        <div class="fw-semibold mb-1">SMTP test safety</div>
        <p class="mb-0">Test sends are rate-limited. SMTP failures and communication logs redact credentials and truncate long error bodies so secrets do not leak into the dashboard.</p>
    </div>
    <div class="jp-alert jp-alert--info border small mb-4" data-testid="ota-notification-recipient-guidance">
        <div class="fw-semibold mb-1">Operational alert recipients</div>
        <p class="mb-1">Configure per-event <strong>recipient emails</strong> under <a href="{{ route('admin.settings.communications.notification-events.index') }}">Notification routing</a> so booking, payment, and supplier alerts reach your ops inbox even when no platform admin user is attached to this agency.</p>
        <p class="mb-0">When the admin recipient bucket is empty, the system falls back to this agency's <strong>support email</strong> (Agency settings) and then <code>ota-brand.support_email</code>. Legacy agency admin accounts are not used for admin notifications and cannot access this console.</p>
    </div>
    @if (config('queue.default') !== 'sync')
        <div class="jp-alert jp-alert--warn border small mb-4" data-testid="ota-queue-worker-readiness">
            <div class="fw-semibold mb-1">Queue worker required</div>
            <p class="mb-1">Outbound email and other queued jobs use <code>{{ config('queue.default') }}</code>. Production requires a queue worker (Supervisor or cron <code>queue:work --stop-when-empty</code>) or mail may stay in <strong>queued</strong> status.</p>
            <p class="mb-0">Scheduled daily/weekly/monthly reports also require OS cron for <code>php artisan schedule:run</code>. See <code>docs/production-cron-smtp-notifications.md</code>.</p>
        </div>
    @endif
    <div class="jp-card">
        <div class="jp-card__body">
            <form method="post" action="{{ route('admin.settings.communications.update') }}" class="row g-3">
                @csrf
                @method('PATCH')

                <div class="col-md-6">
                    <h3 class="h5">Email settings</h3>
                    <label class="form-check">
                        <input type="checkbox" class="form-check-input" name="email_enabled" value="1" {{ $settings->email_enabled ? 'checked' : '' }}>
                        <span class="form-check-label">Enable email notifications</span>
                    </label>
                </div>

                <div class="col-md-6">
                    <h3 class="h5">SMTP settings</h3>
                    <label class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" name="smtp_enabled" value="1" {{ $settings->smtp_enabled ? 'checked' : '' }}>
                        <span class="form-check-label">Use custom SMTP</span>
                    </label>
                    <input class="jp-control mb-2" name="smtp_host" placeholder="SMTP Host" value="{{ old('smtp_host', $settings->smtp_host) }}">
                    <input class="jp-control mb-2" name="smtp_port" placeholder="SMTP Port" value="{{ old('smtp_port', $settings->smtp_port) }}">
                    <input class="jp-control mb-2" name="smtp_username" placeholder="SMTP Username" value="{{ old('smtp_username', $settings->smtp_username) }}">
                    <input type="password" class="jp-control" name="smtp_password" placeholder="SMTP Password (leave blank to keep existing)">
                    @if($settings->maskedSmtpPassword())
                        <div class="text-secondary small mt-1">Saved password: {{ $settings->maskedSmtpPassword() }}</div>
                    @endif
                </div>

                <div class="col-md-6">
                    <h3 class="h5">Sender identity</h3>
                    <input class="jp-control mb-2" name="mail_from_name" placeholder="From name" value="{{ old('mail_from_name', $settings->mail_from_name) }}">
                    <input class="jp-control mb-2" name="mail_from_email" placeholder="From email" value="{{ old('mail_from_email', $settings->mail_from_email) }}">
                    <input class="jp-control" name="reply_to_email" placeholder="Reply-to email" value="{{ old('reply_to_email', $settings->reply_to_email) }}">
                </div>

                <div class="col-md-6">
                    <h3 class="h5">Report schedules</h3>
                    <label class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" name="daily_report_enabled" value="1" {{ $settings->daily_report_enabled ? 'checked' : '' }}>
                        <span class="form-check-label">Enable daily report</span>
                    </label>
                    <input class="jp-control mb-2" type="time" name="daily_report_time" value="{{ old('daily_report_time', $settings->daily_report_time ?? '08:00') }}">

                    <label class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" name="weekly_report_enabled" value="1" {{ $settings->weekly_report_enabled ? 'checked' : '' }}>
                        <span class="form-check-label">Enable weekly report</span>
                    </label>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <select class="jp-control" name="weekly_report_day">
                                @foreach(['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day)
                                    <option value="{{ $day }}" {{ old('weekly_report_day', $settings->weekly_report_day ?? 'monday') === $day ? 'selected' : '' }}>{{ ucfirst($day) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <input class="jp-control" type="time" name="weekly_report_time" value="{{ old('weekly_report_time', $settings->weekly_report_time ?? '08:00') }}">
                        </div>
                    </div>

                    <label class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" name="monthly_report_enabled" value="1" {{ $settings->monthly_report_enabled ? 'checked' : '' }}>
                        <span class="form-check-label">Enable monthly report</span>
                    </label>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <input class="jp-control" type="number" min="1" max="28" name="monthly_report_day" placeholder="Day (1-28)" value="{{ old('monthly_report_day', $settings->monthly_report_day ?? 1) }}">
                        </div>
                        <div class="col-6">
                            <input class="jp-control" type="time" name="monthly_report_time" value="{{ old('monthly_report_time', $settings->monthly_report_time ?? '08:00') }}">
                        </div>
                    </div>

                    <label class="form-check">
                        <input type="checkbox" class="form-check-input" name="monthly_ledger_enabled" value="1" {{ $settings->monthly_ledger_enabled ? 'checked' : '' }}>
                        <span class="form-check-label">Enable monthly ledger email</span>
                    </label>
                </div>

                <div class="col-md-6">
                    <h3 class="h5">WhatsApp readiness</h3>
                    <label class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" name="whatsapp_enabled" value="1" {{ $settings->whatsapp_enabled ? 'checked' : '' }}>
                        <span class="form-check-label">Enable WhatsApp rules (no sending yet)</span>
                    </label>
                    <input class="jp-control mb-2" name="whatsapp_provider" placeholder="Provider: meta_cloud_api|twilio|custom" value="{{ old('whatsapp_provider', $settings->whatsapp_provider) }}">
                    <input class="jp-control mb-2" name="whatsapp_phone_number_id" placeholder="Phone Number ID" value="{{ old('whatsapp_phone_number_id', $settings->whatsapp_phone_number_id) }}">
                    <input class="jp-control mb-2" name="whatsapp_business_account_id" placeholder="Business Account ID" value="{{ old('whatsapp_business_account_id', $settings->whatsapp_business_account_id) }}">
                    <input type="password" class="jp-control mb-2" name="whatsapp_access_token" placeholder="Access token (leave blank to keep existing)">
                    @if($settings->maskedWhatsappToken())
                        <div class="text-secondary small">Saved token: {{ $settings->maskedWhatsappToken() }}</div>
                    @endif
                </div>

                <div class="col-12">
                    <button class="jp-btn jp-btn--primary" type="submit">Save settings</button>
                </div>
            </form>
            <hr class="my-4">
            <form method="post" action="{{ route('admin.settings.communications.test-email') }}" class="jp-form-grid jp-form-grid--filter">
                @csrf
                <div class="col-md-8">
                    <label class="jp-label mb-1">Test recipient email</label>
                    <input class="jp-control" type="email" name="recipient_email" placeholder="ops@asiftravels.com" required>
                </div>
                <div class="col-md-4">
                    <button class="jp-btn jp-btn--outline w-100" type="submit">Send test email</button>
                </div>
            </form>
        </div>
    </div>
@endsection

