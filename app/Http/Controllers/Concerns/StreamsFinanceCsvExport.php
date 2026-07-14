<?php

namespace App\Http\Controllers\Concerns;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streamed CSV downloads for finance audit exports (read-only).
 */
trait StreamsFinanceCsvExport
{
    /**
     * @param  list<list<string|int|float|null>>  $rows
     */
    protected function streamFinanceCsv(array $rows, string $filenamePrefix): StreamedResponse
    {
        $filename = sprintf('%s-%s.csv', $filenamePrefix, now()->format('Ymd-Hi'));

        return response()->stream(static function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

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
