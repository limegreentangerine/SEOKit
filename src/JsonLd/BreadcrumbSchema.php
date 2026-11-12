<?php

namespace SeoKit\JsonLd;

final class BreadcrumbSchema
{
    public static function id(array $page): string
    {
        return $page['url'] . '#breadcrumb';
    }

    /**
     * Null when there are fewer than two crumbs (the home page has no trail worth marking up).
     */
    public static function build(array $page): ?array
    {
        $items = [];

        foreach ((array) ($page['breadcrumbs'] ?? []) as $crumb) {
            if (($crumb['name'] ?? '') === '' || ($crumb['url'] ?? '') === '') {
                continue;
            }

            $items[] = [
                '@type' => 'ListItem',
                'position' => count($items) + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ];
        }

        if (count($items) < 2) {
            return null;
        }

        return [
            '@type' => 'BreadcrumbList',
            '@id' => self::id($page),
            'itemListElement' => $items,
        ];
    }
}
