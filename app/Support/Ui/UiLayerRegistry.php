<?php

namespace App\Support\Ui;

use InvalidArgumentException;

/**
 * Manifest reader for config/ui-layers.php.
 */
final class UiLayerRegistry
{
    /** @var array<string, UiLayer>|null */
    private static ?array $indexed = null;

    /**
     * @return list<UiLayer>
     */
    public static function all(): array
    {
        return array_values(self::indexed());
    }

    public static function find(string $key): ?UiLayer
    {
        return self::indexed()[$key] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public static function contextLabels(): array
    {
        /** @var array<string, string> $contexts */
        $contexts = config('ui-layers.contexts', []);

        return $contexts;
    }

    public static function isGloballyEnabled(): bool
    {
        return (bool) config('ui-layers.enabled', true);
    }

    /**
     * @return array<string, UiLayer>
     */
    private static function indexed(): array
    {
        if (self::$indexed !== null) {
            return self::$indexed;
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = config('ui-layers.layers', []);
        $indexed = [];

        foreach ($rows as $row) {
            $key = (string) ($row['key'] ?? '');
            if ($key === '') {
                continue;
            }

            if (isset($indexed[$key])) {
                throw new InvalidArgumentException("Duplicate UI layer key in config: {$key}");
            }

            $contexts = array_values(array_filter(array_map(
                static fn ($context): string => trim((string) $context),
                (array) ($row['contexts'] ?? []),
            )));

            $indexed[$key] = new UiLayer(
                key: $key,
                contexts: $contexts,
                order: (int) ($row['order'] ?? 100),
                defaultEnabled: (bool) ($row['enabled'] ?? false),
                css: array_values(array_filter(array_map(
                    static fn ($path): string => ltrim((string) $path, '/'),
                    (array) ($row['css'] ?? []),
                ))),
                js: array_values(array_filter(array_map(
                    static fn ($path): string => ltrim((string) $path, '/'),
                    (array) ($row['js'] ?? []),
                ))),
                description: trim((string) ($row['description'] ?? '')),
                rollback: trim((string) ($row['rollback'] ?? '')),
                suppliers: array_values(array_filter(array_map(
                    static fn ($supplier): string => strtolower(trim((string) $supplier)),
                    (array) ($row['suppliers'] ?? []),
                ))),
            );
        }

        self::$indexed = $indexed;

        return self::$indexed;
    }
}
