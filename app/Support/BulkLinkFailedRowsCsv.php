<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkLinkFailedRowsCsv
{
    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): list<string|int|null>  $mapRow
     */
    public static function download(
        string $filename,
        array $headers,
        array $rows,
        int $errorCount,
        callable $mapRow,
    ): StreamedResponse {
        $storedCount = count($rows);
        $truncated = $errorCount > $storedCount;

        return response()->stream(function () use ($headers, $rows, $errorCount, $storedCount, $truncated, $mapRow): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            if ($truncated) {
                fputcsv($out, [
                    sprintf(
                        '# error_count=%d; baris di file ini=%d (simpanan dibatasi — total error bisa lebih besar)',
                        $errorCount,
                        $storedCount,
                    ),
                ]);
            }

            fputcsv($out, $headers);

            foreach ($rows as $row) {
                fputcsv($out, $mapRow($row));
            }
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}