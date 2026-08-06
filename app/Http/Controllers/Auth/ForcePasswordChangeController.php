<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\SecurityEventLogger;
use App\Support\Auth\AuthPostLoginRedirectResolver;
use App\Support\Auth\PublicAuthRedirectAllowlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forced password change after bootstrap or admin reset (must_change_password=true).
 */
class ForcePasswordChangeController extends Controller
{
    public function show(Request $request): View|RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            if ($this->wantsAuthJson($request)) {
                return response()->json([
                    'ok' => false,
                    'authenticated' => false,
                    'message' => 'Unauthenticated.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            return redirect()->route('login');
        }

        if (! ($user->must_change_password ?? false)) {
            $redirect = $this->resolvePostChangeRedirect($user, $request);

            if ($this->wantsAuthJson($request)) {
                return response()->json([
                    'ok' => true,
                    'requires_password_change' => false,
                    'redirect' => $redirect,
                ]);
            }

            return redirect()->to($redirect);
        }

        if ($this->wantsAuthJson($request)) {
            return response()->json([
                'ok' => true,
                'requires_password_change' => true,
                'message' => 'For security, set a new password before continuing to your account.',
            ]);
        }

        return view(client_view('auth.force-password-change', 'frontend'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            if ($this->wantsAuthJson($request)) {
                return response()->json([
                    'ok' => false,
                    'authenticated' => false,
                    'message' => 'Unauthenticated.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            abort(403);
        }

        if (! ($user->must_change_password ?? false)) {
            if ($this->wantsAuthJson($request)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Password change is not required.',
                ], Response::HTTP_FORBIDDEN);
            }

            return redirect()->to($this->resolvePostChangeRedirect($user, $request));
        }

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

        $redirect = $this->resolvePostChangeRedirect($user->fresh(), $request);

        if ($this->wantsAuthJson($request)) {
            return response()->json([
                'ok' => true,
                'message' => 'Password updated. You can now access your account.',
                'redirect' => $redirect,
            ]);
        }

        return redirect()->to($redirect)->with('status', 'Password updated. You can now access your account.');
    }

    private function resolvePostChangeRedirect(\App\Models\User $user, Request $request): string
    {
        return PublicAuthRedirectAllowlist::sanitize(
            app(AuthPostLoginRedirectResolver::class)->resolvePath($user, $request),
            '/',
        );
    }

    private function wantsAuthJson(Request $request): bool
    {
        return $request->wantsJson() || $request->query('format') === 'json';
    }
}
