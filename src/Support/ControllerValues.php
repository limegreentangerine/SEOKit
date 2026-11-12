<?php

namespace SeoKit\Support;

/**
 * Values a page controller hands to its view with $this->set(), which let a page that shows one record per URL
 * (a single page taking a parameter) describe that record rather than the parent page:
 *
 * - pageTitle, pageDescription: the same variables core reads for the <title> and meta description
 * - pageImage: absolute image URL
 * - ogUrl: absolute URL of the record
 */
final class ControllerValues
{
    /**
     * Keys that must be absolute http(s) URLs.
     */
    protected const URLS = ['pageImage', 'ogUrl'];

    /**
     * Reads from the sets array. It never falls back to the query string, as Controller::get() would, so a
     * visitor cannot supply a value with "?pageTitle=".
     *
     * @param array<string, mixed> $sets
     */
    public static function pick(array $sets, string $key): string
    {
        $value = $sets[$key] ?? null;
        $value = is_string($value) ? trim($value) : '';

        if (in_array($key, self::URLS, true) && !preg_match('#^https?://#i', $value)) {
            return '';
        }

        return $value;
    }

    /**
     * Empty when the page has no controller, the controller did not set the key, or the value is unusable.
     */
    public static function get(object $page, string $key): string
    {
        try {
            $controller = $page->getPageController();

            return is_object($controller) ? self::pick((array) $controller->getSets(), $key) : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
