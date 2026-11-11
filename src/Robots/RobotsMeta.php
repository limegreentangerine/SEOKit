<?php

namespace SeoKit\Robots;

/**
 * Builds the extra robots directives for a page. "noindex" is deliberately absent: core already outputs it
 * from the exclude_search_index attribute, and it also needs to keep the page out of Concrete's own search.
 */
final class RobotsMeta
{
    /**
     * Allowed directives, in the order they are written to the tag.
     */
    public const DIRECTIVES = ['nofollow', 'noarchive', 'nosnippet', 'noimageindex'];

    /**
     * Accepts the select attribute value: an iterable of option objects (getSelectAttributeOptionValue)
     * or plain strings. Unknown values are dropped.
     *
     * @return array<int, string>
     */
    public static function directives(mixed $value): array
    {
        $found = [];

        if (is_iterable($value)) {
            foreach ($value as $option) {
                $name = is_object($option) && method_exists($option, 'getSelectAttributeOptionValue')
                    ? $option->getSelectAttributeOptionValue()
                    : $option;

                if (is_string($name)) {
                    $found[] = strtolower(trim($name));
                }
            }
        }

        return array_values(array_intersect(self::DIRECTIVES, $found));
    }

    /**
     * The <meta> tag, or null when there is nothing to add.
     */
    public static function tag(mixed $value): ?string
    {
        $directives = self::directives($value);

        return $directives === []
            ? null
            : '<meta name="robots" content="' . implode(', ', $directives) . '">';
    }
}
