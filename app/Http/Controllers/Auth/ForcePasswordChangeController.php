<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\SecurityEventLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Forced password change after bootstrap or admin reset (must_change_password=true).
 */
class ForcePasswordChangeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        if (! ($user->must_change_password ?? false)) {
            return redirect()->intended('/');
        }

        return view(client_view('auth.force-password-change', 'frontend'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validate([
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        try {
            app(SecurityEventLogger::class)->record(
                eventType: 'security.password_changed',
                outcome: 'success',
                actor: $user,
                agencyId: $user->current_agency_id,
                request: $request,
                metadata: ['forced' => true],
            );
        } catch (\Throwable) {
            // fail-safe
        }

        return redirect()->intended('/')->with('status', 'Password updated. You can now access your account.');
    }
}
