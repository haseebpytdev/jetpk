<?php

namespace App\Support\AgentPortal;

use App\Models\Agency;
use Illuminate\Http\Request;

/**
 * Agent finance statement JSON for Next.js dashboard (read-only).
 */
class AgentPortalFinanceStatementPresenter
{
    /**
     * @param  array<string, mixed>  $statement
     * @return array<string, mixed>
     */
    public function present(array $statement, Request $request, bool $canExport): array
    {
        $agency = $statement['agency'] ?? null;
        $agencyName = $agency instanceof Agency ? (string) $agency->name : '';
        $period = is_array($statement['period'] ?? null) ? $statement['period'] : [];
        $currency = (string) ($statement['currency'] ?? 'PKR');

        $exportUrl = null;
        if ($canExport) {
            $query = array_filter([
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to'),
            ], static fn ($value): bool => filled($value));
            $exportUrl = '/laravel/agent/finance/statement/export'.($query !== [] ? '?'.http_build_query($query) : '');
        }

        return [
            'ok' => true,
            'agency' => [
                'name' => $agencyName,
            ],
            'period' => [
                'from' => (string) ($period['from'] ?? ''),
                'to' => (string) ($period['to'] ?? ''),
            ],
            'currency' => $currency,
            'opening_balance' => (float) ($statement['opening_balance'] ?? 0),
            'closing_balance' => (float) ($statement['closing_balance'] ?? 0),
            'total_debits' => (float) ($statement['total_debits'] ?? 0),
            'total_credits' => (float) ($statement['total_credits'] ?? 0),
            'movements' => collect($statement['movements'] ?? [])
                ->map(fn (array $movement): array => [
                    'date' => (string) ($movement['date'] ?? ''),
                    'type' => (string) ($movement['type'] ?? ''),
                    'description' => (string) ($movement['description'] ?? ''),
                    'reference' => (string) ($movement['reference'] ?? ''),
                    'booking_reference' => filled($movement['booking_reference'] ?? null)
                        ? (string) $movement['booking_reference']
                        : null,
                    'debit' => (float) ($movement['debit'] ?? 0),
                    'credit' => (float) ($movement['credit'] ?? 0),
                    'running_balance' => (float) ($movement['running_balance'] ?? 0),
                    'status' => (string) ($movement['status'] ?? ''),
                    'created_by' => filled($movement['created_by'] ?? null) ? (string) $movement['created_by'] : null,
                    'approved_by' => filled($movement['approved_by'] ?? null) ? (string) $movement['approved_by'] : null,
                ])
                ->values()
                ->all(),
            'reconciliation' => [
                'wallet_balance' => (float) ($statement['reconciliation']['wallet_balance'] ?? 0),
                'ledger_liability' => (float) ($statement['reconciliation']['ledger_liability'] ?? 0),
                'difference' => (float) ($statement['reconciliation']['difference'] ?? 0),
                'status' => (string) ($statement['reconciliation']['status'] ?? ''),
                'matches' => (bool) ($statement['reconciliation']['matches'] ?? false),
            ],
            'export_url' => $exportUrl,
            'blade_fallback_url' => '/laravel/agent/finance/statement',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentValidationError(string $message): array
    {
        return [
            'ok' => false,
            'code' => 'validation',
            'message' => $message,
            'errors' => [
                'date_from' => [$message],
            ],
        ];
    }
}
