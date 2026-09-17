<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auth\LoginOtpSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin authority for the global login email-OTP gate (all roles).
 */
class LoginOtpSettingsController extends Controller
{
    public function __construct(
        private readonly LoginOtpSettingsService $settings,
    ) {}

    public function edit(): View
    {
        Gate::authorize('platform.admin');

        return view(client_view('settings.login-otp', 'admin'), [
            'snapshot' => $this->settings->snapshot(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('platform.admin');

        $validated = $request->validate([
            'require_login_otp' => ['required'],
        ]);

        $this->settings->setRequired($request->boolean('require_login_otp'));

        return redirect()
            ->route('admin.settings.login-otp.edit')
            ->with('status', 'Login OTP setting saved. Runtime gate updated.');
    }
}
