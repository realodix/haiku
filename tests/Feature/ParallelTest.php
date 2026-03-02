<?php

namespace Realodix\Haiku\Test\Feature;

use Realodix\Haiku\Console\CommandOptions;
use Realodix\Haiku\Fixer\Fixer;
use Realodix\Haiku\Test\TestCase;
use Symfony\Component\Filesystem\Path;

class ParallelTest extends TestCase
{
    /**
     * Test parallel execution by providing enough data to trigger it.
     *
     * Thresholds in Fixer.php:
     * - minFiles: 4
     * - minAvgSize: 7 KB
     */
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

        $results = $fixer->handle($cmdOpt);
        foreach ($results as $result) {
            $this->assertSame('processed', $result->status);
        }

        // Second run: should be skipped (cache hit)
        $fixer = app(Fixer::class);
        $results = $fixer->handle($cmdOpt);
        foreach ($results as $result) {
            $this->assertSame('skipped', $result->status);
        }
    }
}
