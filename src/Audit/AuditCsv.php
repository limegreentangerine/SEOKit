<?php

namespace SeoKit\Audit;

/**
 * Builds the CSV export of an audit.
 */
final class AuditCsv
{
    public const HEADER = ['Page', 'Path', 'Page score', 'Severity', 'Issue', 'Detail'];

    /**
     * Page names and details come from editors and the public web: stop a spreadsheet running them as formulas.
     */
    protected static function safe(string $cell): string
    {
        return preg_match('/^[=+\-@\t\r]/', $cell) ? "'" . $cell : $cell;
    }

    /**
     * @param array<int, array{pageName: string, pagePath: string, pageScore: int, severity: string, code: string, detail: string}> $rows
     */
    public static function build(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, self::HEADER, ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($handle, array_map([self::class, 'safe'], [
                $row['pageName'],
                $row['pagePath'],
                (string) $row['pageScore'],
                $row['severity'],
                Issues::describe($row['code'], $row['detail'])['label'],
                $row['detail'],
            ]), ',', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
