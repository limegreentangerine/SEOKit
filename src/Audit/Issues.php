<?php

namespace SeoKit\Audit;

/**
 * The wording shown for each issue code. Stored findings keep only the code and a short detail, so wording can be
 * improved or translated without touching old audit results.
 */
final class Issues
{
    /**
     * A short name for an issue code, without the per-page detail, for totals.
     */
    public static function summary(string $code): string
    {
        return [
            'title_long' => t('Titles that are too long'),
            'title_short' => t('Titles that are very short'),
            'title_duplicate' => t('Pages sharing a title'),
            'description_missing' => t('Pages with no description'),
            'description_short' => t('Descriptions that are short'),
            'description_long' => t('Descriptions that are too long'),
            'description_duplicate' => t('Pages sharing a description'),
            'noindex_in_sitemap' => t('Noindex pages listed in the sitemap'),
            'no_social_image' => t('Pages with no sharing image'),
            'schema_incomplete' => t('Schema types that are not output'),
            'jsonld_invalid' => t('Custom JSON-LD that is not output'),
        ][$code] ?? $code;
    }

    /**
     * @return array{label: string, advice: string}
     */
    public static function describe(string $code, string $detail = ''): array
    {
        $texts = [
            'title_long' => [t('Title is too long (%s characters)', $detail), t('Aim for about 60 characters or fewer so Google shows all of it. Set a Meta Title on the page.')],
            'title_short' => [t('Title is very short (%s characters)', $detail), t('A fuller title says more about the page. Set a Meta Title on the page.')],
            'title_duplicate' => [t('Same title as %s other page(s)', $detail), t('Give each page its own title so search engines can tell them apart.')],
            'description_missing' => [t('No description'), t('Add a Meta Description or a page description, or Google will choose text from the page.')],
            'description_short' => [t('Description is short (%s characters)', $detail), t('Around 70 to 160 characters usually works well.')],
            'description_long' => [t('Description is too long (%s characters)', $detail), t('Google cuts descriptions at about 160 characters.')],
            'description_duplicate' => [t('Same description as %s other page(s)', $detail), t('Write a description specific to this page.')],
            'noindex_in_sitemap' => [t('Set to noindex but listed in the sitemap'), t('Tick "Exclude From sitemap.xml" on the page, or untick "Exclude From Search Index".')],
            'no_social_image' => [t('No sharing image'), t('Set a Thumbnail on the page, or a Default Sharing Image site attribute.')],
            'schema_incomplete' => [t('Schema type is not output: %s', $detail), t('Fill in the required fields of the Schema attribute, or set it to None.')],
            'jsonld_invalid' => [t('Custom JSON-LD is not output: %s', $detail), t('Fix the JSON in the Custom JSON-LD attribute, or clear it.')],
        ];

        [$label, $advice] = $texts[$code] ?? [$code, ''];

        return ['label' => $label, 'advice' => $advice];
    }
}
