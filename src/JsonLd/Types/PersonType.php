<?php

namespace SeoKit\JsonLd\Types;

use SeoKit\JsonLd\SchemaBuilder;

final class PersonType extends AbstractType
{
    public static function handle(): string
    {
        return 'Person';
    }

    public static function label(): string
    {
        return 'Person';
    }

    public static function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'kind' => 'text', 'required' => true, 'default' => 'name'],
            ['name' => 'jobTitle', 'label' => 'Job title', 'kind' => 'text'],
            ['name' => 'image', 'label' => 'Image URL', 'kind' => 'url'],
            ['name' => 'sameAs', 'label' => 'Profiles (one URL per line)', 'kind' => 'urls'],
            ['name' => 'worksForSite', 'label' => 'Works for this organization', 'kind' => 'select', 'options' => ['0' => 'No', '1' => 'Yes']],
        ];
    }

    public static function node(array $data, array $page, array $site): array
    {
        return SchemaBuilder::clean([
            '@type' => 'Person',
            '@id' => $page['url'] . '#person',
            'url' => $page['url'],
            'name' => $data['name'],
            'jobTitle' => $data['jobTitle'],
            'image' => $data['image'],
            'sameAs' => $data['sameAs'],
            'worksFor' => $data['worksForSite'] === '1' ? self::organization($site) : null,
        ]);
    }
}
