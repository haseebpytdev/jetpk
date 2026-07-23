<?php

namespace App\Support\OneApi;

use App\Services\Suppliers\OneApi\Exceptions\OneApiValidationException;

/**
 * Gates SOAP fixture file reads to PHPUnit and explicit fixture-only console flows.
 */
final class OneApiFixtureTransportScope
{
    private static bool $enabled = false;

    private static bool $unitTestFixturesAllowed = true;

    private static string $reason = '';

    public static function enable(string $reason): void
    {
        self::$enabled = true;
        self::$reason = $reason;
    }

    public static function disable(): void
    {
        self::$enabled = false;
        self::$reason = '';
    }

    public static function disallowUnitTestFixtures(): void
    {
        self::$unitTestFixturesAllowed = false;
    }

    public static function allowUnitTestFixtures(): void
    {
        self::$unitTestFixturesAllowed = true;
    }

    public static function isEnabled(): bool
    {
        if (self::$enabled) {
            return true;
        }

        return app()->runningUnitTests() && self::$unitTestFixturesAllowed;
    }

    public static function isExplicitlyEnabled(): bool
    {
        return self::$enabled;
    }

    public static function reason(): string
    {
        return self::$reason;
    }

    /**
     * @throws OneApiValidationException
     */
    public static function resolveReadableFixturePath(string $candidatePath): string
    {
        $candidatePath = trim($candidatePath);
        if ($candidatePath === '') {
            return '';
        }

        if (! self::isEnabled()) {
            throw new OneApiValidationException(
                'fixture_forbidden',
                422,
                'Fixture transport is not available in this runtime context.',
            );
        }

        $real = realpath($candidatePath);
        if ($real === false || ! is_file($real)) {
            throw new OneApiValidationException('fixture_forbidden', 422, 'Fixture path is not readable.');
        }

        $allowedRoot = realpath(base_path('tests/Fixtures/Suppliers/OneApi'));
        if ($allowedRoot === false || ! str_starts_with(str_replace('\\', '/', $real), str_replace('\\', '/', $allowedRoot))) {
            throw new OneApiValidationException('fixture_forbidden', 422, 'Fixture path is outside the allowed supplier fixture directory.');
        }

        return $real;
    }
}
