<?php

namespace SeoKit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SeoKit\JsonLd\SchemaBuilder;
use SeoKit\JsonLd\Types\{Fields, FormRenderer, TypeRegistry};

final class SchemaTypesBehaviorTest extends TestCase
{
    private function page(): array
    {
        return [
            'url' => 'https://example.com/guide',
            'name' => 'The Guide',
            'description' => 'A helpful guide',
            'datePublished' => '2026-01-02T00:00:00+00:00',
            'dateModified' => '2026-02-03T00:00:00+00:00',
        ];
    }

    private function site(array $override = []): array
    {
        return $override + ['url' => 'https://example.com/', 'disabled' => []];
    }

    /**
     * Posts $data for $type through the same path the editor form uses, then renders the node.
     *
     * @return array{nodes: array<int, array>, errors: array<int, string>}
     */
    private function nodesFor(string $type, array $data, array $site = []): array
    {
        $saved = TypeRegistry::fromPost(['type' => $type, 'data' => [$type => $data]]);
        $this->assertSame([], $saved['errors'], 'save-time errors');

        return TypeRegistry::nodes($saved['json'], $this->page(), $this->site($site));
    }

    // --- Fields -------------------------------------------------------------------------------

    public function testFieldKindsCleanTheirInput(): void
    {
        $defs = [
            ['name' => 'url', 'label' => 'U', 'kind' => 'url'],
            ['name' => 'date', 'label' => 'D', 'kind' => 'date'],
            ['name' => 'dt', 'label' => 'DT', 'kind' => 'datetime'],
            ['name' => 'num', 'label' => 'N', 'kind' => 'number'],
            ['name' => 'sel', 'label' => 'S', 'kind' => 'select', 'options' => ['a' => 'A']],
            ['name' => 'urls', 'label' => 'URLs', 'kind' => 'urls'],
            ['name' => 'lines', 'label' => 'L', 'kind' => 'lines'],
            ['name' => 'text', 'label' => 'T', 'kind' => 'text'],
        ];

        $good = Fields::clean($defs, [
            'url' => 'https://example.com/x', 'date' => '2026-02-28', 'dt' => '2027-01-01T10:30', 'num' => '12.50',
            'sel' => 'a', 'urls' => "https://a.com\nnot a url\njavascript:alert(1)", 'lines' => " one \n\n two ", 'text' => "a\x00b\u{202E}c",
        ]);

        $this->assertSame('https://example.com/x', $good['data']['url']);
        $this->assertSame('2026-02-28', $good['data']['date']);
        $this->assertSame('2027-01-01T10:30', $good['data']['dt']);
        $this->assertSame('12.50', $good['data']['num']);
        $this->assertSame('a', $good['data']['sel']);
        $this->assertSame(['https://a.com'], $good['data']['urls']);
        $this->assertSame(['one', 'two'], $good['data']['lines']);
        $this->assertSame('a b c', $good['data']['text']);

        $bad = Fields::clean($defs, [
            'url' => 'javascript:alert(1)', 'date' => '2026-02-30', 'dt' => 'tomorrow', 'num' => '-5', 'sel' => 'zzz', 'text' => ['array'],
        ]);

        foreach (['url', 'date', 'dt', 'num', 'sel', 'text'] as $name) {
            $this->assertSame('', $bad['data'][$name], $name);
        }
    }

    public function testNumbersAreNonNegativeUnlessTheFieldAllowsARange(): void
    {
        $plain = [['name' => 'n', 'label' => 'N', 'kind' => 'number']];
        $range = [['name' => 'n', 'label' => 'N', 'kind' => 'number', 'min' => -180, 'max' => 180]];

        $this->assertSame('', Fields::clean($plain, ['n' => '-1.5'])['data']['n']);
        $this->assertSame('-1.5', Fields::clean($range, ['n' => '-1.5'])['data']['n']);
        $this->assertSame('', Fields::clean($range, ['n' => '-181'])['data']['n']);
        $this->assertSame('', Fields::clean($range, ['n' => '181'])['data']['n']);
        $this->assertSame('', Fields::clean($plain, ['n' => '1e999'])['data']['n']);
    }

    public function testDefaultsFillBlanksOnlyWhenThePageIsKnownAndSatisfyRequired(): void
    {
        $defs = [['name' => 'name', 'label' => 'Name', 'kind' => 'text', 'required' => true, 'default' => 'name']];

        // at save time the page is unknown: a defaulted field is not required
        $this->assertSame([], Fields::clean($defs, [])['errors']);
        $this->assertSame('', Fields::clean($defs, [])['data']['name']);

        // at render time the default fills it
        $this->assertSame('The Guide', Fields::clean($defs, [], $this->page())['data']['name']);
        $this->assertSame('Mine', Fields::clean($defs, ['name' => 'Mine'], $this->page())['data']['name']);

        // and with no default available it is an error
        $this->assertSame(['Name is required.'], Fields::clean($defs, [], ['name' => ''])['errors']);
    }

    public function testListRowsDropEmptyRowsAndFlagPartialOnes(): void
    {
        $def = ['name' => 'items', 'label' => 'Questions', 'kind' => 'list', 'fields' => [
            ['name' => 'q', 'label' => 'Question', 'kind' => 'text', 'required' => true],
            ['name' => 'a', 'label' => 'Answer', 'kind' => 'text'],
        ]];

        $result = Fields::clean([$def], ['items' => [['q' => 'One', 'a' => 'A'], ['q' => '', 'a' => ''], ['q' => '', 'a' => 'orphan']]]);

        $this->assertCount(2, $result['data']['items']);
        $this->assertSame(['Questions, item 2: Question is required.'], $result['errors']);
    }

    // --- Types --------------------------------------------------------------------------------

    public function testAllSevenTypesAreRegistered(): void
    {
        $this->assertSame(
            ['Article', 'FAQPage', 'Product', 'Event', 'Person', 'LocalBusiness', 'HowTo'],
            array_keys(TypeRegistry::all()),
        );
    }

    public function testArticleUsesPageDefaultsAndLinksToTheWebPageAndOrganization(): void
    {
        $result = $this->nodesFor('Article', ['kind' => 'BlogPosting', 'authorName' => 'Sam', 'authorUrl' => 'https://example.com/sam']);
        $node = $result['nodes'][0];

        $this->assertSame([], $result['errors']);
        $this->assertSame('BlogPosting', $node['@type']);
        $this->assertSame('https://example.com/guide#article', $node['@id']);
        $this->assertSame('The Guide', $node['headline']);
        $this->assertSame('A helpful guide', $node['description']);
        $this->assertSame('2026-01-02T00:00:00+00:00', $node['datePublished']);
        $this->assertSame(['@type' => 'Person', 'name' => 'Sam', 'url' => 'https://example.com/sam'], $node['author']);
        $this->assertSame(['@id' => 'https://example.com/#organization'], $node['publisher']);
        $this->assertSame(['@id' => 'https://example.com/guide#webpage'], $node['mainEntityOfPage']);
    }

    public function testNodesDoNotReferenceSwitchedOffNodes(): void
    {
        $node = $this->nodesFor('Article', [], ['disabled' => ['Organization', 'WebPage']])['nodes'][0];

        $this->assertArrayNotHasKey('publisher', $node);
        $this->assertArrayNotHasKey('mainEntityOfPage', $node);
    }

    public function testFaqBuildsQuestionsAndRequiresAtLeastOne(): void
    {
        $node = $this->nodesFor('FAQPage', ['items' => [['question' => 'Why?', 'answer' => 'Because.'], ['question' => '', 'answer' => '']]])['nodes'][0];

        $this->assertSame('FAQPage', $node['@type']);
        $this->assertCount(1, $node['mainEntity']);
        $this->assertSame('Why?', $node['mainEntity'][0]['name']);
        $this->assertSame('Because.', $node['mainEntity'][0]['acceptedAnswer']['text']);

        $this->assertSame(['Questions is required.'], TypeRegistry::fromPost(['type' => 'FAQPage', 'data' => ['FAQPage' => []]])['errors']);
    }

    public function testProductBuildsAnOfferAndRequiresAPrice(): void
    {
        $node = $this->nodesFor('Product', ['price' => '19.99', 'currency' => 'EUR', 'availability' => 'PreOrder', 'brand' => 'Acme', 'sku' => 'A-1'])['nodes'][0];

        $this->assertSame('Product', $node['@type']);
        $this->assertSame('The Guide', $node['name']);
        $this->assertSame(['@type' => 'Brand', 'name' => 'Acme'], $node['brand']);
        $this->assertSame('19.99', $node['offers']['price']);
        $this->assertSame('EUR', $node['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/PreOrder', $node['offers']['availability']);

        $this->assertSame(['Price is required.'], TypeRegistry::fromPost(['type' => 'Product', 'data' => ['Product' => []]])['errors']);
    }

    public function testEventBuildsLocationsByAttendanceMode(): void
    {
        $base = ['startDate' => '2027-05-01T19:00', 'locationName' => 'Town Hall', 'street' => '1 High St', 'onlineUrl' => 'https://example.com/live'];

        $inPerson = $this->nodesFor('Event', $base)['nodes'][0];
        $this->assertSame('Place', $inPerson['location']['@type']);
        $this->assertSame('1 High St', $inPerson['location']['address']['streetAddress']);
        $this->assertSame('https://schema.org/EventScheduled', $inPerson['eventStatus']);
        $this->assertSame('https://schema.org/OfflineEventAttendanceMode', $inPerson['eventAttendanceMode']);

        $online = $this->nodesFor('Event', $base + ['attendance' => 'OnlineEventAttendanceMode'])['nodes'][0];
        $this->assertSame(['@type' => 'VirtualLocation', 'url' => 'https://example.com/live'], $online['location']);

        $mixed = $this->nodesFor('Event', $base + ['attendance' => 'MixedEventAttendanceMode'])['nodes'][0];
        $this->assertCount(2, $mixed['location']);

        $this->assertSame(['Starts is required.'], TypeRegistry::fromPost(['type' => 'Event', 'data' => ['Event' => []]])['errors']);
    }

    public function testEventTimesGetTheSiteTimezoneOffsetForThatDate(): void
    {
        $data = ['startDate' => '2027-07-01T19:00', 'endDate' => '2027-01-15T22:30'];

        // British Summer Time in July, GMT in January
        $london = $this->nodesFor('Event', $data, ['timezone' => 'Europe/London'])['nodes'][0];
        $this->assertSame('2027-07-01T19:00:00+01:00', $london['startDate']);
        $this->assertSame('2027-01-15T22:30:00+00:00', $london['endDate']);

        $newYork = $this->nodesFor('Event', $data, ['timezone' => 'America/New_York'])['nodes'][0];
        $this->assertSame('2027-07-01T19:00:00-04:00', $newYork['startDate']);

        // no timezone, or one PHP does not know: left as typed
        $this->assertSame('2027-07-01T19:00', $this->nodesFor('Event', $data)['nodes'][0]['startDate']);
        $this->assertSame('2027-07-01T19:00', $this->nodesFor('Event', $data, ['timezone' => 'Not/AZone'])['nodes'][0]['startDate']);
    }

    public function testPersonLinksToTheOrganizationOnlyWhenAsked(): void
    {
        $with = $this->nodesFor('Person', ['jobTitle' => 'Editor', 'sameAs' => "https://twitter.com/x\nbad", 'worksForSite' => '1'])['nodes'][0];
        $this->assertSame(['@id' => 'https://example.com/#organization'], $with['worksFor']);
        $this->assertSame(['https://twitter.com/x'], $with['sameAs']);

        $this->assertArrayNotHasKey('worksFor', $this->nodesFor('Person', [])['nodes'][0]);
    }

    public function testLocalBusinessBuildsAddressGeoAndParentOrganization(): void
    {
        $node = $this->nodesFor('LocalBusiness', [
            'kind' => 'Restaurant', 'street' => '2 Low St', 'locality' => 'Leeds', 'openingHours' => "Mo-Fr 09:00-17:00\nSa 10:00-14:00",
            'latitude' => '53.8', 'longitude' => '-1.55',
        ])['nodes'][0];

        $this->assertSame('Restaurant', $node['@type']);
        $this->assertSame('Leeds', $node['address']['addressLocality']);
        $this->assertCount(2, $node['openingHours']);
        $this->assertSame('53.8', $node['geo']['latitude']);
        $this->assertSame('-1.55', $node['geo']['longitude']);
        $this->assertSame(['@id' => 'https://example.com/#organization'], $node['parentOrganization']);

        $this->assertSame(
            ['Street is required.', 'Town / city is required.'],
            TypeRegistry::fromPost(['type' => 'LocalBusiness', 'data' => ['LocalBusiness' => []]])['errors'],
        );
    }

    public function testHowToBuildsStepsToolsAndAnIsoDuration(): void
    {
        $node = $this->nodesFor('HowTo', [
            'minutes' => '90', 'supplies' => "Paper\nGlue", 'tools' => 'Scissors',
            'steps' => [['name' => 'Cut', 'text' => 'Cut the paper.'], ['name' => '', 'text' => 'Glue it.']],
        ])['nodes'][0];

        $this->assertSame('PT90M', $node['totalTime']);
        $this->assertSame([['@type' => 'HowToSupply', 'name' => 'Paper'], ['@type' => 'HowToSupply', 'name' => 'Glue']], $node['supply']);
        $this->assertSame([['@type' => 'HowToTool', 'name' => 'Scissors']], $node['tool']);
        $this->assertCount(2, $node['step']);
        $this->assertSame('Cut the paper.', $node['step'][0]['text']);
        $this->assertArrayNotHasKey('name', $node['step'][1]);
    }

    // --- Registry -----------------------------------------------------------------------------

    public function testBlankOrUnknownTypeMeansNoSchema(): void
    {
        foreach ([null, [], ['type' => ''], ['type' => 'Nonsense', 'data' => []], ['type' => ['x']]] as $post) {
            $this->assertSame(['json' => '', 'errors' => []], TypeRegistry::fromPost($post), var_export($post, true));
        }

        $this->assertSame(['nodes' => [], 'errors' => []], TypeRegistry::nodes('', $this->page(), $this->site()));
    }

    public function testStoredJsonRoundTripsThroughDecode(): void
    {
        $saved = TypeRegistry::fromPost(['type' => 'Product', 'data' => ['Product' => ['name' => 'Widget', 'price' => '5']]]);
        $decoded = TypeRegistry::decode($saved['json']);

        $this->assertSame('Product', $decoded[0]);
        $this->assertSame('Widget', $decoded[1]['name']);
        $this->assertNull(TypeRegistry::decode('{broken'));
        $this->assertNull(TypeRegistry::decode('{"type":"Nonsense","data":{}}'));
    }

    public function testCorruptStoredJsonGivesNoNodeAndAnError(): void
    {
        $result = TypeRegistry::nodes('{"type":"Nonsense"}', $this->page(), $this->site());

        $this->assertSame([], $result['nodes']);
        $this->assertNotSame([], $result['errors']);
    }

    public function testRequiredFieldMissingAtRenderTimeGivesNoNode(): void
    {
        // an Event stored without a start date (for example from an older version) must not emit an invalid node
        $result = TypeRegistry::nodes('{"type":"Event","data":{"name":"Fete"}}', $this->page(), $this->site());

        $this->assertSame([], $result['nodes']);
        $this->assertSame(['Starts is required.'], $result['errors']);
    }

    public function testTypeNodesStayEscapedWhenRendered(): void
    {
        $node = $this->nodesFor('Article', ['headline' => '</script><script>alert(1)</script>'])['nodes'][0];
        $html = SchemaBuilder::render([$node]);

        $this->assertSame(1, substr_count($html, '</script>'));
        $this->assertStringNotContainsString('<script>alert', $html);
    }

    // --- Form ---------------------------------------------------------------------------------

    public function testFormRendersEveryTypeAndEscapesStoredValues(): void
    {
        $html = FormRenderer::render('akID[7]', 'Article', ['Article' => ['headline' => '"><script>alert(1)</script>']]);

        $this->assertStringContainsString('name="akID[7][type]"', $html);
        $this->assertStringContainsString('<option value="Article" selected>', $html);
        $this->assertStringContainsString('name="akID[7][data][Article][headline]"', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;', $html);

        foreach (array_keys(TypeRegistry::all()) as $handle) {
            $this->assertStringContainsString('data-seo-schema-fields="' . $handle . '"', $html);
        }

        // only the selected type's fields are visible
        $this->assertSame(1, preg_match_all('/data-seo-schema-fields="[A-Za-z]+"(?! hidden)>/', $html));
    }

    public function testFormMarksRequiredFieldsWithoutADefaultForTheWarning(): void
    {
        $html = FormRenderer::render('akID[7]', 'Event', []);

        // Starts is required with no page default; Name is required but defaults from the page
        $this->assertMatchesRegularExpression('/name="akID\[7\]\[data\]\[Event\]\[startDate\]"[^>]*data-seo-required="Starts"/', $html);
        $this->assertStringNotContainsString('data-seo-required="Name"', $html);
        // a required list is marked on its container, and its required sub-fields on each input
        $this->assertStringContainsString('<div data-seo-list data-seo-required="Questions">', $html);
        $this->assertStringContainsString('data-seo-required="Question"', $html);
        $this->assertStringContainsString('data-seo-warning hidden', $html);
    }

    public function testFormListsHaveExistingRowsAndATemplateForNewOnes(): void
    {
        $stored = ['FAQPage' => ['items' => [['question' => 'Q1', 'answer' => 'A1'], ['question' => 'Q2', 'answer' => 'A2']]]];
        $html = FormRenderer::render('akID[7]', 'FAQPage', $stored);

        $this->assertStringContainsString('name="akID[7][data][FAQPage][items][0][question]"', $html);
        $this->assertStringContainsString('name="akID[7][data][FAQPage][items][1][answer]"', $html);
        $this->assertStringContainsString('<template>', $html);
        $this->assertStringContainsString('[items][' . FormRenderer::ROW_INDEX . '][question]', $html);
    }
}
