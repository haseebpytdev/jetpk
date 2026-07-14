<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Safe known-good / failure evidence for Sabre GDS PNR create strategy selection (no PII, no raw payload).
 */
class SabreGdsPnrCreateStrategyEvidence extends Model
{
    public const OUTCOME_SUCCESS = 'success';

    public const OUTCOME_FAILURE = 'failure';

    protected $table = 'sabre_gds_pnr_create_strategy_evidence';

    protected $fillable = [
        'supplier_connection_id',
        'provider',
        'distribution_channel',
        'strategy_code',
        'endpoint_path',
        'payload_schema',
        'carrier_chain',
        'validating_carrier',
        'route_pattern',
        'trip_type',
        'segment_count',
        'outcome',
        'success_count',
        'last_success_at',
        'last_success_booking_id',
        'failed_booking_id',
        'host_error_family',
        'safe_reason_code',
    ];

    protected function casts(): array
    {
        return [
            'supplier_connection_id' => 'integer',
            'segment_count' => 'integer',
            'success_count' => 'integer',
            'last_success_at' => 'datetime',
            'last_success_booking_id' => 'integer',
            'failed_booking_id' => 'integer',
        ];
    }
}
