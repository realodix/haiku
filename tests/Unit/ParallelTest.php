<?php

namespace Realodix\Haiku\Test\Unit;

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
        $tester = $this->runFixCommand([
            '--path' => $this->tmpDir,
            '--force-parallel' => true,
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total: 4, Processed: 4, Skipped: 0, Error: 0', $output);
        $this->assertFileExists($this->cacheFile);

        // Second run: should skip all 4 files due to cache
        $tester = $this->runFixCommand([
            '--path' => $this->tmpDir,
            '--force-parallel' => true,
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('All files have been processed.', $output);
    }
}
