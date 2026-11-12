<?php

namespace SeoKit\JsonLd;

/**
 * Assembles the page's JSON-LD graph from plain data arrays, so it can be tested without Concrete.
 *
 * Site data: url, name, type, logo, sameAs[], telephone, email, address[], openingHours[], disabled[].
 * Page data: url, name, description, inLanguage, dateModified, breadcrumbs[[name, url]].
 */
final class SchemaBuilder
{
    public const CONTEXT = 'https://schema.org';

    /**
     * Node types that can be switched off in settings.
     */
    public const TOGGLEABLE = ['Organization', 'WebSite', 'WebPage', 'BreadcrumbList'];

    /**
     * Drops null, empty-string and empty-array values.
     */
    public static function clean(array $node): array
    {
        return array_filter($node, static fn($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * Generated nodes followed by custom nodes. A custom node with the same id as a generated one
     * replaces it, so an editor can override a generated node deliberately.
     *
     * @param array<int, array> $customNodes
     *
     * @return array<int, array>
     */
    public static function graph(array $site, array $page, array $customNodes = []): array
    {
        $disabled = (array) ($site['disabled'] ?? []);
        $on = static fn(string $type): bool => !in_array($type, $disabled, true);

        $nodes = [];

        if ($on('Organization')) {
            $nodes[] = OrganizationSchema::build($site);
        }

        if ($on('WebSite')) {
            $nodes[] = WebSiteSchema::build($site, $on('Organization'));
        }

        $breadcrumb = $on('BreadcrumbList') ? BreadcrumbSchema::build($page) : null;

        if ($breadcrumb !== null) {
            $nodes[] = $breadcrumb;
        }

        if ($on('WebPage')) {
            $nodes[] = WebPageSchema::build($site, $page, $on('WebSite'), $breadcrumb !== null);
        }

        foreach ($customNodes as $custom) {
            $id = $custom['@id'] ?? null;
            $index = $id === null ? false : array_search($id, array_column($nodes, '@id'), true);

            if ($index === false) {
                $nodes[] = $custom;
            } else {
                $nodes[$index] = $custom;
            }
        }

        return array_values($nodes);
    }

    /**
     * Returns the <script> tag. JSON_HEX_TAG/AMP stop "</script>" or "<!--" in data breaking out of the tag.
     *
     * @throws \JsonException on invalid UTF-8 in the data
     */
    public static function render(array $graph): string
    {
        $json = json_encode(
            ['@context' => self::CONTEXT, '@graph' => $graph],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        return '<script type="application/ld+json">' . $json . '</script>';
    }
}
