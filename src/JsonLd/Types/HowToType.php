<?php

namespace SeoKit\JsonLd\Types;

use SeoKit\JsonLd\SchemaBuilder;

final class HowToType extends AbstractType
{
    public static function handle(): string
    {
        return 'HowTo';
    }

    public static function label(): string
    {
        return 'How-to';
    }

    public static function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Title', 'kind' => 'text', 'required' => true, 'default' => 'name'],
            ['name' => 'description', 'label' => 'Description', 'kind' => 'textarea', 'default' => 'description'],
            ['name' => 'image', 'label' => 'Image URL', 'kind' => 'url'],
            ['name' => 'minutes', 'label' => 'Total time (minutes)', 'kind' => 'number'],
            ['name' => 'supplies', 'label' => 'Supplies (one per line)', 'kind' => 'lines'],
            ['name' => 'tools', 'label' => 'Tools (one per line)', 'kind' => 'lines'],
            [
                'name' => 'steps',
                'label' => 'Steps',
                'kind' => 'list',
                'required' => true,
                'fields' => [
                    ['name' => 'name', 'label' => 'Step title', 'kind' => 'text'],
                    ['name' => 'text', 'label' => 'Instructions', 'kind' => 'textarea', 'required' => true],
                ],
            ],
        ];
    }

    public static function node(array $data, array $page, array $site): array
    {
        $named = static fn(string $type): \Closure => static fn(string $name): array => ['@type' => $type, 'name' => $name];

        $steps = array_map(static fn(array $step): array => SchemaBuilder::clean([
            '@type' => 'HowToStep',
            'name' => $step['name'],
            'text' => $step['text'],
        ]), $data['steps']);

        return SchemaBuilder::clean([
            '@type' => 'HowTo',
            '@id' => $page['url'] . '#howto',
            'url' => $page['url'],
            'name' => $data['name'],
            'description' => $data['description'],
            'image' => $data['image'],
            // schema.org durations are ISO 8601, e.g. PT90M
            'totalTime' => $data['minutes'] === '' ? null : 'PT' . (int) round((float) $data['minutes']) . 'M',
            'supply' => array_map($named('HowToSupply'), $data['supplies']),
            'tool' => array_map($named('HowToTool'), $data['tools']),
            'step' => $steps,
        ]);
    }
}
