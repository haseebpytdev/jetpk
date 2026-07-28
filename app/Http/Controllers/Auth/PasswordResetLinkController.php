<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Auth\PublicAuthRedirectAllowlist;
use App\Services\Security\SecurityEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(Request $request): View
    {
        return view(client_view('auth.forgot-password', 'frontend'));
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status == Password::RESET_LINK_SENT) {
            try {
                app(SecurityEventLogger::class)->record(
                    eventType: 'auth.password_reset_requested',
                    outcome: 'success',
                    request: $request,
                    metadata: ['email' => strtolower((string) $request->input('email'))],
                );
            } catch (\Throwable) {
                // fail-safe
            }
        }

        $genericMessage = 'If an account exists for that email address, we have emailed password reset instructions.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $genericMessage,
            ]);
        }

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
