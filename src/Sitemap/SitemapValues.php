<?php

namespace SeoKit\Sitemap;

/**
 * Cleans the sitemap values typed in the bulk editor. Core writes whatever the page attributes hold into the
 * file, so bad values would make an invalid sitemap.
 */
final class SitemapValues
{
    public const CHANGE_FREQUENCIES = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];

    public const PER_PAGE_CHOICES = [10, 25, 50, 100];

    public const DEFAULT_PER_PAGE = 50;

    /**
     * How many pages the bulk editor lists at once: one of the choices, otherwise the default.
     */
    public static function perPage(mixed $value): int
    {
        return in_array((int) $value, self::PER_PAGE_CHOICES, true) ? (int) $value : self::DEFAULT_PER_PAGE;
    }

    /**
     * A priority from 0.0 to 1.0 with one decimal ("0.8"), or null when empty or out of range.
     */
    public static function priority(mixed $value): ?string
    {
        $value = trim((string) $value);

        if (!preg_match('/^(?:0(?:\.\d+)?|1(?:\.0+)?|\.\d+)$/', $value)) {
            return null;
        }

        return number_format(round((float) $value, 1), 1, '.', '');
    }

    /**
     * One of the sitemap protocol's change frequencies, or null.
     */
    public static function changeFrequency(mixed $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, self::CHANGE_FREQUENCIES, true) ? $value : null;
    }

    /**
     * Counts the pages in a sitemap file by reading it line by line, so a large file is not loaded into memory.
     */
    public static function countUrls(string $file): int
    {
        $handle = @fopen($file, 'rb');

        if ($handle === false) {
            return 0;
        }

        $count = 0;

        while (($line = fgets($handle)) !== false) {
            $count += substr_count($line, '<url>');
        }

        fclose($handle);

        return $count;
    }
}
