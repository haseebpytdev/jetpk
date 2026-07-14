<?php

namespace App\Support\Suppliers;

/**
 * Supplier-neutral OTA booking lifecycle contract (search → create → retrieve → cancel → ticket).
 *
 * Each supplier adapter declares action metadata via {@see SupplierLifecycleCapabilities}
 * and wire-format strategies via {@see SupplierActionStrategyRegistry}.
 */
final class SupplierLifecycleContract
{
    /** @var list<string> */
    public const STAGES = [
        'search',
        'fare_selection',
        'selected_fare_context',
        'revalidation',
        'strategy_selection',
        'supplier_create',
        'pnr_order_validation',
        'retrieve_sync',
        'unticketed_cancel',
        'ticketing',
    ];

    /**
     * @return array{
     *     action_code: string,
     *     live_mutation: bool,
     *     duplicate_risk_level: string,
     *     retry_policy: string,
     *     required_admin_gate: ?string
     * }
     */
    public static function actionDefaults(string $actionCode): array
    {
        return match ($actionCode) {
            SupplierActionCode::CREATE_PNR, SupplierActionCode::CREATE_ORDER => [
                'action_code' => $actionCode,
                'live_mutation' => true,
                'duplicate_risk_level' => 'high',
                'retry_policy' => 'admin_confirmed_fallback_only',
                'required_admin_gate' => null,
            ],
            SupplierActionCode::CANCEL_UNTICKETED => [
                'action_code' => $actionCode,
                'live_mutation' => true,
                'duplicate_risk_level' => 'medium',
                'retry_policy' => 'admin_confirmed_only',
                'required_admin_gate' => 'unticketed_cancel_enabled',
            ],
            SupplierActionCode::TICKET => [
                'action_code' => $actionCode,
                'live_mutation' => true,
                'duplicate_risk_level' => 'high',
                'retry_policy' => 'blocked_in_this_phase',
                'required_admin_gate' => 'ticketing_enabled',
            ],
            default => [
                'action_code' => $actionCode,
                'live_mutation' => false,
                'duplicate_risk_level' => 'none',
                'retry_policy' => 'read_only',
                'required_admin_gate' => null,
            ],
        };
    }
}
