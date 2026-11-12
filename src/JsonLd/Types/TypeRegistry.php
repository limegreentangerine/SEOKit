<?php

namespace SeoKit\JsonLd\Types;

/**
 * The available schema types, and the conversion between posted form data, stored JSON and graph nodes.
 *
 * Stored JSON: {"type": "FAQPage", "data": {...cleaned fields of that type...}}
 */
final class TypeRegistry
{
    /**
     * @var array<int, class-string<SchemaType>>
     */
    public const TYPES = [
        ArticleType::class,
        FaqType::class,
        ProductType::class,
        EventType::class,
        PersonType::class,
        LocalBusinessType::class,
        HowToType::class,
    ];

    /**
     * @return array<string, class-string<SchemaType>> handle => class
     */
    public static function all(): array
    {
        $types = [];

        foreach (self::TYPES as $class) {
            $types[$class::handle()] = $class;
        }

        return $types;
    }

    /**
     * Turns the posted form (["type" => handle, "data" => [handle => [field => value]]]) into stored JSON.
     * A blank or unknown type means "no schema" and gives an empty string.
     *
     * @return array{json: string, errors: array<int, string>}
     */
    public static function fromPost(mixed $post): array
    {
        $post = is_array($post) ? $post : [];
        $handle = is_string($post['type'] ?? null) ? $post['type'] : '';
        $class = self::all()[$handle] ?? null;

        if ($class === null) {
            return ['json' => '', 'errors' => []];
        }

        $raw = is_array($post['data'] ?? null) ? ($post['data'][$handle] ?? []) : [];
        $cleaned = Fields::clean($class::fields(), $raw);

        return [
            'json' => json_encode(['type' => $handle, 'data' => $cleaned['data']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
            'errors' => $cleaned['errors'],
        ];
    }

    /**
     * Decodes stored JSON for the editor form: [handle, data], or null when empty or unreadable.
     *
     * @return array{0: string, 1: array}|null
     */
    public static function decode(string $json): ?array
    {
        $stored = json_decode($json, true);

        if (!is_array($stored) || !is_string($stored['type'] ?? null) || !isset(self::all()[$stored['type']])) {
            return null;
        }

        return [$stored['type'], is_array($stored['data'] ?? null) ? $stored['data'] : []];
    }

    /**
     * Graph nodes for stored JSON on a page. Errors (a field that became required, bad JSON) give no node.
     *
     * @return array{nodes: array<int, array>, errors: array<int, string>}
     */
    public static function nodes(string $json, array $page, array $site): array
    {
        if (trim($json) === '') {
            return ['nodes' => [], 'errors' => []];
        }

        $decoded = self::decode($json);

        if ($decoded === null) {
            return ['nodes' => [], 'errors' => ['Stored schema data is not valid.']];
        }

        [$handle, $data] = $decoded;
        $class = self::all()[$handle];
        $cleaned = Fields::clean($class::fields(), $data, $page);

        if ($cleaned['errors'] !== []) {
            return ['nodes' => [], 'errors' => $cleaned['errors']];
        }

        return ['nodes' => [$class::node($cleaned['data'], $page, $site)], 'errors' => []];
    }
}
