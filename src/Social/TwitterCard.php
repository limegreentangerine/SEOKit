<?php

namespace SeoKit\Social;

/**
 * Builds the Twitter / X Card meta tags. Core outputs Open Graph but no Twitter tags.
 */
final class TwitterCard
{
    public const MAX_TITLE = 70;

    public const MAX_DESCRIPTION = 200;

    /**
     * Single-line text: no markup, control characters or runs of whitespace.
     */
    protected static function plain(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '';

        return trim(preg_replace('/\p{C}+/u', '', $text) ?? '');
    }

    protected static function clip(string $text, int $max): string
    {
        return mb_strlen($text) <= $max ? $text : rtrim(mb_substr($text, 0, $max - 1)) . '…';
    }

    /**
     * @return array<string, string> meta tag key (for core's metaTags array) => full <meta> tag, in output order
     */
    public static function tags(string $title, string $description, string $imageUrl): array
    {
        $title = self::clip(self::plain($title), self::MAX_TITLE);

        if ($title === '') {
            return [];
        }

        $image = preg_match('#^https?://#i', $imageUrl) ? $imageUrl : '';

        $values = [
            'twitter:card' => $image === '' ? 'summary' : 'summary_large_image',
            'twitter:title' => $title,
            'twitter:description' => self::clip(self::plain($description), self::MAX_DESCRIPTION),
            'twitter:image' => $image,
        ];

        $tags = [];

        foreach ($values as $name => $content) {
            if ($content !== '') {
                $tags[$name] = '<meta name="' . $name . '" content="' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '">';
            }
        }

        return $tags;
    }
}
