<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountType;
use App\Enums\OtaNotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Communication\OtaNotificationService;
use App\Support\Auth\CheckoutReturnIntent;
use App\Support\Auth\LoginDestination;
use App\Support\Emails\OperationalEmailDefaults;
use App\Support\Ui\MobileViewPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        protected OtaNotificationService $notificationService,
        protected MobileViewPreference $mobileViewPreference,
    ) {}

    /**
     * Display the login view.
     */
    public function create(Request $request): View
    {
        CheckoutReturnIntent::primeSessionFromQuery($request);

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.auth.login');
        }

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->user()?->forceFill(['last_login_at' => now()])->save();
        $this->notifyLogin($request);

        $request->session()->regenerate();

        $user = $request->user();
        if ($user !== null && $user->account_type === AccountType::Customer && ! $user->hasVerifiedEmail()) {
            if ($this->intendedEmailVerificationUrl($request) !== null) {
                return redirect()->intended(route('verification.notice', absolute: false));
            }

            return redirect()
                ->route('verification.notice')
                ->with('status', 'Please verify your email address to continue.');
        }

        return redirect()->intended(LoginDestination::path($user));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function notifyLogin(LoginRequest $request): void
    {
        $user = $request->user();
        if ($user === null || $user->currentAgency === null) {
            return;
        }

        if ($user->account_type === AccountType::Customer) {
            return;
        }

        $event = match ($user->account_type) {
            AccountType::AgencyAdmin, AccountType::PlatformAdmin => OtaNotificationEvent::AdminLoginSuccess,
            AccountType::Staff => OtaNotificationEvent::StaffLoginSuccess,
            AccountType::Agent => OtaNotificationEvent::AgentLoginSuccess,
            default => null,
        };

        if ($event === null || ! $this->loginNotificationEnabled($event)) {
            return;
        }

        $defaults = OperationalEmailDefaults::forEvent($event->value);
        $templateVariables = OperationalEmailDefaults::authVariablesFromUser(
            $user->currentAgency,
            $user,
            $request,
        );

        $this->notificationService->send(
            agency: $user->currentAgency,
            eventKey: $event->value,
            actor: $user,
            payload: [
                'account_type' => $user->account_type?->value,
                'timestamp' => now()->toIso8601String(),
                'ip' => (string) $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 250),
            ],
            fallbackSubject: $defaults['subject'] ?? 'Security login notice',
            fallbackBody: $defaults['body'] ?? 'A privileged user login was detected for '.$user->email.'.',
            templateVariables: $templateVariables,
        );
    }

    private function loginNotificationEnabled(OtaNotificationEvent $event): bool
    {
        return match ($event) {
            OtaNotificationEvent::AdminLoginSuccess => (bool) config('ota.notify_admin_login'),
            OtaNotificationEvent::StaffLoginSuccess => (bool) config('ota.notify_staff_login'),
            OtaNotificationEvent::AgentLoginSuccess => (bool) config('ota.notify_agent_login'),
            default => false,
        };
    }

    private function intendedEmailVerificationUrl(Request $request): ?string
    {
        $intended = $request->session()->get('url.intended');
        if (! is_string($intended) || $intended === '') {
            return null;
        }

        $path = parse_url($intended, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }

        return preg_match('#^/verify-email/[^/]+/[^/]+$#', $path) === 1
            ? $intended
            : null;
    }
}
