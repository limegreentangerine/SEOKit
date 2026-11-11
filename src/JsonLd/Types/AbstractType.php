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
     * A local date and time as an editor typed it ("2027-05-01T19:00") with the offset that applies on that date
     * in the site's timezone ("2027-05-01T19:00:00+01:00"), which is what Google asks for. Left as typed when the
     * site has no usable timezone.
     */
    protected static function withOffset(string $local, array $site): string
    {
        $timezone = (string) ($site['timezone'] ?? '');

        if ($local === '' || $timezone === '') {
            return $local;
        }

        try {
            return (new \DateTimeImmutable($local, new \DateTimeZone($timezone)))->format(DATE_ATOM);
        } catch (\Throwable) {
            return $local;
        }
    }

    /**
     * @return array<string, string>
     */
    protected static function currencies(): array
    {
        return ['GBP' => 'GBP (£)', 'EUR' => 'EUR (€)', 'USD' => 'USD ($)'];
    }
}
