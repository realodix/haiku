<?php

namespace Realodix\Haiku\Test\Unit;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Console\CommandOptions;
use Realodix\Haiku\Fixer\Fixer;
use Realodix\Haiku\Fixer\Runner;
use Realodix\Haiku\Test\TestCase;
use Symfony\Component\Filesystem\Path;

class GeneralTest extends TestCase
{
    use GeneralProvider;

    public function testComparesFiles(): void
    {
        $inputFile = base_path('tests/Integration/general_actual.txt');
        $expectedFile = base_path('tests/Integration/general_expected.txt');

        $targetFile = Path::join($this->tmpDir, basename($inputFile));
        $this->fs->copy($inputFile, $targetFile, true);

        $this->applyFlags();
        app(Runner::class)->run(new CommandOptions(
            cachePath: $this->cacheFile,
            path: $targetFile,
        ));

        $this->assertFileEquals($expectedFile, $targetFile);
    }

    #[PHPUnit\Test]
    public function duplicateRules()
    {
        $input = [
            '-ads-',
            '-ads-',
        ];
        $expected = ['-ads-'];
        $this->assertSame($expected, $this->fix($input));

        $input = [
            '##.ads',
            '##.ads',
        ];
        $expected = ['##.ads'];
        $this->assertSame($expected, $this->fix($input));

        $input = [
            'example.com##.adsHeader',
            'example.com##.adsHeader',
        ];
        $expected = ['example.com##.adsHeader'];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function sort()
    {
        $input = [
            'a',
            'B',
            'A',
            'b',
        ];

        $expected = [
            'a',
            'A',
            'B',
            'b',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function multiple_sections_are_ordered_correctly()
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

    #[PHPUnit\Test]
    public function multiple_sections_are_ordered_correctly_2()
    {
        $input = [
            'example.*,~/example\.([a-z]{1,2}|[a-z]{4,16})/##body > *',
            'example.com##ads',
            '||example.com^',
            'example.com#@%#ads',
            '#%#window.__gaq = undefined;',
        ];

        $expected = [
            '||example.com^',
            'example.com##ads',
            '#%#window.__gaq = undefined;',
            'example.com#@%#ads',
            'example.*,~/example\.([a-z]{1,2}|[a-z]{4,16})/##body > *',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    public function testEmptyLine()
    {
        $input = [
            '##.ads',
            '     ',
            '##.banner',
            '',
            ' ',
            ' ! comment',
            '##.foo',
        ];

        $expected = ['##.ads', '##.banner', '! comment', '##.foo'];
        $this->assertSame($expected, $this->fix($input, ['remove_empty_lines' => true]));

        $expected = ['##.ads', '', '##.banner', '', '', '! comment', '##.foo'];
        $this->assertSame($expected, $this->fix($input, ['remove_empty_lines' => false]));

        $expected = ['##.ads', '##.banner', '', '! comment', '##.foo'];
        $this->assertSame($expected, $this->fix($input, ['remove_empty_lines' => 'keep_before_comment']));

        $input = array_map(fn($value) => str_replace('! ', '# ', $value), $input);
        $expected = ['##.ads', '##.banner', '', '# comment', '##.foo'];
        $this->assertSame($expected, $this->fix($input, ['remove_empty_lines' => 'keep_before_comment']));
        // there is no next line
        $this->assertSame([], $this->fix([''], ['remove_empty_lines' => 'keep_before_comment']));
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

    #[PHPUnit\Test]
    public function handle_split_comma(): void
    {
        // escape comma
        $input = [
            '$permissions=storage-access=()\, camera=(),domain=b.com|a.com,image,extension="userscript name\, with \"quote\""',
            '||example.org^$domain=/a\,b/,hls=/#UPLYNK-SEGMENT:.*\,ad/t,extension="userscript name\, with \"quote\""',
        ];
        $expected = [
            '$image,extension="userscript name\, with \"quote\"",permissions=storage-access=()\, camera=(),domain=a.com|b.com',
            '||example.org^$extension="userscript name\, with \"quote\"",hls=/#UPLYNK-SEGMENT:.*\,ad/t,domain=/a\,b/',
        ];
        $this->assertSame($expected, $this->fix($input));

        // non escape comma
        $input = [
            '/ads.$domain=/^https:\/\/[a-z\d]{4,}+\.[a-z\d]{12,}+\.com$/',
            '/^https:\/\/[a-z\d]{4,}+\.[a-z\d]{12,}+\.(cfd|sbs|shop)$/##.ads',
            // https://github.com/uBlockOrigin/uBlock-issues/discussions/2234#discussioncomment-5403472
            '~/example\.([a-z]{1,2}|[a-z]{4,16})/##body > *',
        ];
        $expected = [
            '/ads.$domain=/^https:\/\/[a-z\d]{4,}+\.[a-z\d]{12,}+\.com$/',
            '/^https:\/\/[a-z\d]{4,}+\.[a-z\d]{12,}+\.(cfd|sbs|shop)$/##.ads',
            '~/example\.([a-z]{1,2}|[a-z]{4,16})/##body > *',
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

    #[PHPUnit\Test]
    public function handle_nonAscii(): void
    {
        $input = [
            '$domain=éxample.com|färgbolaget.nu|jõulud.eu',
            'éxample.com,färgbolaget.nu,jõulud.eu##.ads',
        ];
        $expected = [
            '$domain=färgbolaget.nu|jõulud.eu|éxample.com',
            'färgbolaget.nu,jõulud.eu,éxample.com##.ads',
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

    /**
     * Don't touch: Not to be crushed, do not combine
     */
    #[PHPUnit\Test]
    public function another_syntax(): void
    {
        $input = [
            // Host
            '0.0.0.0 example.com',
            '0.0.0.0 example.org',
            '127.0.0.1 example.com',
            '127.0.0.1 example.org',
            '!', // Dnsmasq
            'address /example.com/#',
            'address /example.org/#',
            'address=/example.com/0.0.0.0',
            'address=/example.org/0.0.0.0',
            'server=/example.com/',
            'server=/example.org/',
            '!', // RPZ / BIND
            'example.com CNAME .',
            'example.org CNAME . ; Malware download (2020-05-25), see https://urlhaus.abuse.ch/host/0022a601.pphost.net/',
            'zone "0022a601.pphost.net" { type master; notify no; file "null.zone.file"; };',
            'zone "0following.com" { type master; notify no; file "null.zone.file"; };',
            '!', // UNBOUND
            'local-zone: "example.com" nxdomain',
            'local-zone: "example.org" nxdomain',
            '!', // Privoxy
            '.example.com',
            '.example.org',
        ];

        $this->assertSame($input, $this->fix($input));
        $this->analyse($input);

        $input = [
            '!', // MinerBlock and uBlacklist
            '*://*.example.com/*',
            '*://*.example.org/*',
        ];

        $this->assertSame($input, $this->fix($input));
        $this->analyse($input);
    }
}
