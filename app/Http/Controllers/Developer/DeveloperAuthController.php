<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\DeveloperUser;
use App\Services\Security\SecurityEventLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Dedicated login/logout for /dev/cp (not OTA web guard).
 */
class DeveloperAuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if (! config('ota-developer.enabled')) {
            abort(404);
        }

        $userId = $request->session()->get('dev_cp_user_id');
        if ($userId !== null) {
            $developer = DeveloperUser::query()->find($userId);
            if ($developer !== null && $developer->is_active) {
                return redirect()->route('dev.cp.index');
            }

            $request->session()->forget('dev_cp_user_id');
        }

        return view('developer.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        if (! config('ota-developer.enabled')) {
            abort(404);
        }

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower(trim($credentials['email']));
        $developer = DeveloperUser::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (
            $developer === null
            || ! $developer->is_active
            || ! Hash::check($credentials['password'], $developer->password)
        ) {
            try {
                app(SecurityEventLogger::class)->record(
                    eventType: 'devcp.login',
                    outcome: 'failure',
                    request: $request,
                    metadata: ['email' => $email],
                );
            } catch (\Throwable) {
                // fail-safe
            }

            throw ValidationException::withMessages([
                'email' => ['Invalid developer credentials.'],
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('dev_cp_user_id', $developer->id);

        $developer->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        try {
            app(SecurityEventLogger::class)->record(
                eventType: 'devcp.login',
                outcome: 'success',
                actor: $developer,
                request: $request,
            );
        } catch (\Throwable) {
            // fail-safe
        }

        if ($developer->must_change_password ?? false) {
            return redirect()->route('dev.cp.password');
        }

        return redirect()->intended(route('dev.cp.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('dev_cp_user_id');
        if ($userId !== null) {
            $developer = DeveloperUser::query()->find($userId);
            if ($developer !== null) {
                try {
                    app(SecurityEventLogger::class)->record(
                        eventType: 'devcp.logout',
                        outcome: 'success',
                        actor: $developer,
                        request: $request,
                    );
                } catch (\Throwable) {
                    // fail-safe
                }
            }
        }

        $request->session()->forget('dev_cp_user_id');
        $request->session()->regenerateToken();

        return redirect()->route('dev.cp.login');
    }
}
