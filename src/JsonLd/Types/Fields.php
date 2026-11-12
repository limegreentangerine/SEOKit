<?php

namespace SeoKit\JsonLd\Types;

/**
 * Cleans posted or stored field values against a type's field definitions.
 *
 * A definition is: name, label, kind, required?, default? (a page data key), options? (value => label),
 * help?, fields? (sub-definitions for kind "list").
 *
 * Kinds: text, textarea, url, urls, date, datetime, number, select, lines, list.
 */
final class Fields
{
    public const MAX_TEXT = 500;

    public const MAX_TEXTAREA = 5000;

    public const MAX_LINES = 20;

    /**
     * @param array<int, string> $errors
     *
     * @return string|array
     */
    protected static function value(array $def, mixed $raw, array &$errors): string|array
    {
        switch ($def['kind']) {
            case 'list':
                return self::rows($def, $raw, $errors);
            case 'lines':
                return self::lines($raw, false);
            case 'urls':
                return self::lines($raw, true);
            case 'textarea':
                return self::text($raw, self::MAX_TEXTAREA, true);
            case 'url':
                return self::url($raw);
            case 'date':
                $value = self::text($raw, 10);

                return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $value : '';
            case 'datetime':
                $value = self::text($raw, 19);

                return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $value) && strtotime($value) !== false ? $value : '';
            case 'number':
                $value = self::text($raw, 20);

                // non-negative unless the definition says otherwise (longitude is negative west of Greenwich)
                $min = $def['min'] ?? 0;
                $max = $def['max'] ?? PHP_FLOAT_MAX;

                return is_numeric($value) && (float) $value >= $min && (float) $value <= $max ? (string) $value : '';
            case 'select':
                $value = self::text($raw, 100);

                return array_key_exists($value, $def['options'] ?? []) ? $value : '';
            default:
                return self::text($raw, self::MAX_TEXT);
        }
    }

    /**
     * Strips control characters (keeping newlines in multi-line text) and caps the length.
     */
    protected static function text(mixed $raw, int $max, bool $multiline = false): string
    {
        if (!is_scalar($raw)) {
            return '';
        }

        $pattern = $multiline ? '/[^\P{C}\n]+/u' : '/\p{C}+/u';
        $text = preg_replace($pattern, ' ', str_replace("\r\n", "\n", (string) $raw)) ?? '';

        return mb_substr(trim($text), 0, $max);
    }

    protected static function url(mixed $raw): string
    {
        $url = self::text($raw, 2000);

        return preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }

    /**
     * @return array<int, string>
     */
    protected static function lines(mixed $raw, bool $urls): array
    {
        $lines = is_string($raw) ? preg_split('/\R/', $raw) : (is_array($raw) ? $raw : []);
        $clean = [];

        foreach ($lines as $line) {
            $line = $urls ? self::url($line) : self::text($line, self::MAX_TEXT);

            if ($line !== '') {
                $clean[] = $line;
            }
        }

        return array_slice($clean, 0, self::MAX_LINES);
    }

    /**
     * Rows of a repeatable group. Entirely empty rows are dropped; a partly filled row missing a required
     * sub-field is an error.
     *
     * @param array<int, string> $errors
     *
     * @return array<int, array>
     */
    protected static function rows(array $def, mixed $raw, array &$errors): array
    {
        $rows = [];

        foreach (array_values(is_array($raw) ? $raw : []) as $row) {
            $cleaned = self::clean($def['fields'], $row);
            $filled = array_filter($cleaned['data'], static fn($v) => !self::isEmpty($v));

            if ($filled === []) {
                continue;
            }

            foreach ($cleaned['errors'] as $error) {
                $errors[] = sprintf('%s, item %d: %s', $def['label'], count($rows) + 1, $error);
            }

            $rows[] = $cleaned['data'];

            if (count($rows) >= self::MAX_LINES * 5) {
                break;
            }
        }

        return $rows;
    }

    /**
     * Cleans $raw against $defs.
     *
     * With $page the defaults are applied and required fields enforced. Without it (at save time, when the page
     * is not known) fields that have a default are not required, because the default fills them when rendering.
     *
     * @param array<int, array> $defs
     *
     * @return array{data: array, errors: array<int, string>}
     */
    public static function clean(array $defs, mixed $raw, ?array $page = null): array
    {
        $raw = is_array($raw) ? $raw : [];
        $data = [];
        $errors = [];

        foreach ($defs as $def) {
            $name = $def['name'];
            $value = self::value($def, $raw[$name] ?? null, $errors);

            if (self::isEmpty($value) && $page !== null && isset($def['default']) && !self::isEmpty($page[$def['default']] ?? null)) {
                $value = $page[$def['default']];
            }

            $needed = !empty($def['required']) && ($page !== null || !isset($def['default']));

            if ($needed && self::isEmpty($value)) {
                $errors[] = sprintf('%s is required.', $def['label']);
            }

            $data[$name] = $value;
        }

        return ['data' => $data, 'errors' => $errors];
    }

    public static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
