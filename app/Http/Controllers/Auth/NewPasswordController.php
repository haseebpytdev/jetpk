<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Client\ClientRedirectResolver;
use App\Services\Security\SecurityEventLogger;
use App\Support\Ui\MobileViewPreference;
use Illuminate\Auth\Events\PasswordReset;
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
        protected MobileViewPreference $mobileViewPreference,
        protected ClientRedirectResolver $clientRedirectResolver,
    ) {}

    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        $viewData = ['request' => $request];

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.auth.reset-password', $viewData);
        }

        return view(client_view('auth.reset-password', 'frontend'), $viewData);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
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

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        return $status == Password::PASSWORD_RESET
                    ? $this->clientRedirectResolver->route('login')->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
