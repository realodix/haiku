<?php

namespace Realodix\Haiku\Test\Linter\Rules\NetOptions;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Linter\Rules\NetOptions\GeneralCheck;
use Realodix\Haiku\Test\TestCase;

class GeneralCheckTest extends TestCase
{
    #[PHPUnit\Test]
    public function case(): void
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
    public function duplicate(): void
    {
        $lines = [
            '*$3p,script,3p',
            '*$3p,script,3p,script',
            '*$domain=a.com,domain=b.com',
        ];

        $this->analyse($lines, [
            [1, 'Duplicate option: $3p'],
            [2, 'Duplicate option: $3p'],
            [2, 'Duplicate option: $script'],
            [3, 'Duplicate option: $domain'],
        ]);
    }

    #[PHPUnit\Test]
    public function duplicateWithItsNegation(): void
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
    public function duplicateWithItsAlias(): void
    {
        $lines = [
            '*$script,domain=example.com,from=example.com',
            '*$css,script,stylesheet',
        ];

        $this->analyse($lines, [
            [1, 'Duplicate option: $from and $domain are aliases of each other.'],
            [2, 'Duplicate option: $css and $stylesheet are aliases of each other.'],
        ]);
    }

    #[PHPUnit\Test]
    public function invalidNegation(): void
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
    public function exceptionOnly(): void
    {
        $lines = [
            '@@*$important',
            '@@*$empty',
            '@@*$mp4',
        ];

        $this->analyse($lines, [
            [1, 'Invalid filter: $important is not allowed in exception rules.'],
            [2, 'Deprecated: The filter option $empty is deprecated.'],
            [2, 'Invalid filter: $empty is not allowed in exception rules.'],
            [3, 'Deprecated: The filter option $mp4 is deprecated.'],
            [3, 'Invalid filter: $mp4 is not allowed in exception rules.'],
        ]);

        $lines = [
            '*$cname',
            '*$genericblock',
        ];

        $this->analyse($lines, [
            [1, 'Invalid filter: $cname is only allowed in exception rules.'],
            [2, 'Invalid filter: $genericblock is only allowed in exception rules.'],
        ]);

        $lines = [
            '*$important',

            '@@*$cname',
            '@@*$genericblock',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function withoutValue_exceptionOnly(): void
    {
        $lines = [
            '*$csp',
            '*$permissions',
            '*$redirect',
            '*$redirect-rule',
            '*$uritransform',
            '*$replace',
            '*$urlskip',
            '||example.org^$removeheader',
        ];

        $this->analyse($lines, [
            [1, 'Invalid filter: $csp without value is only allowed in exception rules.'],
            [2, 'Invalid filter: $permissions without value is only allowed in exception rules.'],
            [3, 'Invalid filter: $redirect without value is only allowed in exception rules.'],
            [4, 'Invalid filter: $redirect-rule without value is only allowed in exception rules.'],
            [5, 'Invalid filter: $uritransform without value is only allowed in exception rules.'],
            [6, 'Invalid filter: $replace without value is only allowed in exception rules.'],
            [7, 'Invalid filter: $urlskip without value is only allowed in exception rules.'],
            [8, 'Invalid filter: $removeheader without value is only allowed in exception rules.'],
        ]);

        $lines = [
            '@@*$csp=foo',
            '@@*$permissions',
            '@@*$redirect',
            '@@*$redirect-rule',
            '@@*$uritransform',
            '@@*$replace',
            '@@*$urlskip',
            '*$urlskip=foo',
            '@@||example.org^$removeheader',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function denyallow_value(): void
    {
        $lines = [
            '*$script,denyallow=x.com|~y.com|z.com,domain=a.com',
            '*$script,denyallow=x.com|y.*|z.com,domain=a.com',
            '*$script,denyallow=~foo.*,domain=a.com',
        ];

        $this->analyse($lines, [
            [1, 'Domains in the $denyallow value cannot be negated: "~y.com"'],
            [2, 'Domains in the $denyallow value cannot have a wildcard TLD: "y.*"'],
            [3, 'Domains in the $denyallow value cannot be negated: "~foo.*"'],
            [3, 'Domains in the $denyallow value cannot have a wildcard TLD: "~foo.*"'],
        ], [GeneralCheck::class]);
    }

    #[PHPUnit\Test]
    public function denyallow_and_to(): void
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
            [2, 'Invalid filter: $denyallow requires $domain.'],
        ], [GeneralCheck::class]);
    }

    #[PHPUnit\Test]
    public function deprecatedOptions(): void
    {
        $lines = [
            '||example.org^$empty',
            '||example.com/videos/$mp4',
            '||example.com^$queryprune=foo',
            '*$queryprune=utm_source',
        ];

        $this->analyse($lines, [
            [1, 'Deprecated: The filter option $empty is deprecated.'],
            [2, 'Deprecated: The filter option $mp4 is deprecated.'],
            [3, 'Deprecated: The filter option $queryprune is deprecated.'],
            [4, 'Deprecated: The filter option $queryprune is deprecated.'],
        ]);
    }
}
