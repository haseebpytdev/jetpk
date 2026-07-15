<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Services\Communication\AgencyCommunicationSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgencyCommunicationSettingsController extends Controller
{
    public function __construct(
        protected AgencyCommunicationSettingsService $settingsService,
    ) {}

    public function index(Request $request): View
    {
        $agency = Agency::query()->findOrFail($request->user()->current_agency_id);
        $settings = $this->settingsService->getOrCreateSettings($agency);
        Gate::authorize('view', $settings);

        return view(client_view('settings.communications.index', 'admin'), compact('agency', 'settings'));
    }

    public function update(Request $request): RedirectResponse
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

        $this->settingsService->updateSettings($agency, $request->user(), $validated);

        return back()->with('status', 'communication-settings-updated');
    }

    public function testEmail(Request $request): RedirectResponse
    {
        $agency = Agency::query()->findOrFail($request->user()->current_agency_id);
        $settings = $this->settingsService->getOrCreateSettings($agency);
        Gate::authorize('update', $settings);

        $validated = $request->validate(['recipient_email' => ['required', 'email']]);
        $this->settingsService->testEmailSettings($agency, $request->user(), $validated['recipient_email']);

        return back()->with('status', 'communication-test-email-sent');
    }

    public function testWhatsapp(Request $request): RedirectResponse
    {
        $agency = Agency::query()->findOrFail($request->user()->current_agency_id);
        $settings = $this->settingsService->getOrCreateSettings($agency);
        Gate::authorize('update', $settings);

        $result = $this->settingsService->testWhatsappReadiness($agency, $request->user());

        return back()->with('status', 'communication-test-whatsapp')->with('whatsapp_readiness', $result);
    }
}
