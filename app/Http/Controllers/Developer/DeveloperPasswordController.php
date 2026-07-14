<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\DeveloperUser;
use App\Services\Security\SecurityEventLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Developer CP forced password change.
 */
class DeveloperPasswordController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $developer = $this->resolveDeveloper($request);
        if ($developer === null) {
            return redirect()->route('dev.cp.login');
        }

        if (! ($developer->must_change_password ?? false)) {
            return redirect()->route('dev.cp.index');
        }

        return view('developer.auth.change-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $developer = $this->resolveDeveloper($request);
        abort_if($developer === null, 403);

        $validated = $request->validate([
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $developer->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        try {
            app(SecurityEventLogger::class)->record(
                eventType: 'security.password_changed',
                outcome: 'success',
                actor: $developer,
                request: $request,
                metadata: ['context' => 'developer_cp', 'forced' => true],
            );
        } catch (\Throwable) {
            // fail-safe
        }

        return redirect()
            ->route('dev.cp.index')
            ->with('status', 'Password updated.');
    }

    private function resolveDeveloper(Request $request): ?DeveloperUser
    {
        $userId = $request->session()->get('dev_cp_user_id');
        if ($userId === null) {
            return null;
        }

        $developer = DeveloperUser::query()->find($userId);

        return ($developer !== null && $developer->is_active) ? $developer : null;
    }
}
