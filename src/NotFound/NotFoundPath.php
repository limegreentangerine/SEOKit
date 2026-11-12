<?php

namespace SeoKit\NotFound;

/**
 * Decides which missing addresses are worth logging and cleans them to one form, so /About/ and /about count
 * as one. The form matches the toolkit's redirection paths (lowercase, leading slash, no trailing slash), so a
 * logged path can be used as a redirect source as it is.
 */
final class NotFoundPath
{
    /**
     * Systems and bot probes that are never pages.
     */
    private const IGNORED_PREFIXES = ['/dashboard', '/ccm', '/concrete', '/index.php', '/application', '/packages', '/tools', '/cdn-cgi', '/wp-', '/.well-known'];
    /**
     * Longest path kept. The column is indexed, and MySQL limits index length on older versions.
     */
    public const MAX_LENGTH = 191;

    /**
     * The cleaned path, or null when it is not worth logging: the home page, a file (anything ending in a short
     * extension, such as .js, .png or .php, which are broken assets and bot probes rather than pages), a system
     * path, or something too long or not valid text.
     *
     * shortcut: a page whose last segment ends in ".xx" is not logged; Concrete slugs rarely contain dots, revisit if
     * a site's pages do.
     */
    public static function clean(mixed $raw): ?string
    {
        // checked before parse_url, which would turn a control character into an underscore
        if (!is_string($raw) || $raw === '' || !mb_check_encoding($raw, 'UTF-8') || preg_match('/[\x00-\x1f\x7f]/', $raw)) {
            return null;
        }

        $path = (string) parse_url('http://x' . (str_starts_with($raw, '/') ? $raw : '/' . $raw), PHP_URL_PATH);
        $path = mb_strtolower((string) preg_replace('#/{2,}#', '/', $path));
        $path = rtrim($path, '/');

        if ($path === '' || mb_strlen($path) > self::MAX_LENGTH) {
            return null;
        }

        foreach (self::IGNORED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return null;
            }
        }

        $last = substr($path, (int) strrpos($path, '/') + 1);

        return preg_match('/\.[a-z0-9]{1,5}$/', $last) ? null : $path;
    }

    /**
     * The host name of the referring page only, never its path or query, which can hold personal data.
     */
    public static function referrerHost(mixed $referer): ?string
    {
        if (!is_string($referer) || $referer === '') {
            return null;
        }

        $parts = parse_url($referer);
        $host = is_array($parts) && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) ? strtolower((string) ($parts['host'] ?? '')) : '';

        return $host !== '' && strlen($host) <= 255 && preg_match('/^[a-z0-9.\-]+$/', $host) ? $host : null;
    }
}
