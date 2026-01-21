<?php

namespace Realodix\Haiku\Test\Unit\Builder;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Builder\Cleaner;
use Realodix\Haiku\Test\TestCase;

class CleaningTest extends TestCase
{
    #[PHPUnit\Test]
    public function agent(): void
    {
        $input = [
            '[Adblock]',
            '[AdBlock]',
            '[Adblock Plus]',
            '[Adblock Plus 2.0]',
            '[AdGuard]',
            '[uBlock]',
            '[uBlock Origin]',
            '[uBlock Origin Lite]',
            '[uBO Lite]',
        ];
        $this->assertSame([], Cleaner::clean($input));

        // not agent
        $input = [
            '[$adg-modifier]##[class^="ads-"]',
            '[$adg-modifier]$$script[data-src="banner"]',
        ];
        $this->assertSame($input, Cleaner::clean($input));
    }

    #[PHPUnit\Test]
    public function comment(): void
    {
        $input = [
            '! comment',
            '# comment',
            '#comment',
        ];

        $this->assertSame([], Cleaner::clean($input));
    }

    #[PHPUnit\Test]
    public function blank(): void
    {
        $input = [
            '      ',
            '',
        ];

        $this->assertSame([], Cleaner::clean($input));
    }

    #[PHPUnit\Test]
    public function unique(): void
    {
        $input = [
            '##.ads',
            '##.ads',
        ];

        $this->assertSame(['##.ads'], Cleaner::clean($input, true));
    }
}
