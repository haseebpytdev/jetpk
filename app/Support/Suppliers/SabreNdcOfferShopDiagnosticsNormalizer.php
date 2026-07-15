<?php

namespace App\Support\Suppliers;

/**
 * Normalizes Sabre NDC shop safe-error fields for HTTP 200 zero-offer outcomes.
 */
final class SabreNdcOfferShopDiagnosticsNormalizer
{
    /**
     * @param  array<string, mixed>  $diagnostics
     * @return array<string, mixed>
     */
    public function normalizeOutcome(
        array $diagnostics,
        int $httpStatus,
        int $normalizedOfferCount,
        int $offerCountRaw,
    ): array {
        if ($httpStatus < 200 || $httpStatus >= 300) {
            return $diagnostics;
        }

        if ($normalizedOfferCount > 0 || $offerCountRaw > 0) {
            return $diagnostics;
        }

        $readableMessage = trim((string) ($diagnostics['message_text'] ?? ''));
        if ($readableMessage === '' || ! $this->isReadableMessageText($readableMessage)) {
            $diagnostics['message_text'] = null;
            $diagnostics['safe_error_message'] = null;
        }

        $diagnostics['safe_error_code'] = 'ndc_zero_offers';
        $diagnostics['safe_error_family'] = 'sabre_ndc_zero_offers';

        return $diagnostics;
    }

    private function isReadableMessageText(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if (preg_match('/^\d{3,8}$/', $value) === 1) {
            return false;
        }

        return preg_match('/[A-Za-z]/', $value) === 1;
    }
}
