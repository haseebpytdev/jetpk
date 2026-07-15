<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\Auth\LoginOtpDeliveryException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\PersistClientPreviewContext;
use App\Services\Auth\LoginOtpService;
use App\Services\Client\ClientRedirectResolver;
use App\Support\Ui\MobileViewPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginOtpController extends Controller
{
    public function __construct(
        protected LoginOtpService $loginOtpService,
        protected ClientRedirectResolver $clientRedirectResolver,
        protected MobileViewPreference $mobileViewPreference,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->loginOtpService->hasPending($request)) {
            return $this->clientRedirectResolver->route('login');
        }

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.auth.login-otp', [
                'maskedEmail' => $this->loginOtpService->maskedEmail($request),
                'resendAvailableIn' => $this->loginOtpService->resendAvailableIn($request),
            ]);
        }

        return view(client_view('auth.login-otp', 'frontend'), [
            'maskedEmail' => $this->loginOtpService->maskedEmail($request),
            'resendAvailableIn' => $this->loginOtpService->resendAvailableIn($request),
        ]);
    }

    public function store(Request $request, AuthenticatedSessionController $sessionController): RedirectResponse
    {
        $this->primeClientSlugFromRequest($request);

        $validated = $request->validate([
            'otp' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $result = $this->loginOtpService->verify($request, $validated['otp']);

        return $sessionController->completeAuthenticatedLogin(
            request: $request,
            user: $result['user'],
            remember: $result['remember'],
        );
    }

    public function resend(Request $request): RedirectResponse
    {
        $this->primeClientSlugFromRequest($request);

        try {
            $this->loginOtpService->resend($request);
        } catch (LoginOtpDeliveryException $e) {
            return back()->withErrors(['otp' => $e->getMessage()]);
        }

        return back()->with('status', 'A new verification code has been sent.');
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
}
