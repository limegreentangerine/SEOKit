<?php

namespace SeoKit\Tests\Unit;

use SeoKit\Robots\LlmsTxt;
use SeoKit\Robots\RobotsTxt;
use PHPUnit\Framework\TestCase;

final class AiCrawlerBehaviorTest extends TestCase
{
    public function testNothingToSayGivesNoBlock(): void
    {
        $this->assertSame('', RobotsTxt::block([]));
        $this->assertSame('', RobotsTxt::block(['made_up']));
    }

    public function testBlockedBotsGetTheirOwnGroupBetweenMarkers(): void
    {
        $block = RobotsTxt::block(['anthropic_training', 'made_up', 'openai_training']);

        // BOTS order, not the order given
        $this->assertSame(
            "# BEGIN SEO Kit\nUser-agent: GPTBot\nDisallow: /\n\nUser-agent: ClaudeBot\nDisallow: /\n# END SEO Kit\n",
            $block,
        );
    }

    public function testCustomRulesAndSitemapAreAppendedAndCleaned(): void
    {
        $block = RobotsTxt::block([], "Disallow: /private/\r\n\x00\x07Crawl-delay: 5", 'https://example.com/sitemap.xml');

        $this->assertStringContainsString("User-agent: *\nDisallow: /private/\nCrawl-delay: 5\n\nSitemap: https://example.com/sitemap.xml\n# END", $block);
        $this->assertStringNotContainsString("\x00", $block);
    }

    public function testCustomRulesWithTheirOwnUserAgentAreLeftAlone(): void
    {
        $block = RobotsTxt::block(['openai_training'], "user-agent: Foo\nDisallow: /x/");

        $this->assertStringContainsString("Disallow: /\n\nuser-agent: Foo\nDisallow: /x/\n", $block);
        $this->assertStringNotContainsString('User-agent: *', $block);
    }

    public function testBadSitemapUrlIsDropped(): void
    {
        $this->assertSame('', RobotsTxt::block([], '', "javascript:alert(1)\nDisallow: /"));
    }

    public function testMergeCreatesAMissingFileWithTheDashboardRule(): void
    {
        $block = RobotsTxt::block(['openai_training']);

        $this->assertSame($block, RobotsTxt::merge(null, $block));
        $this->assertSame('', RobotsTxt::merge('', ''));
    }

    public function testMergeAppendsAfterAnExistingFileAndKeepsItIntact(): void
    {
        $existing = "User-agent: *\nDisallow: /secret/\n\n# my notes\nDisallow: /x/\n";
        $block = RobotsTxt::block(['openai_training']);

        $merged = RobotsTxt::merge($existing, $block);

        $this->assertSame($existing . "\n" . $block, $merged);
    }

    public function testMergeReplacesItsOwnBlockInPlaceAndIsRepeatable(): void
    {
        $first = RobotsTxt::block(['openai_training']);
        $second = RobotsTxt::block(['perplexity']);
        $file = "User-agent: *\nDisallow: /a/\n\n" . $first . "\n# footer\nSitemap: https://x.test/s.xml\n";

        $merged = RobotsTxt::merge($file, $second);

        $this->assertSame("User-agent: *\nDisallow: /a/\n\n" . $second . "\n# footer\nSitemap: https://x.test/s.xml\n", $merged);
        $this->assertSame($merged, RobotsTxt::merge($merged, $second));
    }

    public function testEmptyBlockRemovesOurSectionOnly(): void
    {
        $file = "User-agent: *\nDisallow: /a/\n\n" . RobotsTxt::block(['openai_training']);

        $this->assertSame("User-agent: *\nDisallow: /a/\n", RobotsTxt::merge($file, ''));
        $this->assertSame("User-agent: *\nDisallow: /a/\n", RobotsTxt::merge("User-agent: *\nDisallow: /a/\n", ''));
    }

    public function testDamagedMarkersAreNotTouched(): void
    {
        $block = RobotsTxt::block(['openai_training']);

        $this->assertNull(RobotsTxt::merge("User-agent: *\n# BEGIN SEO Kit\nDisallow: /\n", $block));
        $this->assertNull(RobotsTxt::merge($block . $block, $block));
    }

    public function testCustomRulesAreCapped(): void
    {
        $this->assertSame(RobotsTxt::CUSTOM_MAX, mb_strlen(RobotsTxt::cleanCustom(str_repeat('a', 9000))));
    }

    public function testEveryBotHasAnAgentAndAUse(): void
    {
        foreach (RobotsTxt::BOTS as $handle => $bot) {
            $this->assertNotSame([], $bot['agents'], $handle);
            $this->assertContains($bot['use'], ['training', 'search', 'user'], $handle);
        }
    }

    public function testLlmsTxtListsPagesWithSafeLinks(): void
    {
        $text = LlmsTxt::build('Acme', "A shop.\nSells <b>things</b>.", [
            ['title' => 'Shop [new]', 'url' => 'https://example.com/shop/', 'description' => "All\nthe <i>things</i>"],
            ['title' => 'Bad', 'url' => 'javascript:alert(1)'],
            ['title' => '', 'url' => 'https://example.com/x/'],
            ['title' => 'Space', 'url' => 'https://example.com/a b/'],
        ]);

        $this->assertSame(
            "# Acme\n\n> A shop. Sells things.\n\n## Pages\n\n- [Shop (new)](https://example.com/shop/): All the things\n",
            $text,
        );
    }

    public function testLlmsTxtWithoutPagesOrIntroIsJustTheTitle(): void
    {
        $this->assertSame("# Website\n", LlmsTxt::build('', '', []));
    }
}
