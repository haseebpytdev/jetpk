<?php

namespace App\Support\Suppliers;

use App\Enums\SupplierProvider;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;

/**
 * Shared admin credential validation for group-token suppliers (Al-Haider, Ameer-e-Millat).
 */
final class GroupSupplierCredentialValidator
{
    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $existingCredentials
     */
    public static function validate(
        ValidatorContract $validator,
        string $provider,
        array $credentials,
        array $existingCredentials = [],
        bool $isUpdate = false,
    ): void {
        if ($provider === SupplierProvider::AlHaider->value) {
            self::validateTokenProvider(
                $validator,
                $provider,
                $credentials,
                $existingCredentials,
                $isUpdate,
                AlHaiderSupplierConnectionNormalizer::class,
            );

            return;
        }

        if ($provider === SupplierProvider::AmeerEMillat->value) {
            self::validateTokenProvider(
                $validator,
                $provider,
                $credentials,
                $existingCredentials,
                $isUpdate,
                AmeerEMillatSupplierConnectionNormalizer::class,
                requiresEmail: true,
            );
        }
    }

    /**
     * @param  class-string  $normalizerClass
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $existingCredentials
     */
    private static function validateTokenProvider(
        ValidatorContract $validator,
        string $provider,
        array $credentials,
        array $existingCredentials,
        bool $isUpdate,
        string $normalizerClass,
        bool $requiresEmail = false,
    ): void {
        $label = strtoupper(str_replace('_', ' ', $provider));
        $authMode = strtolower(trim((string) (
            SupplierCredentialFormPresenter::effectiveValue('auth_mode', $credentials, $existingCredentials)
            ?: $normalizerClass::AUTH_MODE_MANUAL
        )));

        if (! in_array($authMode, $normalizerClass::supportedAuthModes(), true)) {
            $validator->errors()->add('credentials.auth_mode', "Invalid authentication mode for {$label}.");

            return;
        }

        if ($authMode === $normalizerClass::AUTH_MODE_AUTO) {
            $username = SupplierCredentialFormPresenter::effectiveValue('username', $credentials, $existingCredentials);
            $email = SupplierCredentialFormPresenter::effectiveValue('email', $credentials, $existingCredentials);
            $password = SupplierCredentialFormPresenter::effectiveValue('password', $credentials, $existingCredentials);

            if ($requiresEmail && $email === '' && $username === '') {
                $validator->errors()->add('credentials.email', "{$label} requires email for automatic login.");
            } elseif (! $requiresEmail && $username === '') {
                $validator->errors()->add('credentials.username', "{$label} requires username for automatic login.");
            }

            if ($password === '') {
                $validator->errors()->add('credentials.password', "{$label} requires password for automatic login.");
            }

            return;
        }

        $token = SupplierCredentialFormPresenter::effectiveValue('existing_token', $credentials, $existingCredentials);
        if ($token === '' && ! $isUpdate) {
            $validator->errors()->add('credentials.existing_token', "{$label} requires a bearer token for this authentication mode.");
        }
    }
}
