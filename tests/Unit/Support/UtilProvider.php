<?php

namespace Realodix\Haiku\Test\Unit\Support;

trait UtilProvider
{
    public static function isCosmeticRuleProvider(): array
    {
        return [
            // Element hiding rules
            // https://adguard.com/kb/general/ad-filtering/create-own-filters/#cosmetic-elemhide-rules
            ['##.ads'],
            ['###img'],
            ['##img'],
            ['#@#.ads'],
            ['#@##img'],
            ['#@#img'],

            // CSS rules
            // https://adguard.com/kb/general/ad-filtering/create-own-filters/#cosmetic-css-rules
            ['#$#div { visibility: hidden; }'],
            ['#@$#div { visibility: hidden; }'],
            ['#$#.textad { visibility: hidden; }'],
            ['#@$#.textad { visibility: hidden; }'],
            ['#$##textad { visibility: hidden; }'],
            ['#@$##textad { visibility: hidden; }'],

            // Extended CSS selectors
            // https://adguard.com/kb/general/ad-filtering/create-own-filters/#extended-css-selectors
            ['#?#div:has(> a[target="_blank"][rel="nofollow"])'],
            ['#@?#div:has(> a[target="_blank"][rel="nofollow"])'],
            ['#?#.banner:matches-css(width: 360px)'],
            ['#@?#.banner:matches-css(width: 360px)'],
            ['#?##banner:matches-css(width: 360px)'],
            ['#@?##banner:matches-css(width: 360px)'],

            ['#$?#div:has(> span) { display: none !important; }'],
            ['#@$?#div:has(> span) { display: none !important; }'],
            ['#$?#.banner:has(> span) { display: none !important; }'],
            ['#@$?#.banner:has(> span) { display: none !important; }'],
            ['#$?##banner:has(> span) { display: none !important; }'],
            ['#@$?##banner:has(> span) { display: none !important; }'],

            // JavaScript rules
            // https://adguard.com/kb/general/ad-filtering/create-own-filters/#javascript-rules
            // https://adguard.com/kb/general/ad-filtering/create-own-filters/#scriptlets
            // ['#%#window.__gaq = undefined;'],
            // ['#@%#window.__gaq = undefined;'],
        ];
    }

    public static function isNotCosmeticRuleProvider(): array
    {
        return [
            // Element hiding rules
            ['## .ads'],
            ['## #img'],
            ['## img'],
            ['#@# .ads'],
            ['#@# #img'],
            ['#@# img'],
            // CSS rules
            ['#$# div { visibility: hidden; }'],
            ['#@$# div { visibility: hidden; }'],
            ['#$# .textad { visibility: hidden; }'],
            ['#@$# .textad { visibility: hidden; }'],
            ['#$# #textad { visibility: hidden; }'],
            ['#@$# #textad { visibility: hidden; }'],
            // Extended CSS selectors
            ['#?# div:has(> a[target="_blank"][rel="nofollow"])'],
            ['#@?# div:has(> a[target="_blank"][rel="nofollow"])'],
            ['#?# .banner:matches-css(width: 360px)'],
            ['#@?# .banner:matches-css(width: 360px)'],
            ['#?# #banner:matches-css(width: 360px)'],
            ['#@?# #banner:matches-css(width: 360px)'],
            ['#$?# div:has(> span) { display: none !important; }'],
            ['#@$?# div:has(> span) { display: none !important; }'],
            ['#$?# .banner:has(> span) { display: none !important; }'],
            ['#@$?# .banner:has(> span) { display: none !important; }'],
            ['#$?# #banner:has(> span) { display: none !important; }'],
            ['#@$? ##banner:has(> span) { display: none !important; }'],
            // JavaScript rules
            ['#%# window.__gaq = undefined;'],
            ['#@%# window.__gaq = undefined;'],

            // Comment
            ['############################################'],
            ['#202509090000'],
            ['#foo'],
            ['# foo'],
        ];
    }

    public static function isMetaLineProvider(): array
    {
        return [
            // Comment line
            ['!'],
            ['! comment'],
            // Special comment
            ['#comment'],
            ['# comment'],
            ['##'],
            ['###'],

            // Headers
            ['[]'],
            ['[ ]'],
            ['[Adblock Plus 2.0]'],
            ['[Adblock Plus 3.1; AdGuard 1.0]'],

            // Preprocessor directive
            // https://github.com/gorhill/uBlock/wiki/Static-filter-syntax#pre-parsing-directives
            // https://adguard.com/kb/general/ad-filtering/create-own-filters/#preprocessor-directives
            ['!#include ublock-filters.txt'],
            ['!#if env_firefox'],
            ['!#if (conditions)'],
            ['!#else'],
            ['!#endif'],
            ['!+NOT_OPTIMIZED'],
            ['!+ NOT_OPTIMIZED'],
            ['!+ NOT_OPTIMIZED PLATFORM(android)'],

            // YAML metadata
            ['---'],
        ];
    }

    public static function isNotMetaLineProvider(): array
    {
        return [
            // https://adguard.com/kb/general/ad-filtering/create-own-filters/#non-basic-rules-modifiers
            ['['],
            ['[$path=/test]example.org##.ad'],
            ['[$path=/test]##.ad'],
            // Special case
            ['[$adg-modifier]##[class^="ads-"]'],
            ['[$adg-modifier]$$script[data-src="banner"]'],

            // Like YAML metadata
            ['-ads-'],
        ];
    }

    public static function splitOptions_provider()
    {
        return [
            [
                '$~third-party,~xmlhttprequest,domain=~www.example.com',
                ['$~third-party', '~xmlhttprequest', 'domain=~www.example.com'],
            ],
            [
                '$_,removeparam=/^ss\\$/,__,image,1p,3p',
                ['$_', 'removeparam=/^ss\$/,__', 'image', '1p', '3p'],
            ],

            // only network options, then the filter rules will also be captured
            [
                '||example.com/*.js$1p,script',
                ['||example.com/*.js$1p', 'script'],
            ],

            // typo
            [ // uppercase network option
                '$IMAGE,DOMAIN=a.com|b.com',
                ['$IMAGE', 'DOMAIN=a.com|b.com'],
            ],
            [ // has superfluous commas
                '*$,script,,header=via:/1\.1\s+google/,,css,',
                ['*$', 'script', '', 'header=via:/1\.1\s+google/', '', 'css', ''],
            ],

            // ignore: escape comma
            [
                '$image,permissions=storage-access=()\, camera=(),domain=a.com|b.com',
                ['$image', 'permissions=storage-access=()\, camera=()', 'domain=a.com|b.com'],
            ],
            [
                '||example.org^$hls=/#UPLYNK-SEGMENT:.*\,ad/t,domain=/a\,b/',
                ['||example.org^$hls=/#UPLYNK-SEGMENT:.*\,ad/t', 'domain=/a\,b/'],
            ],

            // ignore: comma inside regex
            [
                '/ads.$domain=/^https:\/\/[a-z\d]{4,}+\.[a-z\d]{12,}+\.(cfd|sbs|shop)$/',
                ['/ads.$domain=/^https:\/\/[a-z\d]{4,}+\.[a-z\d]{12,}+\.(cfd|sbs|shop)$/'],
            ],
            [
                '/^https:\/\/[a-z\d]{4,}+\.[a-z\d]{12,}+\.(cfd|sbs|shop)$/',
                ['/^https:\/\/[a-z\d]{4,}+\.[a-z\d]{12,}+\.(cfd|sbs|shop)$/'],
            ],
            [ // https://github.com/uBlockOrigin/uBlock-issues/discussions/2234#discussioncomment-5403472
                '$all,~doc,domain=example.*|/example\.([a-z]{1,2}|[a-z]{4,16})/',
                ['$all', '~doc', 'domain=example.*|/example\.([a-z]{1,2}|[a-z]{4,16})/'],
            ],
        ];
    }

    /**
     * https://github.com/mathiasbynens/CSS.escape/blob/4b25c283e/tests/tests.js
     */
    public static function cssEscapeProvider(): array
    {
        return [
            // allowed_characters
            ['abc', 'abc'],
            ['A_Z-09', 'A_Z-09'],

            // null character
            ["\0", "\u{FFFD}"],
            ["a\0", "a\u{FFFD}"],
            ["\0b", "\u{FFFD}b"],
            ["a\0b", "a\u{FFFD}b"],
            // replacement character passthrough
            ["\u{FFFD}", "\u{FFFD}"],
            ["a\u{FFFD}", "a\u{FFFD}"],
            ["\u{FFFD}b", "\u{FFFD}b"],
            ["a\u{FFFD}b", "a\u{FFFD}b"],
            // control characters
            ["\x01", '\1 '],
            ["\x1F", '\1f '],
            ["\x7F", '\7f '],
            [chr(0x01).chr(0x02).chr(0x1E).chr(0x1F), '\1 \2 \1e \1f '],
            // first character digit
            ['1abc', '\31 abc'],
            ['9test', '\39 test'],
            // second character digit after dash
            ['-1abc', '-\31 abc'],
            ['-9foo', '-\39 foo'],
            // single dash
            ['-', '\-'],
            ['-a', '-a'],
            ['--', '--'],
            ['--a', '--a'],
            // unicode passthrough
            ['é', 'é'],
            ["\x80\x2D\x5F\xA9", "\x80\x2D\x5F\xA9"],
            ["\xA0\xA1\xA2", "\xA0\xA1\xA2"],
            // other characters are escaped
            ['#', '\#'],
            ['.', '\.'],
            ['[', '\['],
            [':', '\:'],
            // simple_escape_characters
            [' !xy', '\ \!xy'],
            // astral_symbol_passthrough
            ["\u{1D306}", "\u{1D306}"],
        ];
    }
}
