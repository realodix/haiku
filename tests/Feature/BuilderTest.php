<?php

namespace Realodix\Haiku\Test\Feature;

use Carbon\Carbon;
use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

class BuilderTest extends TestCase
{
    public function testBuild()
    {
        $this->runBuildCommand();

        $this->assertFileEquals(
            base_path('tests/Integration/Builder/result/compiled1.txt'),
            base_path('tests/Integration/tmp/compiled1.txt'),
        );
    }

    public function testBuild2()
    {
        Carbon::setTestNow('2026-01-01 10:10:00');

        $this->runBuildCommand();

        $this->assertFileEquals(
            base_path('tests/Integration/Builder/result/compiled2.txt'),
            base_path('tests/Integration/tmp/compiled2.txt'),
        );
    }

    #[PHPUnit\Test]
    public function date_modified()
    {
        $this->assertStringContainsString(
            'date_modified: %timestamp%',
            file_get_contents(base_path('tests/Integration/Builder/haiku.yml')),
        );

        $this->runBuildCommand();
        $compiledContent = file_get_contents(base_path('tests/Integration/tmp/date_modified.txt'));
        $this->assertStringContainsString('date_modified:', $compiledContent);
        $this->assertStringNotContainsString('%timestamp%', $compiledContent);
    }
}
