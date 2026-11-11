<?php

namespace SeoKit\Robots;

/**
 * Builds the text of /llms.txt: a Markdown summary of the site for language models.
 */
final class LlmsTxt
{
    public const INTRO_MAX = 1000;

    public const DESCRIPTION_MAX = 160;

    /**
     * Single line of plain text: tags and line breaks removed, runs of whitespace collapsed.
     */
    public static function line(mixed $text, int $max = 0): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $text)) ?? '');

        return $max > 0 ? rtrim(mb_substr($text, 0, $max)) : $text;
    }

    /**
     * @param array<int, array<string, mixed>> $pages each with title, url and optionally description
     */
    public static function build(string $siteName, string $intro, array $pages): string
    {
        $out = '# ' . (self::line($siteName) ?: 'Website') . "\n";

        $intro = self::line($intro, self::INTRO_MAX);

        if ($intro !== '') {
            $out .= "\n> " . $intro . "\n";
        }

        $items = [];

        foreach ($pages as $page) {
            $url = trim((string) ($page['url'] ?? ''));
            $title = self::line($page['title'] ?? '');

            if ($title === '' || !preg_match('#^https?://[^\s()<>]+$#', $url)) {
                continue;
            }

            $description = self::line($page['description'] ?? '', self::DESCRIPTION_MAX);
            // brackets would end the Markdown link early
            $items[] = '- [' . str_replace(['[', ']'], ['(', ')'], $title) . '](' . $url . ')' . ($description === '' ? '' : ': ' . $description);
        }

        if ($items !== []) {
            $out .= "\n## Pages\n\n" . implode("\n", $items) . "\n";
        }

        return $out;
    }
}
