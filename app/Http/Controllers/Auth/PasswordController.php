<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\SecurityEventLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();
        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        try {
            app(SecurityEventLogger::class)->record(
                eventType: 'security.password_changed',
                outcome: 'success',
                actor: $user,
                agencyId: $user->current_agency_id,
                request: $request,
                metadata: ['forced' => false],
            );
        } catch (\Throwable) {
            // fail-safe
        }

        return back()->with('status', 'password-updated');
    }
}
