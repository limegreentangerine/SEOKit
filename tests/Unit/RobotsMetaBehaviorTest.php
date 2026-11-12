<?php

namespace SeoKit\Tests\Unit;

use SeoKit\Robots\RobotsMeta;
use PHPUnit\Framework\TestCase;

final class RobotsMetaBehaviorTest extends TestCase
{
    private function option(string $name): object
    {
        return new class($name) {
            public function __construct(private string $name) {}

            public function getSelectAttributeOptionValue(): string
            {
                return $this->name;
            }
        };
    }

    public function testNoValueMeansNoTag(): void
    {
        foreach ([null, '', [], new \ArrayIterator([]), 42] as $value) {
            $this->assertNull(RobotsMeta::tag($value), var_export($value, true));
        }
    }

    public function testOptionObjectsAndStringsAreAccepted(): void
    {
        $this->assertSame(
            '<meta name="robots" content="nofollow, noarchive">',
            RobotsMeta::tag(new \ArrayIterator([$this->option('noarchive'), $this->option('nofollow')])),
        );
        $this->assertSame('<meta name="robots" content="nosnippet">', RobotsMeta::tag(['nosnippet']));
    }

    public function testNoindexAndUnknownValuesAreDroppedAndDuplicatesCollapsed(): void
    {
        // noindex belongs to core's exclude_search_index attribute
        $this->assertNull(RobotsMeta::tag(['noindex', 'evil"><script>', 'index']));
        $this->assertSame(['nofollow'], RobotsMeta::directives(['NoFollow ', 'nofollow', 'noindex']));
    }
}
