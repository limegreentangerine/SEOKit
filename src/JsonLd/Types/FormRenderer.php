<?php

namespace SeoKit\JsonLd\Types;

/**
 * Renders the editor form for the schema attribute. Pure string building so it can be tested without Concrete;
 * every value and name is escaped.
 */
final class FormRenderer
{
    /**
     * Placeholder for a row index inside <template>; the page script replaces it with a unique number.
     */
    public const ROW_INDEX = '__i__';

    protected static function input(string $name, array $def, mixed $value): string
    {
        $n = self::e($name);
        $req = self::requiredAttribute($def);
        $v = is_array($value) ? implode("\n", array_map('strval', $value)) : (string) $value;
        $type = ['url' => 'url', 'date' => 'date', 'datetime' => 'datetime-local', 'number' => 'number'][$def['kind']] ?? 'text';

        switch ($def['kind']) {
            case 'textarea':
            case 'lines':
            case 'urls':
                return '<textarea class="form-control" rows="3" name="' . $n . '"' . $req . '>' . self::e($v) . '</textarea>';
            case 'select':
                $html = '<select class="form-select" name="' . $n . '"' . $req . '>';

                foreach ($def['options'] as $key => $text) {
                    $html .= '<option value="' . self::e((string) $key) . '"' . ((string) $key === $v ? ' selected' : '') . '>' . self::e(t($text)) . '</option>';
                }

                return $html . '</select>';
            default:
                $extra = $def['kind'] === 'number'
                    ? ' min="' . self::e((string) ($def['min'] ?? 0)) . '"' . (isset($def['max']) ? ' max="' . self::e((string) $def['max']) . '"' : '') . ' step="any"'
                    : '';

                return '<input class="form-control" type="' . $type . '" name="' . $n . '" value="' . self::e($v) . '"' . $extra . $req . '>';
        }
    }

    /**
     * A repeatable group: existing rows, a <template> for new ones, and add/remove buttons wired by the page script.
     */
    protected static function listing(string $name, array $def, array $rows): string
    {
        $html = '<div data-seo-list' . self::requiredAttribute($def) . '><div data-seo-rows>';

        foreach (array_values($rows) as $i => $row) {
            $html .= self::row($name . '[' . $i . ']', $def['fields'], $row);
        }

        $html .= '</div><template>' . self::row($name . '[' . self::ROW_INDEX . ']', $def['fields'], []) . '</template>';
        $html .= '<button type="button" class="btn btn-sm btn-secondary" data-seo-add>' . self::e(t('Add')) . '</button></div>';

        return $html;
    }

    protected static function row(string $name, array $fields, array $row): string
    {
        $html = '<div class="card card-body mb-2" data-seo-row>';

        foreach ($fields as $def) {
            $html .= self::field($name . '[' . $def['name'] . ']', $def, $row[$def['name']] ?? null);
        }

        return $html . '<button type="button" class="btn btn-sm btn-link text-danger align-self-start" data-seo-remove>' . self::e(t('Remove')) . '</button></div>';
    }

    /**
     * Marks a required field (one with no page default) so the page script can warn while it is empty.
     * Concrete's page attributes panel does not run an attribute's validation, so this is the only feedback.
     */
    protected static function requiredAttribute(array $def): string
    {
        return !empty($def['required']) && !isset($def['default'])
            ? ' data-seo-required="' . self::e(t($def['label'])) . '"'
            : '';
    }

    protected static function help(array $def): string
    {
        return empty($def['help']) ? '' : '<div class="form-text">' . self::e(t($def['help'])) . '</div>';
    }

    protected static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param string               $base   form field name for the posted array, e.g. akID[12]
     * @param array<string, array> $stored values per type handle
     */
    public static function render(string $base, ?string $selected, array $stored): string
    {
        $html = '<select class="form-select" name="' . self::e($base . '[type]') . '" data-seo-schema-type>';
        $html .= '<option value="">' . self::e(t('None')) . '</option>';

        foreach (TypeRegistry::all() as $handle => $class) {
            $html .= '<option value="' . self::e($handle) . '"' . ($handle === $selected ? ' selected' : '') . '>' . self::e(t($class::label())) . '</option>';
        }

        $html .= '</select>';

        foreach (TypeRegistry::all() as $handle => $class) {
            $html .= '<div class="mt-3" data-seo-schema-fields="' . self::e($handle) . '"' . ($handle === $selected ? '' : ' hidden') . '>';
            $html .= '<div class="alert alert-warning" data-seo-warning hidden data-seo-message="' . self::e(t('Not output until completed:')) . '"></div>';

            foreach ($class::fields() as $def) {
                $html .= self::field($base . '[data][' . $handle . '][' . $def['name'] . ']', $def, $stored[$handle][$def['name']] ?? null);
            }

            $html .= '</div>';
        }

        return $html;
    }

    public static function field(string $name, array $def, mixed $value): string
    {
        $label = self::e(t($def['label'])) . (!empty($def['required']) && !isset($def['default']) ? ' <span class="text-danger">*</span>' : '');

        if ($def['kind'] === 'list') {
            return '<div class="mb-3"><label class="form-label">' . $label . '</label>' . self::listing($name, $def, is_array($value) ? $value : []) . self::help($def) . '</div>';
        }

        return '<div class="mb-3"><label class="form-label">' . $label . '</label>' . self::input($name, $def, $value) . self::help($def) . '</div>';
    }
}
