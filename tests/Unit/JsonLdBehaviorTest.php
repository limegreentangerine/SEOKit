<?php

namespace SeoKit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SeoKit\JsonLd\{CustomJsonLd, SchemaBuilder};

final class JsonLdBehaviorTest extends TestCase
{
    private function site(array $override = []): array
    {
        return $override + [
            'url' => 'https://example.com/',
            'name' => 'Example Ltd',
            'type' => 'LocalBusiness',
            'logo' => 'https://example.com/logo.png',
            'sameAs' => ['https://twitter.com/example'],
            'telephone' => '01234 567890',
            'email' => 'hi@example.com',
            'address' => ['streetAddress' => '1 High St', 'addressLocality' => 'Leeds', 'postalCode' => ''],
            'openingHours' => ['Mo-Fr 09:00-17:00'],
            'disabled' => [],
        ];
    }

    private function page(array $override = []): array
    {
        return $override + [
            'url' => 'https://example.com/about/team',
            'name' => 'Team',
            'description' => 'Meet the team',
            'inLanguage' => 'en-GB',
            'dateModified' => '2026-01-02T03:04:05+00:00',
            'breadcrumbs' => [
                ['name' => 'Home', 'url' => 'https://example.com/'],
                ['name' => 'About', 'url' => 'https://example.com/about'],
                ['name' => 'Team', 'url' => 'https://example.com/about/team'],
            ],
        ];
    }

    private function byType(array $graph): array
    {
        return array_column($graph, null, '@type');
    }

    public function testGraphHasLinkedSiteAndPageNodes(): void
    {
        $nodes = $this->byType(SchemaBuilder::graph($this->site(), $this->page()));

        $this->assertSame(['LocalBusiness', 'WebSite', 'BreadcrumbList', 'WebPage'], array_keys($nodes));
        $this->assertSame('https://example.com/#organization', $nodes['WebSite']['publisher']['@id']);
        $this->assertSame('https://example.com/#website', $nodes['WebPage']['isPartOf']['@id']);
        $this->assertSame('https://example.com/about/team#breadcrumb', $nodes['WebPage']['breadcrumb']['@id']);
        $this->assertSame(3, $nodes['BreadcrumbList']['itemListElement'][2]['position']);
    }

    public function testOrganizationDropsEmptyValuesAndOnlyLocalBusinessGetsOpeningHours(): void
    {
        $local = $this->byType(SchemaBuilder::graph($this->site(), $this->page()))['LocalBusiness'];
        $this->assertSame(['@type' => 'PostalAddress', 'streetAddress' => '1 High St', 'addressLocality' => 'Leeds'], $local['address']);
        $this->assertSame(['Mo-Fr 09:00-17:00'], $local['openingHours']);

        $org = $this->byType(SchemaBuilder::graph($this->site(['type' => 'Organization']), $this->page()))['Organization'];
        $this->assertArrayNotHasKey('openingHours', $org);
    }

    public function testUnknownOrganizationTypeFallsBackToOrganization(): void
    {
        $graph = SchemaBuilder::graph($this->site(['type' => 'Evil']), $this->page());

        $this->assertSame('Organization', $graph[0]['@type']);
    }

    public function testHomePageHasNoBreadcrumb(): void
    {
        $page = $this->page(['url' => 'https://example.com/', 'breadcrumbs' => [['name' => 'Home', 'url' => 'https://example.com/']]]);
        $nodes = $this->byType(SchemaBuilder::graph($this->site(), $page));

        $this->assertArrayNotHasKey('BreadcrumbList', $nodes);
        $this->assertArrayNotHasKey('breadcrumb', $nodes['WebPage']);
    }

    public function testDisabledNodesAreOmittedAndNotReferenced(): void
    {
        $nodes = $this->byType(SchemaBuilder::graph($this->site(['disabled' => ['Organization', 'WebSite']]), $this->page()));

        $this->assertArrayNotHasKey('LocalBusiness', $nodes);
        $this->assertArrayNotHasKey('WebSite', $nodes);
        $this->assertArrayNotHasKey('isPartOf', $nodes['WebPage']);
    }

    public function testCustomNodeWithSameIdReplacesGeneratedNodeAndOthersAreAppended(): void
    {
        $custom = [
            ['@type' => 'WebPage', '@id' => 'https://example.com/about/team#webpage', 'name' => 'Custom'],
            ['@type' => 'FAQPage', 'name' => 'FAQ'],
        ];
        $graph = SchemaBuilder::graph($this->site(), $this->page(), $custom);
        $nodes = $this->byType($graph);

        $this->assertCount(5, $graph);
        $this->assertSame('Custom', $nodes['WebPage']['name']);
        $this->assertArrayNotHasKey('description', $nodes['WebPage']);
        $this->assertSame('FAQPage', $graph[4]['@type']);
    }

    public function testCustomParserAcceptsObjectListAndGraphAndStripsContext(): void
    {
        $single = CustomJsonLd::parse('{"@context":"https://schema.org","@type":"FAQPage"}');
        $this->assertNull($single['error']);
        $this->assertSame([['@type' => 'FAQPage']], $single['nodes']);

        $list = CustomJsonLd::parse('[{"@type":"A"},{"@type":"B"}]');
        $this->assertCount(2, $list['nodes']);

        $graph = CustomJsonLd::parse('{"@context":"https://schema.org","@graph":[{"@type":"A","@context":"x"}]}');
        $this->assertSame([['@type' => 'A']], $graph['nodes']);

        $this->assertSame(['nodes' => [], 'error' => null], CustomJsonLd::parse("  \n"));
    }

    public function testCustomParserRejectsInvalidJsonAndNonObjectNodes(): void
    {
        $invalid = [
            '{not json', '"a string"', '42', '[1, 2]', '[[]]', '[{}]',
            '{}', '[]', '{"@graph":[]}', '{"@context":"https://schema.org"}',
        ];

        foreach ($invalid as $raw) {
            $result = CustomJsonLd::parse($raw);
            $this->assertSame([], $result['nodes'], $raw);
            $this->assertNotNull($result['error'], $raw);
        }
    }

    public function testRenderEscapesScriptBreakoutAndKeepsOneContext(): void
    {
        $graph = [['@type' => 'WebPage', 'name' => '</script><script>alert(1)</script> & <!-- x', 'url' => 'https://example.com/é']];
        $html = SchemaBuilder::render($graph);

        $this->assertSame(1, substr_count($html, '</script>'));
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('</script>', $html);
        $this->assertStringContainsString('https://example.com/é', $html);

        $json = substr($html, strlen('<script type="application/ld+json">'), -strlen('</script>'));
        $decoded = json_decode($json, true);
        $this->assertSame('https://schema.org', $decoded['@context']);
        $this->assertSame($graph[0]['name'], $decoded['@graph'][0]['name']);
    }

    public function testRenderThrowsOnInvalidUtf8SoCallersCanSkipOutput(): void
    {
        $this->expectException(\JsonException::class);

        SchemaBuilder::render([['name' => "bad \xB1 byte"]]);
    }
}
