<?php

namespace App\Rules;

use App\Support\Security\TurnstileVerifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Turnstile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! TurnstileVerifier::isEnabled()) {
            return;
        }

        $ip = request()->ip();
        $verified = app(TurnstileVerifier::class)->verify(is_scalar($value) ? (string) $value : null, is_string($ip) ? $ip : null);

        if (! $verified) {
            $fail(TurnstileVerifier::FAILURE_MESSAGE);
        }
    }
}
