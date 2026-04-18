<?php

namespace Realodix\Haiku\Test\Bench;

use PhpBench\Attributes as Bench;
use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Linter\Util;

#[Bench\BeforeMethods('setUp')]
class LintBench
{
    public function setUp(): void
    {
        app()->instance(LinterConfig::class, new LinterConfig);
    }

    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(1)]
    #[Bench\Assert('(mode(variant.time.avg) < mode(baseline.time.avg) +/- 10%)')]
    public function benchLint(): void
    {
        $lines = [
            '##.ads',
            '##.ads',
            '###id_1_bannerId',
            '##[id^="id_1_banner"]',
            '###id_2_bannerId',
            '##[id^="id_2_banner" i]',
            '*$3p,script,3p',
            '*$3p,script,3p,script',
            '*$domain=a.com,domain=b.com',
            '||example.com/*.js$1p,script,redirect=google-ima.js',
            '||example.com/*.js$1p,script,redirect=noopjs:100',
            '||example.com/ads.js$script,redirect-rule=noop.js',
            '/fingerprint2.min.js$redirect=fingerprint2.js,domain=example.com',
            '*$xhr,redirect-rule=noopjs:-1,to=~example.com',
            '@@||example.org^$redirect',
            'example.org##+js(nowoif)',
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
            'no_short_rules' => 3,
        ];

        $rules = Util::loadLinterRules();
        foreach ($rules as $rule) {
            $rule->check($lines);
        }
    }
}
