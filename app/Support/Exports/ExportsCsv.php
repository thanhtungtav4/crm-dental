<?php

namespace App\Support\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportsCsv
{
    protected function streamCsv(string $fileName, array $columns, Builder $query): StreamedResponse
    {
        $safeFileName = Str::endsWith($fileName, '.csv') ? $fileName : ($fileName . '.csv');

        return response()->streamDownload(function () use ($columns, $query): void {
            $handle = fopen('php://output', 'w');
            if (! $handle) {
                return;
            }

            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, array_map(fn (array $column) => $column['label'], $columns));

            $query->chunk(500, function ($records) use ($handle, $columns): void {
                foreach ($records as $record) {
                    $row = [];
                    foreach ($columns as $column) {
                        $value = $column['value']($record);
                        $row[] = $this->normalizeCsvValue($value);
                    }
                    fputcsv($handle, $row);
                }
            });

            fclose($handle);
        }, $safeFileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function normalizeCsvValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y H:i');
        }

        if (is_bool($value)) {
            return $value ? 'Có' : 'Không';
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        return (string) $value;
    }
}
