<?php

namespace SeoKit\JsonLd\Types;

use SeoKit\JsonLd\SchemaBuilder;

final class ArticleType extends AbstractType
{
    public static function handle(): string
    {
        return 'Article';
    }

    public static function label(): string
    {
        return 'Article / Blog post';
    }

    public static function fields(): array
    {
        return [
            ['name' => 'kind', 'label' => 'Type', 'kind' => 'select', 'options' => ['Article' => 'Article', 'BlogPosting' => 'Blog post', 'NewsArticle' => 'News article']],
            ['name' => 'headline', 'label' => 'Headline', 'kind' => 'text', 'required' => true, 'default' => 'name', 'help' => 'Defaults to the page name.'],
            ['name' => 'description', 'label' => 'Description', 'kind' => 'textarea', 'default' => 'description', 'help' => 'Defaults to the page description.'],
            ['name' => 'image', 'label' => 'Image URL', 'kind' => 'url'],
            ['name' => 'datePublished', 'label' => 'Date published', 'kind' => 'date', 'default' => 'datePublished', 'help' => 'Defaults to the page publish date.'],
            ['name' => 'dateModified', 'label' => 'Date modified', 'kind' => 'date', 'default' => 'dateModified', 'help' => 'Defaults to the page modified date.'],
            ['name' => 'authorName', 'label' => 'Author name', 'kind' => 'text'],
            ['name' => 'authorUrl', 'label' => 'Author URL', 'kind' => 'url'],
        ];
    }

    public static function node(array $data, array $page, array $site): array
    {
        $author = SchemaBuilder::clean(['@type' => 'Person', 'name' => $data['authorName'], 'url' => $data['authorUrl']]);

        return SchemaBuilder::clean(self::base($data['kind'] ?: 'Article', 'article', $page, $site) + [
            'headline' => $data['headline'],
            'description' => $data['description'],
            'image' => $data['image'],
            'datePublished' => $data['datePublished'],
            'dateModified' => $data['dateModified'],
            'author' => isset($author['name']) ? $author : null,
            'publisher' => self::organization($site),
        ]);
    }
}
