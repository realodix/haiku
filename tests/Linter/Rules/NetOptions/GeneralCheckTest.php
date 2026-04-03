<?php

namespace Realodix\Haiku\Test\Linter\Rules\NetOptions;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

class GeneralCheckTest extends TestCase
{
    #[PHPUnit\Test]
    public function duplicate(): void
    {
        $lines = [
            '*$3p,script,3p',
            '*$3p,script,3p,script',
            '*$domain=a.com,domain=b.com',
        ];

        $this->analyse($lines, [
            [1, 'Duplicate option "3p".'],
            [2, 'Duplicate option "3p".'],
            [2, 'Duplicate option "script".'],
            [3, 'Duplicate option "domain".'],
        ]);
    }

    #[PHPUnit\Test]
    public function uppercase(): void
    {
        $lines = [
            '*$3p,SCRIPT,Css',
        ];

        $this->analyse($lines, [
            [1, 'Option "Css" must be lowercase.'],
            [1, 'Option "SCRIPT" must be lowercase.'],
        ]);
    }

    #[PHPUnit\Test]
    public function alias_redundant(): void
    {
        $lines = [
            '*$script,domain=example.com,from=example.com',
            '*$css,script,stylesheet',
        ];

        $this->analyse($lines, [
            [1, 'Options "from" and "domain" are redundant (aliases of each other).'],
            [2, 'Options "css" and "stylesheet" are redundant (aliases of each other).'],
        ]);
    }

    #[PHPUnit\Test]
    public function denyallow_and_to_conflict(): void
    {
        $lines = [
            '*$script,denyallow=x.com,domain=y.com,to=z.org',
        ];

        $this->analyse($lines, [
            [1, '$denyallow cannot be used together with $to.'],
        ]);
    }

    #[PHPUnit\Test]
    public function denyallow_requires_domain(): void
    {
        $lines = [
            '*$3p,script,denyallow=x.com|y.com,domain=a.com|b.com',
            '*$3p,script,denyallow=x.com',
        ];

        $this->analyse($lines, [
            [2, '$denyallow requires $domain.'],
        ]);
    }

    #[PHPUnit\Test]
    public function checkDeprecatedOptions(): void
    {
        $lines = [
            '||example.org^$empty',
            '||example.com/videos/$mp4',
            '||example.com^$queryprune=foo',
            '*$queryprune=utm_source',
        ];

        $this->analyse($lines, [
            [1, 'Deprecated filter option: "empty".'],
            [2, 'Deprecated filter option: "mp4".'],
            [3, 'Deprecated filter option: "queryprune".'],
            [4, 'Deprecated filter option: "queryprune".'],
        ]);
    }

    #[PHPUnit\Test]
    public function checkInterOptionDomainContradiction(): void
    {
        $lines = [
            '*$domain=a.com|b.com,denyallow=b.com',
            '*$from=a.com,to=~a.com|b.com',
        ];

        $this->analyse($lines, [
            [1, 'Option $denyallow contradicts $domain for: b.com'],
            [2, 'Option $to contradicts $from for: a.com'],
        ]);

        $lines = [
            '*$domain=a.com|b.com,denyallow=~b.com',
            '*$from=a.com,to=a.com|b.com',
            '*$script,3p,denyallow=example.com,domain=example.com',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function checkOptionConflict(): void
    {
        $lines = [
            '*$script,~script',
            '*$css,~stylesheet',
            '*$stylesheet,~css',
        ];

        $this->analyse($lines, [
            [1, '$script conflicts with its negation.'],
            [2, '$stylesheet conflicts with its negation.'],
            [3, '$stylesheet conflicts with its negation.'],
        ]);
    }

    #[PHPUnit\Test]
    public function checkInvalidNegation(): void
    {
        $lines = [
            '*$~strict1p',
            '*$~strict3p',
            '*$~domain=a.com',
        ];

        $this->analyse($lines, [
            [1, '$strict1p cannot be negated.'],
            [2, '$strict3p cannot be negated.'],
            [3, '$domain cannot be negated.'],
        ]);
    }

    #[PHPUnit\Test]
    public function checkExceptionOnlyOptions(): void
    {
        $lines = [
            '@@*$important',
            '@@*$empty',
            '@@*$mp4',
        ];

        $this->analyse($lines, [
            [1, 'Option "important" is not allowed in exception rules.'],
            [2, 'Option "empty" is not allowed in exception rules.'],
            [3, 'Option "mp4" is not allowed in exception rules.'],
            [2, 'Deprecated filter option: "empty".'],
            [3, 'Deprecated filter option: "mp4".'],
        ]);

        $lines = [
            '*$cname',
            '*$genericblock',

            '*$csp',
            '*$permissions',
            '*$redirect',
            '*$redirect-rule',
            '*$uritransform',
            '*$replace',
            '*$urlskip',
        ];

        $this->analyse($lines, [
            [1, 'Option "cname" is only allowed in exception rules.'],
            [2, 'Option "genericblock" is only allowed in exception rules.'],
            [3, 'Option "csp" without value is only allowed in exception rules.'],
            [4, 'Option "permissions" without value is only allowed in exception rules.'],
            [5, 'Option "redirect" without value is only allowed in exception rules.'],
            [6, 'Option "redirect-rule" without value is only allowed in exception rules.'],
            [7, 'Option "uritransform" without value is only allowed in exception rules.'],
            [8, 'Option "replace" without value is only allowed in exception rules.'],
            [9, 'Option "urlskip" without value is only allowed in exception rules.'],
        ]);

        $lines = [
            '*$important',

            '@@*$cname',
            '@@*$genericblock',

            '@@*$csp=foo',
            '@@*$permissions',
            '@@*$redirect',
            '@@*$redirect-rule',
            '@@*$uritransform',
            '@@*$replace',
            '@@*$urlskip',
            '*$urlskip=foo',
        ];

        $this->analyse($lines);
    }
}
