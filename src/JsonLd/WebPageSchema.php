<?php

namespace SeoKit\JsonLd;

final class WebPageSchema
{
    public static function build(array $site, array $page, bool $withWebSite, bool $withBreadcrumb): array
    {
        return SchemaBuilder::clean([
            '@type' => 'WebPage',
            '@id' => $page['url'] . '#webpage',
            'url' => $page['url'],
            'name' => $page['name'] ?? '',
            'description' => $page['description'] ?? '',
            'inLanguage' => $page['inLanguage'] ?? '',
            'dateModified' => $page['dateModified'] ?? '',
            'isPartOf' => $withWebSite ? ['@id' => WebSiteSchema::id($site)] : null,
            'breadcrumb' => $withBreadcrumb ? ['@id' => BreadcrumbSchema::id($page)] : null,
        ]);
    }
}
