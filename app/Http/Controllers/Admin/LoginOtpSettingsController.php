<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Services\Auth\LoginOtpSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin authority for the global login email-OTP gate (all roles).
 */
class LoginOtpSettingsController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function __construct(
        private readonly LoginOtpSettingsService $settings,
    ) {}

    public function edit(Request $request): View|JsonResponse
    {
        Gate::authorize('platform.admin');

        $snapshot = $this->settings->snapshot();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'snapshot' => $snapshot,
            ]);
        }

        return view(client_view('settings.login-otp', 'admin'), [
            'snapshot' => $snapshot,
        ]);
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('platform.admin');

        $request->validate([
            'require_login_otp' => ['required'],
        ]);

        $this->settings->setRequired($request->boolean('require_login_otp'));
        $snapshot = $this->settings->snapshot();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Login OTP setting saved. Runtime gate updated.',
                'snapshot' => $snapshot,
            ]);
        }

        return redirect()
            ->route('admin.settings.login-otp.edit')
            ->with('status', 'Login OTP setting saved. Runtime gate updated.');
    }
}
