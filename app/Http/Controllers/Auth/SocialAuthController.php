<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Exceptions\Auth\LoginOtpDeliveryException;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Auth\LoginOtpService;
use App\Services\Client\ClientRedirectResolver;
use App\Support\Auth\CheckoutReturnIntent;
use App\Support\Auth\ClientLoginOtpGate;
use App\Support\Auth\GoogleOnboarding;
use App\Support\Auth\SocialOAuthClientContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * OAuth redirect/callback and profile linking (Google primary; Facebook dormant).
 * New Google customers are sent through {@see GoogleOnboardingController} before dashboard/checkout.
 */
class SocialAuthController extends Controller
{
    private const SUPPORTED_PROVIDERS = ['google', 'facebook'];

    private const PRIVILEGED_SOCIAL_LOGIN_MESSAGE = 'For admin, staff, or agent accounts, please log in with your password first and link Google from your profile.';

    private ?string $resolveFailureMessage = null;

    private bool $resolvedWasNewCustomer = false;

    public function __construct(
        private readonly ClientRedirectResolver $clientRedirectResolver,
        private readonly LoginOtpService $loginOtpService,
    ) {}

    public function redirect(string $provider): RedirectResponse
    {
        $this->assertSupportedProvider($provider);

        if (! $this->providerIsConfigured($provider)) {
            return $this->clientRedirectResolver->route('login')->withErrors([
                'social' => ucfirst($provider).' sign in is not available right now. Please use email and password.',
            ]);
        }

        CheckoutReturnIntent::primeSessionFromQuery(request());
        SocialOAuthClientContext::captureForRedirect(request());

        return $this->socialiteRedirect($provider);
    }

    public function linkRedirect(string $provider): RedirectResponse
    {
        $this->assertSupportedProvider($provider);

        if (! $this->providerIsConfigured($provider)) {
            return redirect()->route('profile.edit')->withErrors([
                'social' => ucfirst($provider).' linking is not available right now.',
            ]);
        }

        $user = Auth::user();
        if ($user === null) {
            return $this->clientRedirectResolver->route('login');
        }

        if ($user->socialAccounts()->where('provider', $provider)->exists()) {
            return redirect()->route('profile.edit')->with('status', 'social-already-linked');
        }

        request()->session()->put('social.link_intent', $provider);
        SocialOAuthClientContext::captureForRedirect(request());

        return $this->socialiteRedirect($provider);
    }

    public function callback(string $provider): RedirectResponse
    {
        $this->assertSupportedProvider($provider);

        if (! $this->providerIsConfigured($provider)) {
            return $this->clientRedirectResolver->route('login')->withErrors([
                'social' => ucfirst($provider).' sign in is not available right now. Please use email and password.',
            ]);
        }

        try {
            $oauthUser = $this->socialiteDriver($provider)->user();
        } catch (\Throwable) {
            return $this->clientRedirectResolver->route('login')->withErrors([
                'social' => 'Unable to sign in with '.ucfirst($provider).'. Please try again.',
            ]);
        }

        SocialOAuthClientContext::restoreAfterCallback(request());

        $linkProvider = request()->session()->pull('social.link_intent');
        if ($linkProvider === $provider && Auth::check()) {
            return $this->handleLinkCallback($provider, $oauthUser, Auth::user());
        }

        $this->resolvedWasNewCustomer = false;
        $user = $this->resolveLocalUser($provider, $oauthUser);

        if ($user === null) {
            return $this->clientRedirectResolver->route('login')->withErrors([
                'social' => $this->resolveFailureMessage
                    ?? 'Social sign in is available for customer accounts only. Please use your standard login method.',
            ]);
        }

        if (ClientLoginOtpGate::isRequired()) {
            $this->primeGoogleOnboardingIfNeeded($provider, $user);

            try {
                $this->loginOtpService->initiate(request(), $user, remember: true);
            } catch (LoginOtpDeliveryException $e) {
                return $this->clientRedirectResolver->route('login')->withErrors([
                    'social' => $e->getMessage(),
                ]);
            }

            return $this->clientRedirectResolver
                ->route('login.otp')
                ->with('status', 'We sent a verification code to your email.');
        }

        return $this->completeSocialLogin($provider, $user);
    }

    private function completeSocialLogin(string $provider, User $user): RedirectResponse
    {
        Auth::login($user, remember: true);
        request()->session()->regenerate();

        if ($provider === 'google' && GoogleOnboarding::requiresOnboarding($user, $this->resolvedWasNewCustomer)) {
            GoogleOnboarding::markSessionRequired(request(), $this->resolvedWasNewCustomer);

            return redirect()->route('auth.google.complete-profile');
        }

        return $this->clientRedirectResolver->intended(user: $user);
    }

    private function primeGoogleOnboardingIfNeeded(string $provider, User $user): void
    {
        if ($provider === 'google' && GoogleOnboarding::requiresOnboarding($user, $this->resolvedWasNewCustomer)) {
            GoogleOnboarding::markSessionRequired(request(), $this->resolvedWasNewCustomer);
        }
    }

    private function socialiteRedirect(string $provider): RedirectResponse
    {
        return $this->socialiteDriver($provider)->redirect();
    }

    private function socialiteDriver(string $provider): SocialiteProvider
    {
        $driver = Socialite::driver($provider);
        $redirectUri = config('services.'.$provider.'.redirect');

        if (filled($redirectUri)) {
            $driver = $driver->redirectUrl((string) $redirectUri);
        }

        return $driver;
    }

    public static function providerIsConfigured(string $provider): bool
    {
        if (! in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            return false;
        }

        $config = config('services.'.$provider, []);

        return filled($config['client_id'] ?? null)
            && filled($config['client_secret'] ?? null)
            && filled($config['redirect'] ?? null);
    }

    private function assertSupportedProvider(string $provider): void
    {
        if (! in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            abort(404);
        }
    }

    private function handleLinkCallback(string $provider, SocialiteUser $oauthUser, User $user): RedirectResponse
    {
        $providerId = trim((string) $oauthUser->getId());
        if ($providerId === '') {
            return redirect()->route('profile.edit')->withErrors([
                'social' => 'Unable to link '.ucfirst($provider).'. Please try again.',
            ]);
        }

        if (! $this->userCanSocialLogin($user)) {
            return redirect()->route('profile.edit')->withErrors([
                'social' => $this->resolveFailureMessage ?? 'This account is not available for sign in.',
            ]);
        }

        $existingAccount = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($existingAccount !== null && $existingAccount->user_id !== $user->id) {
            return redirect()->route('profile.edit')->withErrors([
                'social' => 'This '.ucfirst($provider).' account is already linked to another user.',
            ]);
        }

        if ($user->socialAccounts()->where('provider', $provider)->exists()) {
            return redirect()->route('profile.edit')->with('status', 'social-already-linked');
        }

        $email = strtolower(trim((string) $oauthUser->getEmail()));
        $name = trim((string) ($oauthUser->getName() ?: $oauthUser->getNickname() ?: ''));

        $this->createSocialAccount($user, $provider, $providerId, $oauthUser, $email, $name);

        return redirect()->route('profile.edit')->with('status', 'social-linked');
    }

    private function resolveLocalUser(string $provider, SocialiteUser $oauthUser): ?User
    {
        $this->resolveFailureMessage = null;
        $this->resolvedWasNewCustomer = false;

        $providerId = trim((string) $oauthUser->getId());
        if ($providerId === '') {
            return null;
        }

        $email = strtolower(trim((string) $oauthUser->getEmail()));
        $name = trim((string) ($oauthUser->getName() ?: $oauthUser->getNickname() ?: ''));

        $existingAccount = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($existingAccount !== null) {
            $user = $existingAccount->user;
            if (! $this->userCanSocialLogin($user)) {
                return null;
            }

            $avatar = $oauthUser->getAvatar();
            if ($avatar !== null && $avatar !== $existingAccount->avatar) {
                $existingAccount->forceFill(['avatar' => $avatar])->save();
            }

            return $user;
        }

        $user = null;
        if ($email !== '') {
            $user = User::query()->where('email', $email)->first();
        }

        if ($user !== null) {
            if ($user->account_type !== AccountType::Customer) {
                $this->resolveFailureMessage = self::PRIVILEGED_SOCIAL_LOGIN_MESSAGE;

                return null;
            }

            if (! $this->userCanSocialLogin($user)) {
                return null;
            }

            $this->createSocialAccount($user, $provider, $providerId, $oauthUser, $email, $name);

            return $user;
        }

        if ($email === '') {
            return null;
        }

        $user = User::query()->create([
            'name' => $name !== '' ? $name : 'Customer',
            'email' => $email,
            'password' => Str::password(40),
            'account_type' => AccountType::Customer,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => Agency::query()->where('slug', config('ota.default_agency_slug'))->value('id'),
        ]);
        if ($this->isTrustedEmail($provider, $oauthUser)) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }
        $user->forceFill([
            'social_email_verification_deadline' => now()->addDay(),
        ])->save();

        $this->createSocialAccount($user, $provider, $providerId, $oauthUser, $email, $name);
        $this->resolvedWasNewCustomer = true;

        return $user;
    }

    private function createSocialAccount(
        User $user,
        string $provider,
        string $providerId,
        SocialiteUser $oauthUser,
        string $email,
        string $name,
    ): SocialAccount {
        return SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $providerId,
            'provider_email' => $email !== '' ? $email : null,
            'provider_name' => $name !== '' ? $name : null,
            'avatar' => $oauthUser->getAvatar(),
            'meta' => [
                'nickname' => $oauthUser->getNickname(),
            ],
        ]);
    }

    private function userCanSocialLogin(User $user): bool
    {
        if ($user->isSuspended() || $user->status === UserAccountStatus::Inactive) {
            $this->resolveFailureMessage = 'This account is not available for sign in.';

            return false;
        }

        return true;
    }

    private function isTrustedEmail(string $provider, SocialiteUser $oauthUser): bool
    {
        if ($oauthUser->getEmail() === null) {
            return false;
        }

        if ($provider === 'google') {
            return true;
        }

        $raw = $oauthUser->user;
        if (! is_array($raw)) {
            return false;
        }

        return (bool) ($raw['email_verified'] ?? $raw['verified'] ?? false);
    }
}
