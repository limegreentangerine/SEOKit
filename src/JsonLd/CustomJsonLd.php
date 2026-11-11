<?php

namespace SeoKit\JsonLd;

/**
 * Parses editor-supplied JSON-LD into graph nodes. Invalid input yields an error and no nodes: it is never emitted.
 */
final class CustomJsonLd
{
    /**
     * Accepts a single node, a list of nodes, or an object with a graph list.
     * Any per-node context is dropped; the output graph carries one context.
     *
     * @return array{nodes: array<int, array>, error: ?string}
     */
    public static function parse(string $raw): array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return ['nodes' => [], 'error' => null];
        }

        try {
            $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return ['nodes' => [], 'error' => $e->getMessage()];
        }

        if (!is_array($data)) {
            return ['nodes' => [], 'error' => 'JSON-LD must be an object or a list of objects.'];
        }

        if (!array_is_list($data)) {
            $data = isset($data['@graph']) && is_array($data['@graph']) && array_is_list($data['@graph'])
                ? $data['@graph']
                : [$data];
        }

        // "{}", "[]" and an empty graph list all land here
        if ($data === []) {
            return ['nodes' => [], 'error' => 'JSON-LD is empty.'];
        }

        $nodes = [];

        foreach ($data as $node) {
            if (!is_array($node) || array_is_list($node)) {
                return ['nodes' => [], 'error' => 'Every JSON-LD node must be a non-empty object.'];
            }

            unset($node['@context']);

            // a node that was only a "@context" is empty once that is dropped
            if ($node === []) {
                return ['nodes' => [], 'error' => 'Every JSON-LD node must be a non-empty object.'];
            }

            $nodes[] = $node;
        }

        return ['nodes' => $nodes, 'error' => null];
    }
}
