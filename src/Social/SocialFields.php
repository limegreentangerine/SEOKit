<?php

namespace SeoKit\Social;

/**
 * Resolves a social sharing value (title, description, type) from core's social.opengraph.field_* site config.
 *
 * Core reads these fields but, when one points at a page attribute that is empty, prints no tag at all. Here an
 * empty or missing source falls back to the page's own name or description instead, so every page gets a value.
 * Open Graph and Twitter Cards both resolve through this, so they always agree.
 */
final class SocialFields
{
    /**
     * Attribute values can be objects (files, selects); only text is usable here.
     */
    protected static function scalar(mixed $value): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? trim((string) $value) : '';
    }
    /**
     * @param mixed                   $field     the config value: a literal string, or an array with value_from
     *                                           of page_attribute, page_property or value
     * @param string                  $property  the fallback key in $page ("title", "description" or "type")
     * @param callable(string): mixed $attribute reads a page attribute by handle
     * @param array<string, string>   $page      fallback values by key
     */
    public static function text(mixed $field, string $property, callable $attribute, array $page): string
    {
        $fallback = trim((string) ($page[$property] ?? ''));

        if (is_string($field)) {
            return trim($field) !== '' ? trim($field) : $fallback;
        }

        if (!is_array($field)) {
            return $fallback;
        }

        switch ($field['value_from'] ?? null) {
            case 'page_attribute':
                $value = self::scalar($attribute((string) ($field['attribute'] ?? '')));

                return $value !== '' ? $value : $fallback;
            case 'page_property':
                $value = trim((string) ($page[(string) ($field['property'] ?? $property)] ?? ''));

                return $value !== '' ? $value : $fallback;
            case 'value':
                $value = trim((string) ($field['value'] ?? ''));

                return $value !== '' ? $value : $fallback;
            default:
                return $fallback;
        }
    }

    /**
     * The page attribute handle a field reads from, or null when it uses a page property or a fixed value.
     */
    public static function attributeHandle(mixed $field): ?string
    {
        if (is_array($field) && ($field['value_from'] ?? null) === 'page_attribute' && !empty($field['attribute'])) {
            return (string) $field['attribute'];
        }

        return null;
    }
}
