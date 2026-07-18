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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        protected AuthSecurityEmailNotificationService $authSecurityEmailNotificationService,
        protected ClientRedirectResolver $clientRedirectResolver,
        protected LoginOtpService $loginOtpService,
    ) {}

    /**
     * Display the login view.
     */
    public function create(Request $request): View
    {
        CheckoutReturnIntent::primeSessionFromQuery($request);

        return view(client_view('auth.login', 'frontend'));
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse|JsonResponse
    {
        $this->primeClientSlugFromRequest($request);

        $user = $request->validateCredentials();

        if (ClientLoginOtpGate::isRequired()) {
            try {
                $this->loginOtpService->initiate($request, $user, $request->boolean('remember'));
            } catch (LoginOtpDeliveryException $e) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $e->getMessage(),
                        'errors' => ['login' => [$e->getMessage()]],
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                return back()
                    ->withInput($request->only('login', 'email', 'remember'))
                    ->withErrors(['login' => $e->getMessage()]);
            }

            $otpRedirect = $this->clientRedirectResolver->pathForRoute('login.otp');

            if ($request->expectsJson()) {
                return $this->jsonLoginSuccess($otpRedirect, requiresOtp: true);
            }

            return $this->clientRedirectResolver
                ->route('login.otp')
                ->with('status', 'We sent a verification code to your email.');
        }

        Auth::login($user, $request->boolean('remember'));

        $redirect = $this->completeAuthenticatedLogin($request, $user, $request->boolean('remember'));

        if ($request->expectsJson()) {
            return $this->jsonLoginSuccess(
                $this->safeLoginRedirectPath($redirect->getTargetUrl()),
            );
        }

        return $redirect;
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

    private function jsonLoginSuccess(string $redirectPath, bool $requiresOtp = false): JsonResponse
    {
        $payload = [
            'ok' => true,
            'redirect' => $this->safeLoginRedirectPath($redirectPath),
        ];

        if ($requiresOtp) {
            $payload['requires_otp'] = true;
        }

        return response()->json($payload);
    }

    private function safeLoginRedirectPath(string $url): string
    {
        $fallback = $this->clientRedirectResolver->pathForRoute('dashboard');
        $trimmed = trim($url);

        if ($trimmed === '') {
            return $fallback;
        }

        if (str_starts_with($trimmed, '/')) {
            return $trimmed;
        }

        $appRoot = rtrim((string) config('app.url', ''), '/');
        if ($appRoot !== '' && str_starts_with($trimmed, $appRoot)) {
            $path = substr($trimmed, strlen($appRoot));
            if (is_string($path) && $path !== '' && str_starts_with($path, '/')) {
                return $path;
            }

            if ($path === '') {
                return '/';
            }
        }

        return $fallback;
    }
}
