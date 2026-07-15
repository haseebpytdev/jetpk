<?php

namespace App\Support\Sabre\GdsPnrCreate;

/**
 * Classifies Sabre GDS PNR create host outcomes for safe retry policy (no raw payload / PII).
 */
final class SabreGdsPnrCreateStrategyResultClassifier
{
    public const REASON_ENHANCED_AIRBOOK_FORMAT = 'sabre_enhanced_airbook_format_error';

    public const HOST_ERROR_FAMILY_ENHANCED_AIRBOOK_FORMAT = 'ENHANCED_AIRBOOK_FORMAT';

    public const RETRY_POLICY_ADMIN_CONFIRMED_FALLBACK_ONLY = 'admin_confirmed_fallback_only';

    public const RECOMMENDED_ADMIN_ACTION_FORMAT = 'Review strategy digest and retry with eligible fallback strategy only after operator confirmation.';

    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *     safe_reason_code: string,
     *     host_error_family: string|null,
     *     retry_policy: string,
     *     recommended_admin_action: string,
     *     manual_review_required: bool
     * }
     */
    public function classify(array $context): array
    {
        if ($this->isEnhancedAirBookFormatError($context)) {
            return [
                'safe_reason_code' => self::REASON_ENHANCED_AIRBOOK_FORMAT,
                'host_error_family' => self::HOST_ERROR_FAMILY_ENHANCED_AIRBOOK_FORMAT,
                'retry_policy' => self::RETRY_POLICY_ADMIN_CONFIRMED_FALLBACK_ONLY,
                'recommended_admin_action' => self::RECOMMENDED_ADMIN_ACTION_FORMAT,
                'manual_review_required' => true,
            ];
        }

        return [
            'safe_reason_code' => '',
            'host_error_family' => null,
            'retry_policy' => '',
            'recommended_admin_action' => '',
            'manual_review_required' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function isEnhancedAirBookFormatError(array $context): bool
    {
        $messages = $this->collectMessageText($context);

        return str_contains($messages, 'ENHANCEDAIRBOOKRQ')
            && str_contains($messages, 'FORMAT');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function collectMessageText(array $context): string
    {
        $parts = [];
        foreach (['response_error_messages', 'application_error_messages', 'messages', 'message', 'safe_message'] as $key) {
            $value = $context[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $parts[] = strtoupper($value);
            }
            if (is_array($value)) {
                foreach ($value as $row) {
                    if (is_string($row) && trim($row) !== '') {
                        $parts[] = strtoupper($row);
                    }
                }
            }
        }

        return implode(' ', $parts);
    }
}
