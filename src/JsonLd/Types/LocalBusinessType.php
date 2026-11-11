<?php

namespace SeoKit\JsonLd\Types;

use SeoKit\JsonLd\SchemaBuilder;

/**
 * A branch or location page. The site-wide business node comes from the settings page; this one describes
 * this page's own location and points back at the organization.
 */
final class LocalBusinessType extends AbstractType
{
    public static function handle(): string
    {
        return 'LocalBusiness';
    }

    public static function label(): string
    {
        return 'Local business (branch / location)';
    }

    public static function fields(): array
    {
        return [
            ['name' => 'kind', 'label' => 'Type', 'kind' => 'select', 'options' => [
                'LocalBusiness' => 'Local business', 'Restaurant' => 'Restaurant', 'Store' => 'Store', 'ProfessionalService' => 'Professional service',
            ]],
            ['name' => 'name', 'label' => 'Name', 'kind' => 'text', 'required' => true, 'default' => 'name'],
            ['name' => 'description', 'label' => 'Description', 'kind' => 'textarea', 'default' => 'description'],
            ['name' => 'telephone', 'label' => 'Telephone', 'kind' => 'text'],
            ['name' => 'street', 'label' => 'Street', 'kind' => 'text', 'required' => true],
            ['name' => 'locality', 'label' => 'Town / city', 'kind' => 'text', 'required' => true],
            ['name' => 'region', 'label' => 'County / region', 'kind' => 'text'],
            ['name' => 'postcode', 'label' => 'Postcode', 'kind' => 'text'],
            ['name' => 'country', 'label' => 'Country', 'kind' => 'text'],
            ['name' => 'openingHours', 'label' => 'Opening hours (one per line)', 'kind' => 'lines', 'help' => 'For example: Mo-Fr 09:00-17:00'],
            ['name' => 'priceRange', 'label' => 'Price range', 'kind' => 'text', 'help' => 'For example: ££'],
            ['name' => 'image', 'label' => 'Image URL', 'kind' => 'url'],
            ['name' => 'latitude', 'label' => 'Latitude', 'kind' => 'number', 'min' => -90, 'max' => 90],
            ['name' => 'longitude', 'label' => 'Longitude', 'kind' => 'number', 'min' => -180, 'max' => 180],
        ];
    }

    public static function node(array $data, array $page, array $site): array
    {
        $hasGeo = $data['latitude'] !== '' && $data['longitude'] !== '';

        return SchemaBuilder::clean([
            '@type' => $data['kind'] ?: 'LocalBusiness',
            '@id' => $page['url'] . '#localbusiness',
            'url' => $page['url'],
            'name' => $data['name'],
            'description' => $data['description'],
            'telephone' => $data['telephone'],
            'address' => self::address([
                'streetAddress' => $data['street'],
                'addressLocality' => $data['locality'],
                'addressRegion' => $data['region'],
                'postalCode' => $data['postcode'],
                'addressCountry' => $data['country'],
            ]),
            'openingHours' => $data['openingHours'],
            'priceRange' => $data['priceRange'],
            'image' => $data['image'],
            'geo' => $hasGeo ? ['@type' => 'GeoCoordinates', 'latitude' => $data['latitude'], 'longitude' => $data['longitude']] : null,
            'parentOrganization' => self::organization($site),
        ]);
    }
}
