<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Agency;
use App\Services\Finance\Statements\AgentStatementService;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait StreamsFinanceStatementCsv
{
    protected function streamStatementCsv(Agency $agency, array $statement): StreamedResponse
    {
        $rows = app(AgentStatementService::class)->csvRows($statement);
        $filename = sprintf(
            'agent-statement-%s-%s-%s.csv',
            $agency->id,
            $statement['period']['from'] ?? 'from',
            $statement['period']['to'] ?? 'to',
        );

        return response()->stream(static function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
