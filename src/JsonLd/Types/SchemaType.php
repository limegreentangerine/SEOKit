<?php

namespace SeoKit\JsonLd\Types;

/**
 * A page-level schema type an editor can choose. Fields drive both the editor form and the cleaning of stored data.
 */
interface SchemaType
{
    public static function handle(): string;

    public static function label(): string;

    /**
     * Field definitions (see Fields).
     *
     * @return array<int, array>
     */
    public static function fields(): array;

    /**
     * Builds the graph node from cleaned data.
     *
     * @param array $data cleaned field values
     * @param array $page page data as built by SchemaBuilder (url, name, description, ...)
     * @param array $site site data (url, disabled[], ...)
     */
    public static function node(array $data, array $page, array $site): array;
}
