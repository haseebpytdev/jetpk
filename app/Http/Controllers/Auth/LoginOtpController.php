<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\Auth\LoginOtpDeliveryException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\PersistClientPreviewContext;
use App\Services\Auth\LoginOtpService;
use App\Services\Client\ClientRedirectResolver;
use App\Support\Auth\PublicAuthRedirectAllowlist;
use App\Support\Auth\PublicSessionBootstrapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class LoginOtpController extends Controller
{
    public function __construct(
        protected LoginOtpService $loginOtpService,
        protected ClientRedirectResolver $clientRedirectResolver,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->loginOtpService->hasPending($request)) {
            return $this->clientRedirectResolver->route('login');
        }

        return view(client_view('auth.login-otp', 'frontend'), [
            'maskedEmail' => $this->loginOtpService->maskedEmail($request),
            'resendAvailableIn' => $this->loginOtpService->resendAvailableIn($request),
        ]);
    }

    public function store(
        Request $request,
        AuthenticatedSessionController $sessionController,
        PublicSessionBootstrapService $sessionBootstrap,
    ): RedirectResponse|JsonResponse {
        $this->primeClientSlugFromRequest($request);

        try {
            $validated = $request->validate([
                'otp' => ['required', 'string', 'regex:/^\d{6}$/'],
            ]);

            $result = $this->loginOtpService->verify($request, $validated['otp']);

            $redirect = $sessionController->completeAuthenticatedLogin(
                request: $request,
                user: $result['user'],
                remember: $result['remember'],
            );

            if ($request->expectsJson()) {
                $bootstrap = $sessionBootstrap->forAuthenticatedUser($result['user'], $request);
                $redirectPath = PublicAuthRedirectAllowlist::sanitize(
                    $redirect->getTargetUrl(),
                    (string) ($bootstrap['dashboard_url'] ?? '/'),
                );

                return response()->json([
                    'ok' => true,
                    'redirect' => $redirectPath,
                    'user' => $bootstrap['user'] ?? null,
                    'dashboard_url' => $bootstrap['dashboard_url'] ?? $redirectPath,
                ]);
            }

            return $redirect;
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            throw $e;
        }
    }

    public function resend(Request $request): RedirectResponse|JsonResponse
    {
        $this->primeClientSlugFromRequest($request);

        try {
            $this->loginOtpService->resend($request);
        } catch (LoginOtpDeliveryException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['otp' => [$e->getMessage()]],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return back()->withErrors(['otp' => $e->getMessage()]);
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            throw $e;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'resend_available_in' => $this->loginOtpService->resendAvailableIn($request),
                'message' => 'A new verification code has been sent.',
            ]);
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
