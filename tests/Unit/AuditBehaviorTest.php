<?php

namespace SeoKit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SeoKit\Audit\{AuditCsv, Checks, Issues, Scorer};

final class AuditBehaviorTest extends TestCase
{
    private function page(array $override = []): array
    {
        return $override + [
            'id' => 1,
            'name' => 'About us',
            'path' => '/about',
            'siteName' => 'Acme',
            'titleFormat' => '%1$s | %2$s',
            'metaTitle' => 'About Acme and the people who run it',
            'metaDescription' => 'Meet the team behind Acme, learn how we work, and find out why customers keep coming back to us.',
            'description' => '',
            'noindex' => false,
            'excludeSitemap' => false,
            'hasSocialImage' => true,
            'schemaErrors' => [],
            'jsonLdError' => null,
        ];
    }

    /**
     * @return array<int, string> codes found for one page
     */
    private function codes(array $pages, int $id = 1): array
    {
        return array_values(array_map(
            static fn(array $f): string => $f['code'],
            array_filter(Checks::run($pages), static fn(array $f): bool => $f['pageId'] === $id),
        ));
    }

    public function testACompletePageHasNoFindings(): void
    {
        $this->assertSame([], Checks::run([$this->page()]));
    }

    public function testEffectiveTitleFollowsCoreRules(): void
    {
        $this->assertSame('About Acme and the people who run it', Checks::effectiveTitle($this->page()));
        $this->assertSame('Acme | About us', Checks::effectiveTitle($this->page(['metaTitle' => '  '])));
        $this->assertSame('Meta', Checks::effectiveDescription($this->page(['metaDescription' => ' Meta ', 'description' => 'Plain'])));
        $this->assertSame('Plain text', Checks::effectiveDescription($this->page(['metaDescription' => '', 'description' => "Plain\n  text"])));
    }

    public function testTitleLengthFindingsUseTheEffectiveTitle(): void
    {
        $this->assertSame(['title_long'], $this->codes([$this->page(['metaTitle' => str_repeat('a', 61)])]));
        $this->assertSame([], $this->codes([$this->page(['metaTitle' => str_repeat('a', 60)])]));
        $this->assertSame(['title_short'], $this->codes([$this->page(['metaTitle' => 'Short'])]));
        // no Meta Title: the page name goes through the site title format ("Acme | About us" = 15 characters)
        $this->assertSame([], $this->codes([$this->page(['metaTitle' => ''])]));
        $this->assertSame(['title_short'], $this->codes([$this->page(['metaTitle' => '', 'name' => 'Hi'])]));
    }

    public function testDescriptionFindingsFallBackToThePageDescription(): void
    {
        $this->assertSame(['description_missing'], $this->codes([$this->page(['metaDescription' => '', 'description' => ''])]));
        $this->assertSame(['description_short'], $this->codes([$this->page(['metaDescription' => 'Too short for a good result.'])]));
        $this->assertSame(['description_long'], $this->codes([$this->page(['metaDescription' => str_repeat('word ', 40)])]));
        $this->assertSame([], $this->codes([$this->page(['metaDescription' => '', 'description' => str_repeat('good ', 20)])]));
    }

    public function testDuplicateTitlesAndDescriptionsAreFlaggedOnEveryPageInTheGroup(): void
    {
        $pages = [
            $this->page(['id' => 1, 'metaTitle' => 'The very same title for two pages', 'metaDescription' => str_repeat('one ', 25)]),
            $this->page(['id' => 2, 'metaTitle' => 'THE VERY  SAME title for two pages', 'metaDescription' => str_repeat('two ', 25)]),
            $this->page(['id' => 3, 'metaTitle' => 'A different title for the third page', 'metaDescription' => str_repeat('one ', 25)]),
        ];

        // page 1 shares its title with page 2 and its description with page 3
        $this->assertSame(['title_duplicate', 'description_duplicate'], $this->codes($pages, 1));
        $this->assertSame(['title_duplicate'], $this->codes($pages, 2));
        $this->assertSame(['description_duplicate'], $this->codes($pages, 3));

        $first = array_values(array_filter(Checks::run($pages), static fn(array $f): bool => $f['pageId'] === 1 && $f['code'] === 'description_duplicate'));
        $this->assertSame('1', $first[0]['detail'], 'detail is how many other pages share it');
    }

    public function testNoindexPagesSkipSearchChecksButNotSitemapOrSchemaChecks(): void
    {
        $bare = ['noindex' => true, 'metaTitle' => 'x', 'metaDescription' => '', 'hasSocialImage' => false, 'excludeSitemap' => true];

        $this->assertSame([], $this->codes([$this->page($bare)]));
        $this->assertSame(['noindex_in_sitemap'], $this->codes([$this->page(['excludeSitemap' => false] + $bare)]));
        $this->assertSame(['schema_incomplete'], $this->codes([$this->page(['schemaErrors' => ['Starts is required.']] + $bare)]));

        // two noindex pages with the same title are not a duplicate problem
        $this->assertSame([], Checks::run([$this->page(['id' => 1] + $bare), $this->page(['id' => 2] + $bare)]));
    }

    public function testSocialImageSchemaAndJsonLdFindings(): void
    {
        $this->assertSame(['no_social_image'], $this->codes([$this->page(['hasSocialImage' => false])]));

        $findings = Checks::run([$this->page(['schemaErrors' => ['Starts is required.', 'Price is required.'], 'jsonLdError' => 'Syntax error'])]);
        $byCode = array_column($findings, null, 'code');

        $this->assertSame('Starts is required.; Price is required.', $byCode['schema_incomplete']['detail']);
        $this->assertSame('Syntax error', $byCode['jsonld_invalid']['detail']);
        $this->assertSame('error', $byCode['jsonld_invalid']['severity']);
    }

    public function testEveryCodeHasASeverityAndWording(): void
    {
        foreach (Checks::SEVERITIES as $code => $severity) {
            $this->assertContains($severity, [Checks::ERROR, Checks::WARNING, Checks::NOTICE], $code);
            $text = Issues::describe($code, '42');
            $this->assertNotSame($code, $text['label'], $code);
            $this->assertNotSame('', $text['advice'], $code);
        }

        $this->assertSame('Title is too long (65 characters)', Issues::describe('title_long', '65')['label']);
    }

    public function testScoringPenalisesBySeverityAndCountsCleanPages(): void
    {
        $findings = [
            ['pageId' => 1, 'severity' => 'error'],
            ['pageId' => 1, 'severity' => 'warning'],
            ['pageId' => 2, 'severity' => 'notice'],
            ['pageId' => 3, 'severity' => 'error'],
            ['pageId' => 3, 'severity' => 'error'],
            ['pageId' => 3, 'severity' => 'error'],
            ['pageId' => 3, 'severity' => 'error'],
            ['pageId' => 3, 'severity' => 'error'],
            ['pageId' => 3, 'severity' => 'error'],
        ];

        $summary = Scorer::summarise(5, $findings);

        $this->assertSame([1 => 70, 2 => 97, 3 => 0], $summary['pageScores'], 'a page never goes below 0');
        $this->assertSame(7, $summary['errors']);
        $this->assertSame(1, $summary['warnings']);
        $this->assertSame(1, $summary['notices']);
        // two clean pages (100 each) + 70 + 97 + 0, over 5 pages
        $this->assertSame((int) round((200 + 70 + 97 + 0) / 5), $summary['score']);
    }

    public function testScoringWithNothingToScan(): void
    {
        $this->assertSame(100, Scorer::summarise(0, [])['score']);
        $this->assertSame(100, Scorer::summarise(10, [])['score']);
    }

    public function testCsvHasAHeaderQuotesValuesAndNeutralisesFormulas(): void
    {
        $csv = AuditCsv::build([
            ['pageName' => '=HYPERLINK("http://evil")', 'pagePath' => '/a,b', 'pageScore' => 70, 'severity' => 'warning', 'code' => 'title_long', 'detail' => '65'],
            ['pageName' => 'Plain "quoted"', 'pagePath' => '/c', 'pageScore' => 100, 'severity' => 'notice', 'code' => 'no_social_image', 'detail' => ''],
        ]);

        $rows = array_map(static fn(string $line): array => str_getcsv($line, ',', '"', ''), array_filter(explode("\n", $csv)));

        $this->assertSame(AuditCsv::HEADER, $rows[0]);
        $this->assertSame("'=HYPERLINK(\"http://evil\")", $rows[1][0]);
        $this->assertSame('/a,b', $rows[1][1]);
        $this->assertSame('Title is too long (65 characters)', $rows[1][4]);
        $this->assertSame('Plain "quoted"', $rows[2][0]);
    }
}
