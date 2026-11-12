<?php

namespace SeoKit\Audit;

/**
 * The audit rules, applied to plain page data so they can be tested without Concrete.
 *
 * A page is: id, name, path, siteName, titleFormat, metaTitle, metaDescription, description, noindex,
 * excludeSitemap, hasSocialImage, schemaErrors (list of strings), jsonLdError (string or null).
 */
final class Checks
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    public const NOTICE = 'notice';

    /**
     * Rough Google desktop limits, in characters (see the preview attribute for the pixel version).
     */
    public const TITLE_MAX = 60;

    public const TITLE_MIN = 15;

    public const DESCRIPTION_MAX = 160;

    public const DESCRIPTION_MIN = 70;

    /**
     * Issue code => severity.
     */
    public const SEVERITIES = [
        'title_long' => self::WARNING,
        'title_short' => self::NOTICE,
        'title_duplicate' => self::WARNING,
        'description_missing' => self::WARNING,
        'description_short' => self::NOTICE,
        'description_long' => self::WARNING,
        'description_duplicate' => self::WARNING,
        'noindex_in_sitemap' => self::WARNING,
        'no_social_image' => self::NOTICE,
        'schema_incomplete' => self::ERROR,
        'jsonld_invalid' => self::ERROR,
    ];

    /**
     * Single-line, trimmed text.
     */
    protected static function text(mixed $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
    }

    /**
     * Case and whitespace do not make two titles or descriptions different.
     */
    protected static function key(string $text): string
    {
        return mb_strtolower($text);
    }

    /**
     * Core's rule: a Meta Title is used as is, otherwise the page name goes through the site title format.
     */
    public static function effectiveTitle(array $page): string
    {
        $meta = self::text($page['metaTitle'] ?? '');

        return $meta !== '' ? $meta : self::text(str_replace(
            ['%1$s', '%2$s'],
            [(string) ($page['siteName'] ?? ''), (string) ($page['name'] ?? '')],
            (string) ($page['titleFormat'] ?? '%2$s'),
        ));
    }

    public static function effectiveDescription(array $page): string
    {
        $meta = self::text($page['metaDescription'] ?? '');

        return $meta !== '' ? $meta : self::text($page['description'] ?? '');
    }

    /**
     * Every finding for the given pages.
     *
     * Pages marked noindex are left out of the title, description, duplicate and social image checks, since they
     * are not meant to appear in search; the structured data and sitemap checks still apply to them.
     *
     * @param array<int, array> $pages
     *
     * @return array<int, array{pageId: int, code: string, severity: string, detail: string}>
     */
    public static function run(array $pages): array
    {
        $findings = [];
        $titles = [];
        $descriptions = [];

        foreach ($pages as $page) {
            $id = (int) $page['id'];
            $add = static function (string $code, string $detail = '') use (&$findings, $id): void {
                $findings[] = ['pageId' => $id, 'code' => $code, 'severity' => self::SEVERITIES[$code], 'detail' => $detail];
            };

            if (!empty($page['noindex'])) {
                if (empty($page['excludeSitemap'])) {
                    $add('noindex_in_sitemap');
                }
            } else {
                $title = self::effectiveTitle($page);
                $description = self::effectiveDescription($page);
                $titleLength = mb_strlen($title);
                $descriptionLength = mb_strlen($description);

                if ($titleLength > self::TITLE_MAX) {
                    $add('title_long', (string) $titleLength);
                } elseif ($titleLength < self::TITLE_MIN) {
                    $add('title_short', (string) $titleLength);
                }

                if ($descriptionLength === 0) {
                    $add('description_missing');
                } elseif ($descriptionLength < self::DESCRIPTION_MIN) {
                    $add('description_short', (string) $descriptionLength);
                } elseif ($descriptionLength > self::DESCRIPTION_MAX) {
                    $add('description_long', (string) $descriptionLength);
                }

                if (empty($page['hasSocialImage'])) {
                    $add('no_social_image');
                }

                if ($title !== '') {
                    $titles[self::key($title)][] = $id;
                }

                if ($description !== '') {
                    $descriptions[self::key($description)][] = $id;
                }
            }

            if (!empty($page['schemaErrors'])) {
                $add('schema_incomplete', implode('; ', (array) $page['schemaErrors']));
            }

            if (($page['jsonLdError'] ?? null) !== null && $page['jsonLdError'] !== '') {
                $add('jsonld_invalid', (string) $page['jsonLdError']);
            }
        }

        foreach ([['title_duplicate', $titles], ['description_duplicate', $descriptions]] as [$code, $groups]) {
            foreach ($groups as $ids) {
                if (count($ids) < 2) {
                    continue;
                }

                foreach ($ids as $id) {
                    $findings[] = ['pageId' => $id, 'code' => $code, 'severity' => self::SEVERITIES[$code], 'detail' => (string) (count($ids) - 1)];
                }
            }
        }

        return $findings;
    }
}
