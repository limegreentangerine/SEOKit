<?php

namespace SeoKit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SeoKit\Support\ControllerValues;

final class ControllerValuesBehaviorTest extends TestCase
{
    public function testTextIsTrimmedAndMissingKeysAreEmpty(): void
    {
        $sets = ['pageTitle' => '  Flat 4, High St ', 'pageDescription' => ['not text']];

        $this->assertSame('Flat 4, High St', ControllerValues::pick($sets, 'pageTitle'));
        $this->assertSame('', ControllerValues::pick($sets, 'pageDescription'));
        $this->assertSame('', ControllerValues::pick($sets, 'missing'));
    }

    public function testUrlsMustBeAbsoluteHttp(): void
    {
        $sets = ['pageImage' => '/files/a.jpg', 'ogUrl' => 'javascript:alert(1)'];

        $this->assertSame('', ControllerValues::pick($sets, 'pageImage'));
        $this->assertSame('', ControllerValues::pick($sets, 'ogUrl'));
        $this->assertSame('https://example.com/p/9', ControllerValues::pick(['ogUrl' => 'https://example.com/p/9'], 'ogUrl'));
    }

    public function testAPageWithoutAControllerGivesNothing(): void
    {
        $this->assertSame('', ControllerValues::get(new \stdClass(), 'pageTitle'));
    }
}
