<?php

namespace SeoKit\Social;

/**
 * Builds the Twitter / X Card meta tags. Core outputs Open Graph but no Twitter tags.
 */
final class TwitterCard
{
    /**
     * Paths on twitter.com / x.com that are not account names.
     */
    protected const NOT_ACCOUNTS = ['intent', 'share', 'home', 'i', 'search', 'hashtag', 'explore', 'settings'];
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
     * @param string $site the account handle, with or without "@"; omitted when empty or not a valid handle
     *
     * @return array<string, string> meta tag key (for core's metaTags array) => full <meta> tag, in output order
     */
    public static function tags(string $title, string $description, string $imageUrl, string $site = ''): array
    {
        $title = self::clip(self::plain($title), self::MAX_TITLE);

        if ($title === '') {
            return [];
        }

        $image = preg_match('#^https?://#i', $imageUrl) ? $imageUrl : '';
        $handle = ltrim(trim($site), '@');

        $values = [
            'twitter:card' => $image === '' ? 'summary' : 'summary_large_image',
            'twitter:site' => preg_match('/^[A-Za-z0-9_]{1,15}$/', $handle) ? '@' . $handle : '',
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

    /**
     * The account handle from a profile URL such as https://x.com/acme or https://twitter.com/@acme/, or an empty
     * string when the URL is not a profile.
     */
    public static function handleFromUrl(string $url): string
    {
        $url = trim($url);

        if (preg_match('/^@?([A-Za-z0-9_]{1,15})$/', $url, $m)) {
            return $m[1];
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (!in_array($host, ['twitter.com', 'www.twitter.com', 'x.com', 'www.x.com', 'mobile.twitter.com'], true)) {
            return '';
        }

        $segment = ltrim(explode('/', trim((string) ($parts['path'] ?? ''), '/'))[0], '@');

        if (in_array(strtolower($segment), self::NOT_ACCOUNTS, true) || !preg_match('/^[A-Za-z0-9_]{1,15}$/', $segment)) {
            return '';
        }

        return $segment;
    }
}
