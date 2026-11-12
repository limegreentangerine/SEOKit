<?php

namespace SeoKit\JsonLd\Types;

use SeoKit\JsonLd\SchemaBuilder;

final class ProductType extends AbstractType
{
    public static function handle(): string
    {
        return 'Product';
    }

    public static function label(): string
    {
        return 'Product';
    }

    public static function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'kind' => 'text', 'required' => true, 'default' => 'name'],
            ['name' => 'description', 'label' => 'Description', 'kind' => 'textarea', 'default' => 'description'],
            ['name' => 'image', 'label' => 'Image URL', 'kind' => 'url'],
            ['name' => 'sku', 'label' => 'SKU', 'kind' => 'text'],
            ['name' => 'brand', 'label' => 'Brand', 'kind' => 'text'],
            ['name' => 'price', 'label' => 'Price', 'kind' => 'number', 'required' => true],
            ['name' => 'currency', 'label' => 'Currency', 'kind' => 'select', 'options' => self::currencies()],
            ['name' => 'availability', 'label' => 'Availability', 'kind' => 'select', 'options' => [
                'InStock' => 'In stock', 'OutOfStock' => 'Out of stock', 'PreOrder' => 'Pre-order',
                'BackOrder' => 'Back order', 'Discontinued' => 'Discontinued',
            ]],
        ];
    }

    public static function node(array $data, array $page, array $site): array
    {
        return SchemaBuilder::clean([
            '@type' => 'Product',
            '@id' => $page['url'] . '#product',
            'url' => $page['url'],
            'name' => $data['name'],
            'description' => $data['description'],
            'image' => $data['image'],
            'sku' => $data['sku'],
            'brand' => $data['brand'] === '' ? null : ['@type' => 'Brand', 'name' => $data['brand']],
            'offers' => self::offer($data['price'], $data['currency'] ?: 'GBP', $page['url'], $data['availability'] ?: 'InStock'),
        ]);
    }
}
