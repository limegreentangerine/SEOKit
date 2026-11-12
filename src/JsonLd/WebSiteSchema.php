<?php

namespace SeoKit\JsonLd;

final class WebSiteSchema
{
    public static function id(array $site): string
    {
        return $site['url'] . '#website';
    }

    public static function build(array $site, bool $withPublisher): array
    {
        return SchemaBuilder::clean([
            '@type' => 'WebSite',
            '@id' => self::id($site),
            'url' => $site['url'],
            'name' => $site['name'] ?? '',
            'inLanguage' => $site['inLanguage'] ?? '',
            'publisher' => $withPublisher ? ['@id' => OrganizationSchema::id($site)] : null,
        ]);
    }
}
