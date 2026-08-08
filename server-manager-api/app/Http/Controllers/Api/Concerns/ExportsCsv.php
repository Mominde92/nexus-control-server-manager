<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a CSV download for a module's export endpoint.
 *
 * Streaming (rather than building one big string) keeps memory flat regardless
 * of row count, since an export deliberately ignores pagination.
 */
trait ExportsCsv
{
    /**
     * @param  array<int, string>  $headers  Human-readable column labels.
     * @param  Collection<int, covariant \Illuminate\Database\Eloquent\Model>  $records
     * @param  callable(mixed): array<int, string|int|float|null>  $mapper  One record => one CSV row.
     */
    protected function streamCsv(
        string $filenamePrefix,
        array $headers,
        Collection $records,
        callable $mapper,
    ): StreamedResponse {
        $filename = sprintf('%s-%s.csv', $filenamePrefix, now()->format('Y-m-d-His'));

        return response()->streamDownload(
            function () use ($headers, $records, $mapper): void {
                $handle = fopen('php://output', 'wb');

                if ($handle === false) {
                    return;
                }

                // BOM so Excel opens UTF-8 accented names correctly instead of
                // mangling them into the system codepage.
                fwrite($handle, "\xEF\xBB\xBF");

                fputcsv($handle, $headers);

                foreach ($records as $record) {
                    fputcsv($handle, array_map(
                        static fn (mixed $cell): string => $cell === null ? '' : (string) $cell,
                        $mapper($record),
                    ));
                }

                fclose($handle);
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache',
            ],
        );
    }
}
