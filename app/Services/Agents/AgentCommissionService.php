<?php

namespace App\Services\Agents;

use App\Enums\AgentCommissionEntryStatus;
use App\Enums\AgentCommissionEntryType;
use App\Enums\AgentCommissionStatementStatus;
use App\Models\Agent;
use App\Models\AgentCommissionEntry;
use App\Models\AgentCommissionStatement;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingTicket;
use App\Models\MarkupRule;
use App\Models\User;
use App\Services\Finance\Ledger\LedgerEventRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AgentCommissionService
{
    public function generateCommissionForTicket(BookingTicket $ticket): ?AgentCommissionEntry
    {
        $ticket->loadMissing(['booking.agent', 'booking.fareBreakdown']);
        $booking = $ticket->booking;
        if ($booking === null || $booking->agent_id === null || $booking->agent === null) {
            return null;
        }

        $existing = AgentCommissionEntry::query()
            ->where('booking_ticket_id', $ticket->id)
            ->where('type', AgentCommissionEntryType::Earned)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $calculation = $this->calculateCommissionForBooking($booking);
        if ((float) $calculation['commission_amount'] <= 0) {
            return null;
        }

        return AgentCommissionEntry::query()->create([
            'agency_id' => $booking->agency_id,
            'agent_id' => $booking->agent_id,
            'booking_id' => $booking->id,
            'booking_ticket_id' => $ticket->id,
            'type' => AgentCommissionEntryType::Earned,
            'status' => AgentCommissionEntryStatus::Pending,
            'calculation_basis' => $calculation['basis'],
            'rate' => $calculation['rate'],
            'base_amount' => $calculation['base_amount'],
            'commission_amount' => $calculation['commission_amount'],
            'currency' => $calculation['currency'],
            'description' => 'Commission earned for ticket '.$ticket->ticket_number,
            'meta' => $calculation['meta'],
        ]);
    }

    /**
     * @return array{basis: string, rate: float, base_amount: float, commission_amount: float, currency: string, meta: array<string, mixed>}
     */
    public function calculateCommissionForBooking(Booking $booking): array
    {
        $booking->loadMissing(['fareBreakdown', 'agent']);
        $baseAmount = (float) ($booking->fareBreakdown?->base_fare ?? $booking->fareBreakdown?->total ?? 0);
        $currency = (string) ($booking->fareBreakdown?->currency ?? $booking->currency ?? 'PKR');

        $snapshotRules = (array) ($booking->meta['pricing_snapshot']['applied_rules'] ?? $booking->meta['offer_validation_snapshot']['applied_rules'] ?? []);
        foreach ($snapshotRules as $rule) {
            if (($rule['bucket'] ?? null) === 'agent_markup_or_commission' && isset($rule['value'], $rule['value_type'])) {
                $valueType = (string) $rule['value_type'];
                $value = (float) $rule['value'];
                $amount = $valueType === 'percentage' ? round(($baseAmount * $value) / 100, 2) : round($value, 2);

                return [
                    'basis' => $valueType === 'percentage' ? 'percentage' : 'fixed',
                    'rate' => $value,
                    'base_amount' => $baseAmount,
                    'commission_amount' => $amount,
                    'currency' => $currency,
                    'meta' => [
                        'source' => 'booking_snapshot_rule',
                        'applied_rule' => $rule,
                    ],
                ];
            }
        }

        $agentRate = (float) ($booking->agent?->commission_percent ?? 0);
        if ($agentRate > 0) {
            return [
                'basis' => 'percentage',
                'rate' => $agentRate,
                'base_amount' => $baseAmount,
                'commission_amount' => round(($baseAmount * $agentRate) / 100, 2),
                'currency' => $currency,
                'meta' => ['source' => 'agent_profile_default_rate'],
            ];
        }

        $fallbackRule = MarkupRule::query()
            ->where('agency_id', $booking->agency_id)
            ->where('rule_type', 'agent')
            ->where('is_active', true)
            ->orderBy('priority')
            ->first();
        if ($fallbackRule !== null) {
            $value = (float) $fallbackRule->value;
            $basis = $fallbackRule->value_type->value === 'percentage' ? 'percentage' : 'fixed';
            $amount = $basis === 'percentage' ? round(($baseAmount * $value) / 100, 2) : round($value, 2);

            return [
                'basis' => $basis,
                'rate' => $value,
                'base_amount' => $baseAmount,
                'commission_amount' => $amount,
                'currency' => $currency,
                'meta' => [
                    'source' => 'agency_markup_rule_fallback',
                    'rule_id' => $fallbackRule->id,
                ],
            ];
        }

        return [
            'basis' => 'manual',
            'rate' => 0,
            'base_amount' => $baseAmount,
            'commission_amount' => 0,
            'currency' => $currency,
            'meta' => ['source' => 'no_rule'],
        ];
    }

    public function approveEntry(AgentCommissionEntry $entry, User $actor): AgentCommissionEntry
    {
        if (! in_array($entry->status, [AgentCommissionEntryStatus::Pending, AgentCommissionEntryStatus::Rejected], true)) {
            throw new InvalidArgumentException('Only pending/rejected entries can be approved.');
        }

        $entry->forceFill([
            'status' => AgentCommissionEntryStatus::Approved,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ])->save();

        $entry = $entry->fresh();
        app(LedgerEventRecorder::class)->recordAgencyCommissionEarned($entry, $actor);

        return $entry;
    }

    public function rejectEntry(AgentCommissionEntry $entry, User $actor, string $reason): AgentCommissionEntry
    {
        if (! in_array($entry->status, [AgentCommissionEntryStatus::Pending, AgentCommissionEntryStatus::Approved], true)) {
            throw new InvalidArgumentException('Only pending/approved entries can be rejected.');
        }

        $meta = $entry->meta ?? [];
        $meta['rejection_reason'] = $reason;
        $entry->forceFill([
            'status' => AgentCommissionEntryStatus::Rejected,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'meta' => $meta,
        ])->save();

        return $entry->fresh();
    }

    /**
     * @param  array{amount: float|int|string, description?: string}  $data
     */
    public function recordAdjustment(Agent $agent, User $actor, array $data): AgentCommissionEntry
    {
        $amount = round((float) $data['amount'], 2);

        return AgentCommissionEntry::query()->create([
            'agency_id' => $agent->agency_id,
            'agent_id' => $agent->id,
            'type' => AgentCommissionEntryType::Adjustment,
            'status' => AgentCommissionEntryStatus::Approved,
            'calculation_basis' => 'manual',
            'rate' => null,
            'base_amount' => 0,
            'commission_amount' => $amount,
            'currency' => 'PKR',
            'description' => $data['description'] ?? 'Manual commission adjustment',
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'meta' => ['source' => 'manual_adjustment'],
        ]);
    }

    /**
     * @param  array{amount: float|int|string, description?: string}  $data
     */
    public function recordPayout(Agent $agent, User $actor, array $data): AgentCommissionEntry
    {
        $amount = abs(round((float) $data['amount'], 2));

        return AgentCommissionEntry::query()->create([
            'agency_id' => $agent->agency_id,
            'agent_id' => $agent->id,
            'type' => AgentCommissionEntryType::Payout,
            'status' => AgentCommissionEntryStatus::Paid,
            'calculation_basis' => 'manual',
            'rate' => null,
            'base_amount' => 0,
            'commission_amount' => -$amount,
            'currency' => 'PKR',
            'description' => $data['description'] ?? 'Manual commission payout',
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'paid_by' => $actor->id,
            'paid_at' => now(),
            'meta' => ['source' => 'manual_payout'],
        ]);
    }

    public function buildStatement(Agent $agent, User $actor, ?string $periodStart, ?string $periodEnd): AgentCommissionStatement
    {
        $entriesQuery = AgentCommissionEntry::query()
            ->where('agency_id', $agent->agency_id)
            ->where('agent_id', $agent->id)
            ->whereIn('status', [AgentCommissionEntryStatus::Approved, AgentCommissionEntryStatus::Paid]);

        if ($periodStart !== null) {
            $entriesQuery->whereDate('created_at', '>=', $periodStart);
        }
        if ($periodEnd !== null) {
            $entriesQuery->whereDate('created_at', '<=', $periodEnd);
        }

        $entries = $entriesQuery->get();
        $earnedTotal = (float) $entries->where('type', AgentCommissionEntryType::Earned)->sum('commission_amount');
        $adjustmentTotal = (float) $entries->where('type', AgentCommissionEntryType::Adjustment)->sum('commission_amount');
        $payoutTotal = abs((float) $entries->where('type', AgentCommissionEntryType::Payout)->sum('commission_amount'));
        $closingBalance = $earnedTotal + $adjustmentTotal - $payoutTotal;

        return DB::transaction(function () use ($agent, $actor, $periodStart, $periodEnd, $entries, $earnedTotal, $adjustmentTotal, $payoutTotal, $closingBalance): AgentCommissionStatement {
            $statement = AgentCommissionStatement::query()->create([
                'agency_id' => $agent->agency_id,
                'agent_id' => $agent->id,
                'statement_number' => 'STM-'.strtoupper(Str::random(8)),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'status' => AgentCommissionStatementStatus::Issued,
                'opening_balance' => 0,
                'earned_total' => $earnedTotal,
                'adjustment_total' => $adjustmentTotal,
                'payout_total' => $payoutTotal,
                'closing_balance' => $closingBalance,
                'issued_by' => $actor->id,
                'issued_at' => now(),
                'meta' => ['entries_count' => $entries->count()],
            ]);

            $statement->entries()->sync($entries->pluck('id')->all());

            return $statement->fresh(['entries']);
        });
    }

    public function calculateBalance(Agent $agent): float
    {
        return (float) AgentCommissionEntry::query()
            ->where('agent_id', $agent->id)
            ->whereIn('status', [AgentCommissionEntryStatus::Approved, AgentCommissionEntryStatus::Paid])
            ->sum('commission_amount');
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    public function writeAudit(Agent $agent, User $actor, string $action, array $newValues): void
    {
        AuditLog::query()->create([
            'agency_id' => $agent->agency_id,
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => Agent::class,
            'auditable_id' => $agent->id,
            'properties' => [
                'old_values' => [],
                'new_values' => $newValues,
            ],
        ]);
    }
}
