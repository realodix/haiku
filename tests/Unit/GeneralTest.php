<?php

namespace Realodix\Haiku\Test\Unit;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Fixer\Processor;
use Realodix\Haiku\Test\TestCase;

class GeneralTest extends TestCase
{
    use GeneralProvider;

    public function testComparesFiles(): void
    {
        $inputFile = base_path('tests/Integration/general_actual.txt');
        $expectedFile = base_path('tests/Integration/general_expected.txt');

        $this->assertFilter($expectedFile, $inputFile);
    }

    #[PHPUnit\Test]
    public function multiple_sections_are_processed_correctly()
    {
        $input = [
            '[$app=org.example.app]example.com##.textad',
            'example.com###ads',
            'example.com##.ad',

            'example.com##ads',
            'example.com#@##ads',
            'example.com#@#.ads',
            'example.com#@#ads',

            '/ads.$domain=example.com',
            '||example.com^',
            '@@||example.com^',

            'example.com#@#+js(...)',
            'example.com#@%#ads',
            'example.org##.ad',
        ];

        $expected = [
            '/ads.$domain=example.com',
            '||example.com^',
            '@@||example.com^',

            'example.com###ads',
            'example.com,example.org##.ad',
            '[$app=org.example.app]example.com##.textad',
            'example.com##ads',
            'example.com#@##ads',
            'example.com#@#.ads',
            'example.com#@#ads',

            'example.com#@#+js(...)',
            'example.com#@%#ads',
        ];

        arsort($input);
        $output = $this->fix($input);

        $this->assertSame($expected, $output);
    }

    /**
     * Remove the blank line
     */
    public function testBlankLine()
    {
        $input = [
            '',
            '     ',
        ];

        $expected = [];

        $output = $this->fix($input);

        $this->assertSame($expected, $output);
    }

    #[PHPUnit\Test]
    public function cleanup_the_spaces(): void
    {
        $input = [
            ' ! b',
            ' ! a',
        ];
        $expected = [
            '! b',
            '! a',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\DataProvider('notCombinedProvider')]
    #[PHPUnit\Test]
    public function not_combined($input, $expected): void
    {
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function special_line()
    {
        $input = [
            '2',
            '1',
            '[AdGuard]',
            '[uBlock Origin]',
            '[Adblock Plus 2.0]',
            '2',
            '1',
            '',
            '    ',
            '2',
            '1',
        ];

        $expected = [
            '1',
            '2',
            '[AdGuard]',
            '[uBlock Origin]',
            '[Adblock Plus 2.0]',
            '1',
            '2',
        ];

        $output = $this->fix($input);

        $this->assertSame($expected, $output);
    }

    #[PHPUnit\DataProvider('isSpecialLineProvider')]
    #[PHPUnit\Test]
    public function isSpecialLine($data)
    {
        $this->assertTrue(app(Processor::class)->isSpecialLine($data));
    }

    #[PHPUnit\DataProvider('isNotSpecialLineProvider')]
    #[PHPUnit\Test]
    public function isNotSpecialLine($data)
    {
        $this->assertFalse(app(Processor::class)->isSpecialLine($data));
    }

    #[PHPUnit\DataProvider('splitDomainListContainingRegexProvider')]
    #[PHPUnit\Test]
    public function splitDomainListContainingRegex($input, $expected)
    {
        $this->assertSame([$expected], $this->fix([$input]));
    }

    public static function splitDomainListContainingRegexProvider(): array
    {
        return [
            [
                '$all,~doc,domain=laravel.com|~/example\.([a-z]{1,2}|[a-z]{4,16})/|example.*',
                '$all,~doc,domain=~/example\.([a-z]{1,2}|[a-z]{4,16})/|example.*|laravel.com',
            ],
            [
                '$all,~doc,domain=example.*|~/example\.([a-z]{1,2}|[a-z]{4,16})/',
                '$all,~doc,domain=~/example\.([a-z]{1,2}|[a-z]{4,16})/|example.*',
            ],
            [
                '$all,~doc,domain=~/example\.([a-z]{1,2}|[a-z]{4,16})/|laravel.com',
                '$all,~doc,domain=~/example\.([a-z]{1,2}|[a-z]{4,16})/|laravel.com',
            ],

            // `Regex::COSMETIC_RULE` does not support domain lists containing plain domains
            // mixed with regular expressions.
            [
                'example.*,~/example\.([a-z]{1,2}|[a-z]{4,16})/,laravel.com##body > *',
                'example.*,~/example\.([a-z]{1,2}|[a-z]{4,16})/,laravel.com##body > *',
            ],
            [
                'example.*,~/example\.([a-z]{1,2}|[a-z]{4,16})/##body > *',
                'example.*,~/example\.([a-z]{1,2}|[a-z]{4,16})/##body > *',
            ],
            [
                '~/example\.([a-z]{1,2}|[a-z]{4,16})/,laravel.com##body > *',
                '~/example\.([a-z]{1,2}|[a-z]{4,16})/,laravel.com##body > *',
            ],
        ];
    }

    #[PHPUnit\Test]
    public function handle_split_comma(): void
    {
        // escape comma
        $input = [
            '$permissions=storage-access=()\, camera=(),domain=b.com|a.com,image',
            '||example.org^$domain=/a\,b/,hls=/#UPLYNK-SEGMENT:.*\,ad/t',
        ];
        $expected = [
            '$image,permissions=storage-access=()\, camera=(),domain=a.com|b.com',
            '||example.org^$hls=/#UPLYNK-SEGMENT:.*\,ad/t,domain=/a\,b/',
        ];
        $this->assertSame($expected, $this->fix($input));

        // non escape comma
        $input = [
            '/ads.$domain=/^https:\/\/[a-z\d]{4,}+\.[a-z\d]{12,}+\.com$/',
            '/^https:\/\/[a-z\d]{4,}+\.[a-z\d]{12,}+\.(cfd|sbs|shop)$/##.ads',
            // https://github.com/uBlockOrigin/uBlock-issues/discussions/2234#discussioncomment-5403472
            'example.*,~/example\.([a-z]{1,2}|[a-z]{4,16})/##body > *',
        ];
        $expected = [
            '/ads.$domain=/^https:\/\/[a-z\d]{4,}+\.[a-z\d]{12,}+\.com$/',
            'example.*,~/example\.([a-z]{1,2}|[a-z]{4,16})/##body > *',
            '/^https:\/\/[a-z\d]{4,}+\.[a-z\d]{12,}+\.(cfd|sbs|shop)$/##.ads',
        ];
        $this->assertSame($expected, $this->fix($input));

        // Contains $, and must not be affected.
        $input = [
            'example.com#$?#style[id="mdpDeblocker-css"] { remove: true; }',
            'example.com#%#(function(b){Object.defineProperty(Element.prototype,"innerHTML",{get:function(){return b.get.call(this)},set:function(a){/^(?:<([abisuq]) id="[^"]*"><\/\1>)*$/.test(a)||b.set.call(this,a)},enumerable:!0,configurable:!0})})(Object.getOwnPropertyDescriptor(Element.prototype,"innerHTML"));',
            'example.com#$#.ignielAdBlock { display: none !important; }',
            'example.com#$#div.Ad-Container[id^="adblock-bait-element-"] { display: block !important; }',
        ];
        $expected = [
            'example.com#$#.ignielAdBlock { display: none !important; }',
            'example.com#$#div.Ad-Container[id^="adblock-bait-element-"] { display: block !important; }',
            'example.com#$?#style[id="mdpDeblocker-css"] { remove: true; }',
            'example.com#%#(function(b){Object.defineProperty(Element.prototype,"innerHTML",{get:function(){return b.get.call(this)},set:function(a){/^(?:<([abisuq]) id="[^"]*"><\/\1>)*$/.test(a)||b.set.call(this,a)},enumerable:!0,configurable:!0})})(Object.getOwnPropertyDescriptor(Element.prototype,"innerHTML"));',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    /**
     * The results must not cause warnings/errors
     */
    #[PHPUnit\Test]
    public function bad_filter_causing_error(): void
    {
        // https://github.com/AdguardTeam/FiltersRegistry/blob/281518f967/filters/exclusions.txt#L16
        // https://github.com/realodix/haiku/blob/e7b8da5d78/src/Fixer/Type/ElementTidy.php#L35
        $input = [
            'example.com##',
            'example.com#@#',
            'example.com#?#',
            'example.com##+',
        ];
        $this->assertSame($input, $this->fix($input));
    }
}
