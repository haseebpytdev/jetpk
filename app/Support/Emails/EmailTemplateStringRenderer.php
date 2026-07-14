<?php

namespace App\Support\Emails;

use Illuminate\Support\Facades\Log;

/**
 * Safe placeholder renderer for email subjects, bodies, and preview text.
 */
class EmailTemplateStringRenderer
{
    private const PLACEHOLDER_PATTERN = '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/';

    /**
     * @param  array<string, scalar|null>  $variables
     * @param  array{template_key?: string, event_key?: string, booking_reference?: string, audience?: string, brand_name?: string, audit_mode?: bool}|null  $context
     */
    public function render(string $template, array $variables, ?array $context = null): EmailTemplateRenderResult
    {
        $context = $context ?? [];
        $normalized = EmailPlaceholderFallbacks::applyVariableAliases($this->normalizeVariables($variables));
        $missingKeysOriginal = [];
        $fallbackKeysApplied = [];

        $output = preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            function (array $matches) use ($normalized, $context, &$missingKeysOriginal, &$fallbackKeysApplied): string {
                $key = trim($matches[1]);
                $canonical = EmailPlaceholderFallbacks::canonicalKey($key);

                $value = $this->resolvedValue($normalized, $key, $canonical);
                if ($value !== null) {
                    return e($value);
                }

                $missingKeysOriginal[] = $key;

                $fallbackContext = array_merge($context, [
                    'brand_name' => $normalized['brand_name'] ?? $context['brand_name'] ?? null,
                    'agency_name' => $normalized['agency_name'] ?? $context['agency_name'] ?? null,
                    'company_name' => $normalized['company_name'] ?? $context['company_name'] ?? null,
                ]);
                $fallback = EmailPlaceholderFallbacks::fallbackFor($key, $fallbackContext);
                if ($fallback !== null) {
                    $fallbackKeysApplied[] = $key;

                    return e($fallback);
                }

                return $matches[0];
            },
            $template,
        ) ?? $template;

        $missingKeysOriginal = array_values(array_unique($missingKeysOriginal));
        $fallbackKeysApplied = array_values(array_unique($fallbackKeysApplied));

        $unresolvedAfterFallback = $this->unresolvedKeys($output);
        $auditMode = (bool) ($context['audit_mode'] ?? false);
        if (! $auditMode && ($missingKeysOriginal !== [] || $fallbackKeysApplied !== [] || $unresolvedAfterFallback !== [])) {
            $this->logPlaceholderResolution(
                $missingKeysOriginal,
                $fallbackKeysApplied,
                $unresolvedAfterFallback,
                $context,
            );
        }

        if ($unresolvedAfterFallback !== []) {
            $output = $this->stripUnresolved($output);
        }

        return new EmailTemplateRenderResult(
            output: $output,
            unresolvedKeys: $unresolvedAfterFallback,
            hadUnresolved: $unresolvedAfterFallback !== [],
            missingKeysOriginal: $missingKeysOriginal,
            fallbackKeysApplied: $fallbackKeysApplied,
            unresolvedAfterFallback: $unresolvedAfterFallback,
        );
    }

    /**
     * @param  array<string, scalar|null>  $variables
     * @param  array{template_key?: string, event_key?: string, booking_reference?: string, audience?: string, brand_name?: string, audit_mode?: bool}|null  $context
     */
    public static function renderEmailTemplateString(string $template, array $variables, ?array $context = null): string
    {
        return app(self::class)->render($template, $variables, $context)->output;
    }

    /**
     * @return list<string>
     */
    public function unresolvedKeys(string $text): array
    {
        if (! preg_match_all(self::PLACEHOLDER_PATTERN, $text, $matches)) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }

    public function stripUnresolved(string $text): string
    {
        return preg_replace(self::PLACEHOLDER_PATTERN, '', $text) ?? $text;
    }

    /**
     * @param  array<string, string>  $normalized
     */
    protected function resolvedValue(array $normalized, string $key, string $canonical): ?string
    {
        foreach ([$key, $canonical] as $candidate) {
            if (! array_key_exists($candidate, $normalized)) {
                continue;
            }

            $value = trim((string) ($normalized[$candidate] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, scalar|null>  $variables
     * @return array<string, string>
     */
    protected function normalizeVariables(array $variables): array
    {
        $normalized = [];
        foreach ($variables as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }
            $normalized[(string) $key] = (string) ($value ?? '');
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $missingKeysOriginal
     * @param  list<string>  $fallbackKeysApplied
     * @param  list<string>  $unresolvedAfterFallback
     * @param  array{template_key?: string, event_key?: string, booking_reference?: string, audience?: string, brand_name?: string, audit_mode?: bool}  $context
     */
    protected function logPlaceholderResolution(
        array $missingKeysOriginal,
        array $fallbackKeysApplied,
        array $unresolvedAfterFallback,
        array $context,
    ): void {
        try {
            $payload = [
                'template_key' => $context['template_key'] ?? null,
                'event_key' => $context['event_key'] ?? null,
                'booking_reference' => $context['booking_reference'] ?? null,
                'missing_keys_original' => $missingKeysOriginal,
                'fallback_keys_applied' => $fallbackKeysApplied,
                'unresolved_after_fallback' => $unresolvedAfterFallback,
            ];

            if ($unresolvedAfterFallback !== []) {
                Log::warning('ota.email.unresolved_placeholders', $payload);

                return;
            }

            if ($fallbackKeysApplied !== []) {
                Log::info('ota.email.placeholder_fallbacks_applied', $payload);
            }
        } catch (\Throwable) {
            // Never block email send because logging failed.
        }
    }
}
