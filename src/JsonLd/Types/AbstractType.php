<?php

namespace SeoKit\JsonLd\Types;

use SeoKit\JsonLd\SchemaBuilder;

/**
 * Shared helpers for the schema types.
 */
abstract class AbstractType implements SchemaType
{
    /**
     * @param string $slug fragment for the node's @id, e.g. "article"
     */
    protected static function base(string $type, string $slug, array $page, array $site): array
    {
        $webPageOn = !in_array('WebPage', (array) ($site['disabled'] ?? []), true);

        return [
            '@type' => $type,
            '@id' => $page['url'] . '#' . $slug,
            'mainEntityOfPage' => $webPageOn ? ['@id' => $page['url'] . '#webpage'] : null,
        ];
    }

    /**
     * Reference to the site Organization node, or null when that node is switched off.
     */
    protected static function organization(array $site): ?array
    {
        return in_array('Organization', (array) ($site['disabled'] ?? []), true)
            ? null
            : ['@id' => $site['url'] . '#organization'];
    }

    /**
     * @param array<string, string> $fields name => value, empty values are dropped
     */
    protected static function address(array $fields): ?array
    {
        $address = SchemaBuilder::clean($fields);

        return $address === [] ? null : ['@type' => 'PostalAddress'] + $address;
    }

    protected static function offer(string $price, string $currency, string $url, ?string $availability = null): ?array
    {
        if ($price === '') {
            return null;
        }

        return SchemaBuilder::clean([
            '@type' => 'Offer',
            'price' => $price,
            'priceCurrency' => $currency,
            'url' => $url,
            'availability' => $availability === null || $availability === '' ? null : 'https://schema.org/' . $availability,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected static function currencies(): array
    {
        return ['GBP' => 'GBP (£)', 'EUR' => 'EUR (€)', 'USD' => 'USD ($)'];
    }
}
