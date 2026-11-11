<?php

namespace SeoKit\JsonLd;

final class OrganizationSchema
{
    public const TYPES = ['Organization', 'LocalBusiness'];

    public static function id(array $site): string
    {
        return $site['url'] . '#organization';
    }

    public static function build(array $site): array
    {
        $type = in_array($site['type'] ?? '', self::TYPES, true) ? $site['type'] : 'Organization';
        $address = SchemaBuilder::clean((array) ($site['address'] ?? []));
        $logo = (string) ($site['logo'] ?? '');

        return SchemaBuilder::clean([
            '@type' => $type,
            '@id' => self::id($site),
            'name' => $site['name'] ?? '',
            'url' => $site['url'],
            'logo' => $logo === '' ? null : ['@type' => 'ImageObject', 'url' => $logo],
            'image' => $logo,
            'telephone' => $site['telephone'] ?? '',
            'email' => $site['email'] ?? '',
            'address' => $address === [] ? null : ['@type' => 'PostalAddress'] + $address,
            'sameAs' => array_values((array) ($site['sameAs'] ?? [])),
            // openingHours is only meaningful on a LocalBusiness
            'openingHours' => $type === 'LocalBusiness' ? array_values((array) ($site['openingHours'] ?? [])) : [],
        ]);
    }
}
