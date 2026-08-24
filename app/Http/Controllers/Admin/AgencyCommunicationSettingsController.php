<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Services\Communication\AgencyCommunicationSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgencyCommunicationSettingsController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function __construct(
        protected AgencyCommunicationSettingsService $settingsService,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $agency = Agency::query()->findOrFail($request->user()->current_agency_id);
        $settings = $this->settingsService->getOrCreateSettings($agency);
        Gate::authorize('view', $settings);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'settings' => $this->presentSettings($settings),
            ]);
        }

        return view(client_view('settings.communications.index', 'admin'), compact('agency', 'settings'));
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $agency = Agency::query()->findOrFail($request->user()->current_agency_id);
        $settings = $this->settingsService->getOrCreateSettings($agency);
        Gate::authorize('update', $settings);

        $validated = $request->validate([
            'email_enabled' => ['nullable', 'boolean'],
            'smtp_enabled' => ['nullable', 'boolean'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', 'in:tls,ssl,none'],
            'daily_report_enabled' => ['nullable', 'boolean'],
            'daily_report_time' => ['nullable', 'date_format:H:i'],
            'weekly_report_enabled' => ['nullable', 'boolean'],
            'weekly_report_day' => ['nullable', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'weekly_report_time' => ['nullable', 'date_format:H:i'],
            'monthly_report_enabled' => ['nullable', 'boolean'],
            'monthly_report_day' => ['nullable', 'integer', 'between:1,28'],
            'monthly_report_time' => ['nullable', 'date_format:H:i'],
            'monthly_ledger_enabled' => ['nullable', 'boolean'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'mail_from_email' => ['nullable', 'email', 'max:255'],
            'reply_to_email' => ['nullable', 'email', 'max:255'],
            'whatsapp_enabled' => ['nullable', 'boolean'],
            'whatsapp_provider' => ['nullable', 'in:meta_cloud_api,twilio,custom'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:255'],
            'whatsapp_business_account_id' => ['nullable', 'string', 'max:255'],
            'whatsapp_access_token' => ['nullable', 'string', 'max:1000'],
            'whatsapp_webhook_verify_token' => ['nullable', 'string', 'max:1000'],
            'whatsapp_default_country_code' => ['nullable', 'string', 'max:10'],
            'whatsapp_settings' => ['nullable', 'array'],
            'notification_rules' => ['nullable', 'array'],
        ]);

        $validated['email_enabled'] = $request->boolean('email_enabled');
        $validated['smtp_enabled'] = $request->boolean('smtp_enabled');
        $validated['daily_report_enabled'] = $request->boolean('daily_report_enabled');
        $validated['weekly_report_enabled'] = $request->boolean('weekly_report_enabled');
        $validated['monthly_report_enabled'] = $request->boolean('monthly_report_enabled');
        $validated['monthly_ledger_enabled'] = $request->boolean('monthly_ledger_enabled');
        $validated['whatsapp_enabled'] = $request->boolean('whatsapp_enabled');

        if (blank($validated['smtp_password'] ?? null)) {
            unset($validated['smtp_password']);
        }
        if (blank($validated['whatsapp_access_token'] ?? null)) {
            unset($validated['whatsapp_access_token']);
        }
        if (blank($validated['whatsapp_webhook_verify_token'] ?? null)) {
            unset($validated['whatsapp_webhook_verify_token']);
        }

        $settings = $this->settingsService->updateSettings($agency, $request->user(), $validated);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Communication settings updated.',
                'settings' => $this->presentSettings($settings->fresh() ?? $settings),
            ]);
        }

        return back()->with('status', 'communication-settings-updated');
    }

    public function testEmail(Request $request): RedirectResponse|JsonResponse
    {
        $agency = Agency::query()->findOrFail($request->user()->current_agency_id);
        $settings = $this->settingsService->getOrCreateSettings($agency);
        Gate::authorize('update', $settings);

        $rules = ['recipient_email' => ['required', 'email']];
        if ($this->wantsBackOfficeJson($request)) {
            $rules['confirmation'] = ['accepted'];
        }

        $validated = $request->validate($rules);

        $log = $this->settingsService->testEmailSettings($agency, $request->user(), $validated['recipient_email']);

        if ($this->wantsBackOfficeJson($request)) {
            $sent = $log->status === 'sent';

            return $this->backOfficeJson([
                'ok' => $sent,
                'message' => $sent
                    ? 'Test email sent to the confirmed recipient only (no customer broadcast).'
                    : 'Test email failed. Secrets are redacted from the error detail.',
                'status' => $log->status,
                'error_message' => $log->error_message,
                'recipient_email' => $validated['recipient_email'],
            ], $sent ? 200 : 422);
        }

        return back()->with('status', 'communication-test-email-sent');
    }

    public function testWhatsapp(Request $request): RedirectResponse|JsonResponse
    {
        $agency = Agency::query()->findOrFail($request->user()->current_agency_id);
        $settings = $this->settingsService->getOrCreateSettings($agency);
        Gate::authorize('update', $settings);

        $result = $this->settingsService->testWhatsappReadiness($agency, $request->user());

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'WhatsApp readiness checked (no outbound customer message).',
                'whatsapp_readiness' => $result,
            ]);
        }

        return back()->with('status', 'communication-test-whatsapp')->with('whatsapp_readiness', $result);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSettings(AgencyCommunicationSetting $settings): array
    {
        return [
            'email_enabled' => (bool) $settings->email_enabled,
            'smtp_enabled' => (bool) $settings->smtp_enabled,
            'smtp_host' => $settings->smtp_host,
            'smtp_port' => $settings->smtp_port,
            'smtp_username' => $settings->smtp_username,
            'smtp_password_set' => filled($settings->smtp_password),
            'smtp_password_masked' => $settings->maskedSmtpPassword(),
            'smtp_encryption' => $settings->smtp_encryption ?: 'tls',
            'mail_from_name' => $settings->mail_from_name,
            'mail_from_email' => $settings->mail_from_email,
            'reply_to_email' => $settings->reply_to_email,
            'daily_report_enabled' => (bool) $settings->daily_report_enabled,
            'daily_report_time' => $settings->daily_report_time,
            'weekly_report_enabled' => (bool) $settings->weekly_report_enabled,
            'weekly_report_day' => $settings->weekly_report_day,
            'weekly_report_time' => $settings->weekly_report_time,
            'monthly_report_enabled' => (bool) $settings->monthly_report_enabled,
            'monthly_report_day' => $settings->monthly_report_day,
            'monthly_report_time' => $settings->monthly_report_time,
            'monthly_ledger_enabled' => (bool) $settings->monthly_ledger_enabled,
            'whatsapp_enabled' => (bool) $settings->whatsapp_enabled,
            'whatsapp_provider' => $settings->whatsapp_provider,
            'whatsapp_phone_number_id' => $settings->whatsapp_phone_number_id,
            'whatsapp_business_account_id' => $settings->whatsapp_business_account_id,
            'whatsapp_access_token_set' => filled($settings->whatsapp_access_token),
            'whatsapp_access_token_masked' => $settings->maskedWhatsappToken(),
            'whatsapp_webhook_verify_token_set' => filled($settings->whatsapp_webhook_verify_token),
            'whatsapp_default_country_code' => $settings->whatsapp_default_country_code,
        ];
    }
}
