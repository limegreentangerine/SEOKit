<?php

namespace SeoKit\Robots;

/**
 * Builds the text of /robots.txt. robots.txt is a request that well-behaved crawlers follow, not enforcement.
 */
final class RobotsTxt
{
    public const CUSTOM_MAX = 5000;

    /**
     * Known AI crawlers, keyed by a stable handle. "use" says what the crawler is for, because blocking
     * training does not stop an assistant fetching a page a person asked about.
     *
     * @var array<string, array{label: string, agents: array<int, string>, use: string}>
     */
    public const BOTS = [
        'openai_training' => ['label' => 'OpenAI GPTBot', 'agents' => ['GPTBot'], 'use' => 'training'],
        'openai_search' => ['label' => 'OpenAI search (OAI-SearchBot)', 'agents' => ['OAI-SearchBot'], 'use' => 'search'],
        'openai_user' => ['label' => 'ChatGPT user requests', 'agents' => ['ChatGPT-User'], 'use' => 'user'],
        'anthropic_training' => ['label' => 'Anthropic ClaudeBot', 'agents' => ['ClaudeBot'], 'use' => 'training'],
        'anthropic_search' => ['label' => 'Anthropic search (Claude-SearchBot)', 'agents' => ['Claude-SearchBot'], 'use' => 'search'],
        'anthropic_user' => ['label' => 'Claude user requests', 'agents' => ['Claude-User'], 'use' => 'user'],
        'google_extended' => ['label' => 'Google-Extended (Gemini training)', 'agents' => ['Google-Extended'], 'use' => 'training'],
        'apple_extended' => ['label' => 'Applebot-Extended (Apple AI training)', 'agents' => ['Applebot-Extended'], 'use' => 'training'],
        'perplexity' => ['label' => 'PerplexityBot', 'agents' => ['PerplexityBot'], 'use' => 'search'],
        'perplexity_user' => ['label' => 'Perplexity user requests', 'agents' => ['Perplexity-User'], 'use' => 'user'],
        'common_crawl' => ['label' => 'Common Crawl (CCBot)', 'agents' => ['CCBot'], 'use' => 'training'],
        'meta' => ['label' => 'Meta AI (meta-externalagent)', 'agents' => ['meta-externalagent'], 'use' => 'training'],
        'bytedance' => ['label' => 'ByteDance (Bytespider)', 'agents' => ['Bytespider'], 'use' => 'training'],
        'amazon' => ['label' => 'Amazonbot', 'agents' => ['Amazonbot'], 'use' => 'search'],
    ];

    /**
     * Blocked until the editor saves their own choice.
     */
    public const DEFAULT_BLOCKED = ['openai_training', 'anthropic_training', 'google_extended', 'apple_extended', 'common_crawl', 'meta', 'bytedance', 'amazon'];

    /**
     * Custom rules until the editor saves their own: keeps crawlers out of Concrete's system folders.
     */
    public const DEFAULT_CUSTOM = "User-agent: *\nDisallow: /dashboard/\nDisallow: /application/attributes\nDisallow: /application/authentication\nDisallow: /application/bootstrap\nDisallow: /application/config\nDisallow: /application/controllers\nDisallow: /application/elements\nDisallow: /application/jobs\nDisallow: /application/languages\nDisallow: /application/mail\nDisallow: /application/page_types\nDisallow: /application/page_templates\nDisallow: /application/single_pages\nDisallow: /application/tools\nDisallow: /application/views\nDisallow: /ccm/system/captcha/picture\nDisallow: /concrete\nDisallow: /packages\nDisallow: /updates";

    public const BEGIN = '# BEGIN SEO Kit';

    public const END = '# END SEO Kit';

    /**
     * Keeps only handles from BOTS, in the order BOTS lists them.
     *
     * @return array<int, string>
     */
    public static function knownBots(mixed $handles): array
    {
        $handles = is_array($handles) ? $handles : [];

        return array_values(array_filter(array_keys(self::BOTS), static fn(string $handle) => in_array($handle, $handles, true)));
    }

    /**
     * Plain text for the custom rules box: Unix newlines, no control characters, capped length.
     */
    public static function cleanCustom(mixed $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", (string) $text);
        $text = preg_replace('/[^\P{C}\n\t]/u', '', $text) ?? '';

        return trim(mb_substr($text, 0, self::CUSTOM_MAX));
    }

    /**
     * The block SEO Kit manages: a group per blocked bot, the custom rules and the Sitemap line, between markers.
     * Empty when there is nothing to say.
     *
     * @param array<int, string> $blocked    handles from BOTS to block from the whole site
     * @param string|null        $sitemapUrl added as a Sitemap line when given
     */
    public static function block(array $blocked, string $custom = '', ?string $sitemapUrl = null): string
    {
        $parts = [];

        foreach (self::knownBots($blocked) as $handle) {
            $group = array_map(static fn(string $agent) => 'User-agent: ' . $agent, self::BOTS[$handle]['agents']);
            $group[] = 'Disallow: /';
            $parts[] = implode("\n", $group);
        }

        $custom = self::cleanCustom($custom);

        if ($custom !== '') {
            // a rule with no User-agent line would join the bot group above it, whatever blank lines say
            $parts[] = preg_match('/^\s*user-agent\s*:/im', $custom) ? $custom : "User-agent: *\n" . $custom;
        }

        if ($sitemapUrl !== null && preg_match('#^https?://\S+$#', $sitemapUrl)) {
            $parts[] = 'Sitemap: ' . $sitemapUrl;
        }

        return $parts === [] ? '' : self::BEGIN . "\n" . implode("\n\n", $parts) . "\n" . self::END . "\n";
    }

    /**
     * Puts the block into the existing file text: replaces our earlier block, or appends one after the rest.
     * Everything outside the markers is kept byte for byte. An empty block removes ours.
     * Returns null when the markers are damaged (one without the other), so the caller leaves the file alone.
     */
    public static function merge(?string $existing, string $block): ?string
    {
        if ($existing === null || trim($existing) === '') {
            return $block;
        }

        $begin = substr_count($existing, self::BEGIN);
        $end = substr_count($existing, self::END);

        if ($begin !== $end || $begin > 1) {
            return null;
        }

        if ($begin === 1) {
            $pattern = '/\R?' . preg_quote(self::BEGIN, '/') . '.*?' . preg_quote(self::END, '/') . '\R?/s';
            $without = preg_replace($pattern, '', $existing, 1);

            if ($block === '') {
                return $without;
            }

            // keep the block where it was
            $before = substr($existing, 0, strpos($existing, self::BEGIN));
            $afterStart = strpos($existing, self::END) + strlen(self::END);

            return $before . rtrim($block, "\n") . substr($existing, $afterStart);
        }

        return $block === '' ? $existing : rtrim($existing, "\n") . "\n\n" . $block;
    }
}
