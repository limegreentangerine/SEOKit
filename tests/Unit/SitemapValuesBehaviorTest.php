<?php

namespace SeoKit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SeoKit\Sitemap\SitemapValues;

final class SitemapValuesBehaviorTest extends TestCase
{
    public function testPriorityIsKeptToOneDecimalInRange(): void
    {
        $this->assertSame('0.8', SitemapValues::priority('0.8'));
        $this->assertSame('1.0', SitemapValues::priority('1'));
        $this->assertSame('0.0', SitemapValues::priority('0'));
        $this->assertSame('0.5', SitemapValues::priority(' .5 '));
        $this->assertSame('0.7', SitemapValues::priority('0.66'));
    }

    public function testBadPrioritiesAreRejected(): void
    {
        foreach (['', 'high', '1.1', '-0.2', '2', '0.5.1', null] as $value) {
            $this->assertNull(SitemapValues::priority($value), var_export($value, true));
        }
    }

    public function testChangeFrequencyMustBeOneFromTheProtocol(): void
    {
        $this->assertSame('weekly', SitemapValues::changeFrequency(' Weekly '));
        $this->assertNull(SitemapValues::changeFrequency('fortnightly'));
        $this->assertNull(SitemapValues::changeFrequency(''));
    }

    public function testPerPageFallsBackToTheDefault(): void
    {
        $this->assertSame(10, SitemapValues::perPage('10'));
        $this->assertSame(100, SitemapValues::perPage(100));

        foreach ([null, '', 'x', 0, 7, 5000, -10] as $value) {
            $this->assertSame(50, SitemapValues::perPage($value), var_export($value, true));
        }
    }

    public function testCountUrlsCountsPagesNotAlternates(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sm');
        file_put_contents($file, "<urlset>\n<url>\n<loc>a</loc>\n<xhtml:link rel=\"alternate\"/>\n</url>\n<url><loc>b</loc></url>\n</urlset>\n");

        $this->assertSame(2, SitemapValues::countUrls($file));
        unlink($file);
        $this->assertSame(0, SitemapValues::countUrls($file));
    }
}
