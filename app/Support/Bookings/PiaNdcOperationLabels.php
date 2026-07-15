<?php

namespace App\Support\Bookings;

/**
 * Canonical Hitit Crane NDC SOAP operation display labels (R12R).
 *
 * Internal config keys (e.g. order_change) map to supplier SOAPAction short names
 * (e.g. doOrderChange). Never append suffixes from config keys — that produced
 * legacy typos such as doOrderChangee / doVoidTickett.
 */
final class PiaNdcOperationLabels
{
    public const DISPLAY_ORDER_CHANGE = 'doOrderChange';

    public const DISPLAY_VOID_TICKET = 'doVoidTicket';

    public const DISPLAY_TICKET_PREVIEW = 'doTicketPreview';

    public const DISPLAY_ORDER_CANCEL_COMMIT = 'doOrderCancelCommit';

    /** @var list<string> */
    private const LEGACY_TYPO_LABELS = [
        'doOrderChangee',
        'doVoidTickett',
    ];

    public static function displayForConfigKey(string $operationKey): string
    {
        $soapAction = trim((string) config('suppliers.pia_ndc.operations.'.$operationKey.'.soap_action', ''));
        if ($soapAction !== '') {
            return $soapAction;
        }

        return match ($operationKey) {
            'order_change' => self::DISPLAY_ORDER_CHANGE,
            'void_ticket' => self::DISPLAY_VOID_TICKET,
            'ticket_preview' => self::DISPLAY_TICKET_PREVIEW,
            'cancel_commit' => self::DISPLAY_ORDER_CANCEL_COMMIT,
            default => $operationKey,
        };
    }

    public static function sanitizeDisplayOperation(?string $operation, string $configKey): string
    {
        $canonical = self::displayForConfigKey($configKey);
        $candidate = trim((string) $operation);
        if ($candidate === '' || in_array($candidate, self::LEGACY_TYPO_LABELS, true)) {
            return $canonical;
        }

        return $candidate;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public static function applyToSummary(array $summary, string $configKey): array
    {
        $summary['operation'] = self::sanitizeDisplayOperation(
            is_string($summary['operation'] ?? null) ? $summary['operation'] : null,
            $configKey,
        );

        return $summary;
    }
}
