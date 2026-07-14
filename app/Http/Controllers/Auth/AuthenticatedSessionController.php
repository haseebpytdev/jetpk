<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountType;
use App\Exceptions\Auth\LoginOtpDeliveryException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\PersistClientPreviewContext;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Auth\LoginOtpService;
use App\Services\Client\ClientRedirectResolver;
use App\Services\Communication\AuthSecurityEmailNotificationService;
use App\Services\Security\SecurityEventLogger;
use App\Support\Auth\ClientLoginOtpGate;
use App\Support\Auth\CheckoutReturnIntent;
use App\Support\Ui\MobileViewPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        protected AuthSecurityEmailNotificationService $authSecurityEmailNotificationService,
        protected MobileViewPreference $mobileViewPreference,
        protected ClientRedirectResolver $clientRedirectResolver,
        protected LoginOtpService $loginOtpService,
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

        return view(client_view('auth.login', 'frontend'));
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $this->primeClientSlugFromRequest($request);

        $user = $request->validateCredentials();

        if (ClientLoginOtpGate::isRequired()) {
            try {
                $this->loginOtpService->initiate($request, $user, $request->boolean('remember'));
            } catch (LoginOtpDeliveryException $e) {
                return back()
                    ->withInput($request->only('login', 'email', 'remember'))
                    ->withErrors(['login' => $e->getMessage()]);
            }

            return $this->clientRedirectResolver
                ->route('login.otp')
                ->with('status', 'We sent a verification code to your email.');
        }

        Auth::login($user, $request->boolean('remember'));

        return $this->completeAuthenticatedLogin($request, $user, $request->boolean('remember'));
    }

    public function completeAuthenticatedLogin(Request $request, User $user, bool $remember): RedirectResponse
    {
        if (! Auth::check()) {
            Auth::login($user, $remember);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        try {
            if ($request instanceof LoginRequest) {
                $this->authSecurityEmailNotificationService->notifyLoginSuccess($request);
            }
        } catch (\Throwable $e) {
            Log::warning('Auth login success notification failed safely.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            if ($request instanceof LoginRequest) {
                $this->authSecurityEmailNotificationService->notifyNewDeviceLogin($request);
            }
        } catch (\Throwable $e) {
            Log::warning('Auth new device login notification failed safely.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        $request->session()->regenerate();

        try {
            app(SecurityEventLogger::class)->record(
                eventType: 'auth.login',
                outcome: 'success',
                actor: $user,
                agencyId: $user->current_agency_id,
                request: $request,
            );
        } catch (\Throwable) {
            // fail-safe
        }

        if ($user->must_change_password ?? false) {
            return $this->clientRedirectResolver->route('password.force');
        }

        if ($user->account_type === AccountType::Customer && ! $user->hasVerifiedEmail()) {
            if ($this->intendedEmailVerificationUrl($request) !== null) {
                return redirect()->intended($this->clientRedirectResolver->pathForRoute('verification.notice'));
            }

            return $this->clientRedirectResolver
                ->route('verification.notice')
                ->with('status', 'Please verify your email address to continue.');
        }

        return $this->clientRedirectResolver->intended(fallbackRouteName: 'dashboard', user: $user);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $previewSlug = $request->session()->get(PersistClientPreviewContext::SESSION_KEY);

        $this->loginOtpService->clear($request);

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->to($this->clientRedirectResolver->afterLogoutPath(
            is_string($previewSlug) ? $previewSlug : null,
        ));
    }

    private function primeClientSlugFromRequest(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $slug = trim((string) $request->input('client_slug', ''));
        if ($slug === '') {
            return;
        }

        $request->session()->put(PersistClientPreviewContext::SESSION_KEY, $slug);
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
