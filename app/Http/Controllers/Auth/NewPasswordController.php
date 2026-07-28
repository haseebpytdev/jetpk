<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Client\ClientRedirectResolver;
use App\Services\Security\SecurityEventLogger;
use App\Support\Auth\PublicAuthRedirectAllowlist;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function __construct(
        protected ClientRedirectResolver $clientRedirectResolver,
    ) {}

    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        $viewData = ['request' => $request];

        return view(client_view('auth.reset-password', 'frontend'), $viewData);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
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
                        metadata: ['context' => 'password_reset'],
                    );
                } catch (\Throwable) {
                    // fail-safe
                }

                event(new PasswordReset($user));
            }
        );

        $loginPath = PublicAuthRedirectAllowlist::sanitize(
            $this->clientRedirectResolver->pathForRoute('login'),
            '/login',
        );

        if ($request->expectsJson()) {
            if ($status == Password::PASSWORD_RESET) {
                return response()->json([
                    'ok' => true,
                    'redirect' => $loginPath,
                    'message' => __($status),
                ]);
            }

            return response()->json([
                'message' => __($status),
                'errors' => ['email' => [__($status)]],
            ], 422);
        }

        return $status == Password::PASSWORD_RESET
                    ? $this->clientRedirectResolver->route('login')->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
