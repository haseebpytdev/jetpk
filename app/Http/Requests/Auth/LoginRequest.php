<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserAccountStatus;
use App\Models\User;
use App\Services\Communication\AuthSecurityEmailNotificationService;
use App\Services\Security\SecurityEventLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => ['nullable', 'required_without:email', 'string'],
            'email' => ['nullable', 'required_without:login', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $user = $this->validateCredentials();

        Auth::login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Validate credentials without establishing an authenticated session.
     *
     * @throws ValidationException
     */
    public function validateCredentials(): User
    {
        $this->ensureIsNotRateLimited();

        $login = $this->loginIdentifier();
        $field = str_contains($login, '@') ? 'email' : 'username';
        $user = $this->resolveUserByLogin($login, $field);
        $password = (string) $this->input('password');

        if ($user === null || ! Hash::check($password, (string) $user->password) || ! $this->userCanPasswordLogin($user)) {
            RateLimiter::hit($this->throttleKey());
            try {
                app(AuthSecurityEmailNotificationService::class)->notifyFailedLogin($this, $login, $field);
            } catch (\Throwable) {
                // fail-safe: never block login flow on security email failure
            }

            try {
                app(SecurityEventLogger::class)->record(
                    eventType: 'auth.login',
                    outcome: 'failure',
                    actor: $user,
                    agencyId: $user?->current_agency_id,
                    request: $this,
                    metadata: ['login_field' => $field],
                );
            } catch (\Throwable) {
                // fail-safe
            }

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->loginIdentifier()).'|'.$this->ip());
    }

    protected function loginIdentifier(): string
    {
        $login = trim((string) $this->input('login', ''));
        if ($login !== '') {
            return $login;
        }

        return trim((string) $this->input('email', ''));
    }

    protected function resolveUserByLogin(string $login, string $field): ?User
    {
        $normalized = Str::lower($login);

        return User::query()
            ->when(
                $field === 'email',
                fn ($query) => $query->whereRaw('LOWER(email) = ?', [$normalized]),
                fn ($query) => $query->whereRaw('LOWER(username) = ?', [$normalized]),
            )
            ->first();
    }

    protected function userCanPasswordLogin(User $user): bool
    {
        if ($user->isSuspended() || $user->status === UserAccountStatus::Inactive) {
            return false;
        }

        return true;
    }
}
