<?php

namespace App\Services\Ai\Lab;

/**
 * Request/process-scoped AI lab fault simulation for internal canary only.
 * Never mutates config(), .env, or systemd state.
 */
final class AiLabFaultInjectionContext
{
    public const MODE_NORMAL = 'NORMAL';

    public const MODE_SIMULATE_GATEWAY_DOWN = 'SIMULATE_GATEWAY_DOWN';

    public const MODE_SIMULATE_OLLAMA_DOWN = 'SIMULATE_OLLAMA_DOWN';

    public const MODE_SIMULATE_MALFORMED_RESPONSE = 'SIMULATE_MALFORMED_RESPONSE';

    private static ?string $mode = null;

    /**
     * @param  callable(): mixed  $callback
     */
    public static function using(string $mode, callable $callback): mixed
    {
        $previous = self::$mode;
        self::$mode = self::normalize($mode);
        try {
            return $callback();
        } finally {
            self::$mode = $previous;
        }
    }

    public static function set(?string $mode): void
    {
        self::$mode = $mode === null ? null : self::normalize($mode);
    }

    public static function clear(): void
    {
        self::$mode = null;
    }

    public static function mode(): string
    {
        return self::$mode ?? self::MODE_NORMAL;
    }

    public static function isActive(): bool
    {
        return self::mode() !== self::MODE_NORMAL;
    }

    /**
     * @return list<string>
     */
    public static function allowedModes(): array
    {
        return [
            self::MODE_SIMULATE_GATEWAY_DOWN,
            self::MODE_SIMULATE_OLLAMA_DOWN,
            self::MODE_SIMULATE_MALFORMED_RESPONSE,
        ];
    }

    public static function normalize(string $mode): string
    {
        $upper = strtoupper(trim($mode));

        return in_array($upper, self::allowedModes(), true) ? $upper : self::MODE_NORMAL;
    }
}
