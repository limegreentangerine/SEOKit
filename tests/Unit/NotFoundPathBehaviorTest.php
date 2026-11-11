<?php

namespace SeoKit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SeoKit\NotFound\NotFoundPath;

final class NotFoundPathBehaviorTest extends TestCase
{
    public function testPathsAreCleanedToOneForm(): void
    {
        $this->assertSame('/about-us', NotFoundPath::clean('/About-Us/'));
        $this->assertSame('/a/b', NotFoundPath::clean('//a///b?x=1#top'));
        $this->assertSame('/old-page', NotFoundPath::clean('old-page'));
    }

    public function testPathsThatAreNotPagesAreNotLogged(): void
    {
        foreach (['/', '', '/logo.png', '/js/app.min.js', '/wp-login.php', '/.env', '/dashboard/x', '/index.php/x', '/ccm/a', '/wp-admin/x', '/a/b.php', null, 42] as $raw) {
            $this->assertNull(NotFoundPath::clean($raw), var_export($raw, true));
        }
    }

    public function testDotsInTheMiddleOrLongSuffixesAreKept(): void
    {
        $this->assertSame('/v1.2/guide', NotFoundPath::clean('/v1.2/guide'));
        $this->assertSame('/file.longextension', NotFoundPath::clean('/file.longextension'));
    }

    public function testControlCharactersBadEncodingAndLongPathsAreRejected(): void
    {
        $this->assertNull(NotFoundPath::clean("/a\x00b"));
        $this->assertNull(NotFoundPath::clean("/a\xFF"));
        $this->assertNull(NotFoundPath::clean('/' . str_repeat('a', NotFoundPath::MAX_LENGTH)));
        $this->assertSame(NotFoundPath::MAX_LENGTH, mb_strlen((string) NotFoundPath::clean('/' . str_repeat('a', NotFoundPath::MAX_LENGTH - 1))));
    }

    public function testReferrerKeepsOnlyTheHost(): void
    {
        $this->assertSame('example.com', NotFoundPath::referrerHost('https://Example.com/path?email=a@b.c#x'));
        $this->assertSame('sub.example.co.uk', NotFoundPath::referrerHost('http://sub.example.co.uk:8080/'));

        foreach ([null, '', 'not a url', 'javascript:alert(1)', 'ftp://example.com/', 'https://exa mple.com/', 'https:///path'] as $raw) {
            $this->assertNull(NotFoundPath::referrerHost($raw), var_export($raw, true));
        }
    }
}
