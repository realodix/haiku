<?php

namespace Realodix\Haiku\Test\Bench;

use PhpBench\Attributes as Bench;
use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Linter\Helper;
use Realodix\Haiku\Linter\RuleErrorBuilder;

#[Bench\BeforeMethods('setUp')]
class LintBench
{
    public function setUp(): void
    {
        app()->instance(LinterConfig::class, new LinterConfig);
    }

    #[Bench\Revs(100)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(1)]
    #[Bench\Assert('(mode(variant.time.avg) < mode(baseline.time.avg) +/- 10%)')]
    public function benchLint(): void
    {
        $lines = [
            '-banner-$from=a.com,image',
            '-banner-$image,domain=a.com|b.com',
            '@@/adv/*',
            '@@/adv/ads',
            '*$domain=a.com',
            '*$domain=a.com',
            '*$image,script',
            '*$script,image',
            '/ads/*',
            '/ads/*',
            '/ads/*$domain=~example.org',
            '/ads/*$image',
            '/adv/',
            '/advertisement/*$image',
            '/advertisement/ads-$image',
            '/banner/*$domain=~example.net',
            '||example.com^',
            '||example.com^$script',
            '||example.org/banner/',
            '||somesite.com/ads/',
            '||somesite.com/ads/$image',
            '##.ads',
            'example.com##.ads',
            'example.com##.ads',
            '~example.org##.banner1',
            'example.com##.banner1',
            '##a[title="Ads-1" i]',
            '##a[title^="ADS" i]',
            '##a[title="ads-2"]',
            '##img[alt="advertising"]',
            '##img[alt^="adv" i]',
            '##img[alt="Advertisement"]',
            'example.com,example.org##a',
            'example.com##a',
            'a.com,x.com,b.com##a[title="Ads-1" i]',
            'a.com,b.com##a[title^="ADS" i]',
            'b.com,a.com##a[title^="ADv" i]',
            'a.com,x.com,b.com##a[title="Adv-1" i]',
            '##img[alt^="FOO_BAR" i]',
            '##img[alt^="Foo_Bar_1" i]',
            '##img[alt^="Foo_Bar_2" i]',
            '##img[alt^="Foo_Bar_3" i]',
            '##img[alt^="foo_bar_2" i]',
            '##img[alt^="foo_bar" i]',
            'example.org##+js(nowoif)',
            'example.org##+js(nowoif)',
            'a.com,x.com,b.com##+js(acis)',
            'a.com,b.com##+js(acis)]',
            'b.com,a.com##+js(acis)',

            '*$3p,script,3p',
            '*$3p,script,3p,script',
            '*$domain=a.com,domain=b.com',
            '*$script,domain=example.com,from=example.com',
            '*$css,script,stylesheet',

            '###1800number_bo',
            'example.com###1800number_bo',
            'example.com###1800number_bo #13_3623',
            'example.com##h3[style*="color:#999"] #1800number_bo + path[fill="#9E9E9E"]',
            'example.com##:-abp-has(.sponsored)',
            'example.com##:-abp-contains(filters)',
            'example.com##:-abp-properties(background-color: #3D9C4F;)',

            '##.ads',
            'example.com,example.org##.ads',
            ',example.com##.ads',
            'example.com,,example.org##.ads',
            'example.com,##.ads',
            '||ex.com^$domain=|a.com',
            ',a.com,b.com,c.com,,d.com,e.com,f.com,,,g.com,h.com,i.com,##.ad-middle',
            'a,example.com,c##.ads',
            '*$domain=a|example.com|c',
            'example.##.ad',
            'e xample.com##.ad',
            '/single$domain= example.com ',
            '/foo$domain=example.com | example.org',
            '/bar$domain=example.com| example.org',
            '/baz$domain=example.com |example.org',
            'example.com , example.org##foo',
            'example.com, example.org##bar',
            'example.com ,example.org##baz',

            'example.org##+js(bar)',
            'example.org##+js("bar")',
            'example.org##+js( bar.js  )',
            'example.org##+js(nowolf)',
            'example.org##+js(nowolf.js)',

            '||example.com/*.js$1p,script,redirect=invalid',
            '||example.com/*.js$1p,script,redirect=noopjs:invalid-priority',
            '||example.com/*.js$1p,script,redirect-rule=invalid',

            'foo',
            '',
            '',
            '',
            '', // 4 empty lines
            'bar',
            '',
            '', // 2 empty lines (ok)
            '!',
            '',
            '',
            '', // 3 empty lines
            'bar',

            '!#if (adguard)',
            '!#endif',
            '!#if adguard',
            '!#endif',
            '!#if (adguard && !adguard_ext_safari)',
            '!#endif',

            'rule',
            '!#if (condition1)',
            '!#if (condition2)',
            'rule',
            '!#endif',
            'rule',
            'rule',
        ];

        $this->analyse($lines);
    }

    /**
     * @param list<string> $lines
     */
    protected function analyse(array $lines): void
    {
        app(LinterConfig::class)->rules = [
            'cosm_id_selector_start' => true,
            'no_extra_blank_lines' => true,
            'no_short_rules' => 4,
        ];

        $rules = Helper::loadLinterRules();
        foreach ($rules as $rule) {
            $rule->check($lines, new RuleErrorBuilder);
        }
    }
}
