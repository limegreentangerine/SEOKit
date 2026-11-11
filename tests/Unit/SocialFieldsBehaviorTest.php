<?php

namespace SeoKit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SeoKit\Social\{SocialFields, TwitterCard};

final class SocialFieldsBehaviorTest extends TestCase
{
    private const PAGE = ['title' => 'About us', 'description' => 'Who we are', 'type' => 'website'];

    private function attributes(array $values): callable
    {
        return static fn(string $handle): mixed => $values[$handle] ?? null;
    }

    public function testStockCoreConfigUsesPageProperties(): void
    {
        $title = ['value_from' => 'page_property', 'property' => 'title'];
        $description = ['value_from' => 'page_property', 'property' => 'description'];

        $this->assertSame('About us', SocialFields::text($title, 'title', $this->attributes([]), self::PAGE));
        $this->assertSame('Who we are', SocialFields::text($description, 'description', $this->attributes([]), self::PAGE));
    }

    public function testAnAttributeFieldUsesTheAttributeAndFallsBackWhenItIsEmpty(): void
    {
        $field = ['value_from' => 'page_attribute', 'attribute' => 'meta_title'];

        $this->assertSame('A better title', SocialFields::text($field, 'title', $this->attributes(['meta_title' => '  A better title ']), self::PAGE));
        // core would print no tag here; we fall back to the page name
        $this->assertSame('About us', SocialFields::text($field, 'title', $this->attributes(['meta_title' => '']), self::PAGE));
        $this->assertSame('About us', SocialFields::text($field, 'title', $this->attributes([]), self::PAGE));
        // object values (a file, a select) are not text
        $this->assertSame('About us', SocialFields::text($field, 'title', $this->attributes(['meta_title' => new \stdClass()]), self::PAGE));
    }

    public function testAFallbackIsTheRequestedPropertyNotWhateverTheConfigNames(): void
    {
        $description = ['value_from' => 'page_attribute', 'attribute' => 'meta_description'];

        $this->assertSame('Who we are', SocialFields::text($description, 'description', $this->attributes([]), self::PAGE));
    }

    public function testLiteralAndFixedValues(): void
    {
        $this->assertSame('article', SocialFields::text('article', 'type', $this->attributes([]), self::PAGE));
        $this->assertSame('video', SocialFields::text(['value_from' => 'value', 'value' => 'video'], 'type', $this->attributes([]), self::PAGE));
        $this->assertSame('website', SocialFields::text(['value_from' => 'value', 'value' => ''], 'type', $this->attributes([]), self::PAGE));
    }

    public function testMissingOrUnusableConfigFallsBack(): void
    {
        foreach ([null, '', '  ', [], ['value_from' => 'nonsense'], 42] as $field) {
            $this->assertSame('About us', SocialFields::text($field, 'title', $this->attributes([]), self::PAGE), var_export($field, true));
        }
    }

    public function testAttributeHandleIsOnlyReturnedForAttributeFields(): void
    {
        $this->assertSame('meta_title', SocialFields::attributeHandle(['value_from' => 'page_attribute', 'attribute' => 'meta_title']));
        $this->assertNull(SocialFields::attributeHandle(['value_from' => 'page_property', 'property' => 'title']));
        $this->assertNull(SocialFields::attributeHandle(['value_from' => 'page_attribute']));
        $this->assertNull(SocialFields::attributeHandle('literal'));
        $this->assertNull(SocialFields::attributeHandle(null));
    }

    public function testHandleFromUrl(): void
    {
        $this->assertSame('acme', TwitterCard::handleFromUrl('https://twitter.com/acme'));
        $this->assertSame('acme', TwitterCard::handleFromUrl('https://x.com/acme/'));
        $this->assertSame('acme', TwitterCard::handleFromUrl('http://www.twitter.com/@acme?lang=en'));
        $this->assertSame('acme_1', TwitterCard::handleFromUrl('@acme_1'));

        foreach (['https://twitter.com/intent/tweet', 'https://example.com/acme', 'https://x.com/', 'https://x.com/way_too_long_for_a_handle', 'javascript:alert(1)', ''] as $url) {
            $this->assertSame('', TwitterCard::handleFromUrl($url), $url);
        }
    }

    public function testTwitterSiteTagIsAddedOnlyForAValidHandle(): void
    {
        $tags = TwitterCard::tags('Title', 'Desc', 'https://example.com/a.jpg', '@acme');

        $this->assertSame(['twitter:card', 'twitter:site', 'twitter:title', 'twitter:description', 'twitter:image'], array_keys($tags));
        $this->assertSame('<meta name="twitter:site" content="@acme">', $tags['twitter:site']);

        foreach (['', '@', 'not a handle', '"><script>', 'way_too_long_for_a_handle'] as $bad) {
            $this->assertArrayNotHasKey('twitter:site', TwitterCard::tags('Title', 'Desc', '', $bad), $bad);
        }
    }
}
