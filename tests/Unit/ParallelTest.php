<?php

namespace Realodix\Haiku\Test\Unit;

use Realodix\Haiku\Console\CommandOptions;
use Realodix\Haiku\Fixer\Fixer;
use Realodix\Haiku\Test\TestCase;
use Symfony\Component\Filesystem\Path;

class ParallelTest extends TestCase
{
    public function testParallelExecution(): void
    {
        // Create some files
        for ($i = 1; $i <= 4; $i++) {
            $this->fs->dumpFile(
                Path::join($this->tmpDir, "file_parallel{$i}.txt"),
                "foo\n",
            );
        }

        // First run: should process all files in parallel
        $fixer = app(Fixer::class);
        $cmdOpt = new CommandOptions(
            cachePath: $this->cacheFile,
            path: $this->tmpDir,
            forceParallel: true,
        );

        $fixer->handle($cmdOpt);
        foreach ($fixer->results as $result) {
            $this->assertSame('processed', $result['status']);
        }

        // Second run: should be skipped (cache hit)
        $fixer = app(Fixer::class);
        $fixer->handle($cmdOpt);
        foreach ($fixer->results as $result) {
            $this->assertSame('skipped', $result['status']);
        }
    }
}
