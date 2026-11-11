<?php

namespace SeoKit\JsonLd\Types;

use SeoKit\JsonLd\SchemaBuilder;

final class FaqType extends AbstractType
{
    public static function handle(): string
    {
        return 'FAQPage';
    }

    public static function label(): string
    {
        return 'FAQ';
    }

    public static function fields(): array
    {
        return [
            [
                'name' => 'items',
                'label' => 'Questions',
                'kind' => 'list',
                'required' => true,
                'help' => 'The answers must also appear on the page.',
                'fields' => [
                    ['name' => 'question', 'label' => 'Question', 'kind' => 'text', 'required' => true],
                    ['name' => 'answer', 'label' => 'Answer', 'kind' => 'textarea', 'required' => true],
                ],
            ],
        ];
    }

    public static function node(array $data, array $page, array $site): array
    {
        $questions = array_map(static fn(array $item): array => [
            '@type' => 'Question',
            'name' => $item['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
        ], $data['items']);

        return SchemaBuilder::clean([
            '@type' => 'FAQPage',
            '@id' => $page['url'] . '#faq',
            'url' => $page['url'],
            'mainEntity' => $questions,
        ]);
    }
}
