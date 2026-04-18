<?php

namespace Realodix\Haiku\Test\Linter\Rules\NetOptions;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

class RedirectValueCheckTest extends TestCase
{
    #[PHPUnit\Test]
    public function valid(): void
    {
        $lines = [
            '||example.com/*.js$1p,script,redirect=google-ima.js',
            '||example.com/*.js$1p,script,redirect=noopjs:100',
            '||example.com/ads.js$script,redirect-rule=noop.js',
            '/fingerprint2.min.js$redirect=fingerprint2.js,domain=example.com',
            '*$xhr,redirect-rule=noopjs:-1,to=~example.com',
            '@@||example.org^$redirect',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function deprecated(): void
    {
        $lines = [
            '*$redirect=ligatus_angular-tag.js',
        ];

        $this->analyse($lines, [
            [1, 'Deprecated redirect resource value: "ligatus_angular-tag.js"'],
        ]);
    }

    #[PHPUnit\Test]
    public function invalid(): void
    {
        $lines = [
            '||example.com/*.js$1p,script,redirect=invalid',
            '||example.com/*.js$1p,script,redirect=noopjs:invalid-priority',
            '||example.com/*.js$1p,script,redirect-rule=invalid',
        ];

        $this->analyse($lines, [
            [1, 'Unknown redirect resource value: "invalid"'],
            [2, 'Unknown redirect resource value: "noopjs:invalid-priority"'],
            [3, 'Unknown redirect resource value: "invalid"'],
        ]);
    }
}
