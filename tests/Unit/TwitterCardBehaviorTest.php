<?php

namespace SeoKit\Tests\Unit;

use SeoKit\Support\Files;
use SeoKit\Social\TwitterCard;
use PHPUnit\Framework\TestCase;

final class TwitterCardBehaviorTest extends TestCase
{
    public function testWithAnImageItIsALargeImageCard(): void
    {
        $tags = TwitterCard::tags('A title', 'A description', 'https://example.com/a.jpg');

        $this->assertSame(['twitter:card', 'twitter:title', 'twitter:description', 'twitter:image'], array_keys($tags));
        $this->assertSame('<meta name="twitter:card" content="summary_large_image">', $tags['twitter:card']);
        $this->assertSame('<meta name="twitter:image" content="https://example.com/a.jpg">', $tags['twitter:image']);
    }

    public function testWithoutAnImageItIsASummaryCardAndOmitsEmptyTags(): void
    {
        $tags = TwitterCard::tags('A title', '', '');

        $this->assertSame(['twitter:card', 'twitter:title'], array_keys($tags));
        $this->assertSame('<meta name="twitter:card" content="summary">', $tags['twitter:card']);
    }

    public function testNoTitleMeansNoTags(): void
    {
        $this->assertSame([], TwitterCard::tags('  ', 'desc', 'https://example.com/a.jpg'));
    }

    public function testValuesAreEscapedStrippedAndClipped(): void
    {
        $tags = TwitterCard::tags('"><script>alert(1)</script> Tom & Jerry', "Line one\n\n  <b>two</b>\x00", 'javascript:alert(1)');

        $this->assertStringNotContainsString('<script>', implode('', $tags));
        $this->assertSame('<meta name="twitter:title" content="&quot;&gt;alert(1) Tom &amp; Jerry">', $tags['twitter:title']);
        $this->assertSame('<meta name="twitter:description" content="Line one two">', $tags['twitter:description']);
        $this->assertSame('<meta name="twitter:card" content="summary">', $tags['twitter:card']);
        $this->assertArrayNotHasKey('twitter:image', $tags);

        $long = TwitterCard::tags(str_repeat('é', 100), str_repeat('word ', 100), '');
        $content = static fn(string $tag): string => html_entity_decode(preg_match('/content="(.*)">/', $tag, $m) ? $m[1] : '');

        $this->assertSame(TwitterCard::MAX_TITLE, mb_strlen($content($long['twitter:title'])));
        $this->assertStringEndsWith('…', $content($long['twitter:title']));
        $this->assertLessThanOrEqual(TwitterCard::MAX_DESCRIPTION, mb_strlen($content($long['twitter:description'])));
    }

    public function testFilesUrlAcceptsAFileEntityItsVersionOrNothing(): void
    {
        $version = new class {
            public function getURL(): string
            {
                return 'https://example.com/f.png';
            }
        };
        $file = new class($version) {
            public function __construct(private object $version) {}

            public function getApprovedVersion(): object
            {
                return $this->version;
            }
        };

        $this->assertSame('https://example.com/f.png', Files::url($file));
        $this->assertSame('https://example.com/f.png', Files::url($version));
        $this->assertSame('', Files::url(null));
        $this->assertSame('', Files::url('not a file'));
    }
}
