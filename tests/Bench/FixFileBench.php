<?php

namespace Realodix\Haiku\Test\Bench;

use PhpBench\Attributes as Bench;
use Realodix\Haiku\Console\Command\FixCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[Bench\BeforeMethods('setUp')]
#[Bench\AfterMethods('tearDown')]
class FixFileBench
{
    private Filesystem $fs;

    private string $tmpDir;

    private string $inputFile;

    public function setUp(): void
    {
        $this->fs = new Filesystem;
        $this->tmpDir = base_path('tests/Integration/tmp');
        if (!$this->fs->exists($this->tmpDir)) {
            $this->fs->mkdir($this->tmpDir);
        }

        $this->inputFile = base_path('tests/Bench/storage/filter.txt');

        // Silent output for command
        app()->singleton(
            \Symfony\Component\Console\Output\OutputInterface::class,
            \Symfony\Component\Console\Output\BufferedOutput::class,
        );
    }

    /**
     * Benchmark the full fix command (file I/O, app overhead).
     */
    #[Bench\Revs(1)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(1)]
    #[Bench\RetryThreshold(10.0)]
    public function benchComparesFilesFull(): void
    {
        $processingFile = Path::join($this->tmpDir, 'bench_general_actual.txt');
        $this->fs->copy($this->inputFile, $processingFile, true);

        $application = new Application;
        $application->addCommand(app(FixCommand::class));
        $command = $application->find('fix');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--path' => $processingFile,
            '--cache' => Path::join($this->tmpDir, 'cache_bench.json'),
            '--force' => true,
            '--config' => 'tests/Bench/storage/haiku.yml',
        ]);
    }

    public function tearDown(): void
    {
        if ($this->fs->exists($this->tmpDir)) {
            $files = array_merge(
                glob(Path::join($this->tmpDir, '/*.txt')),
                glob(Path::join($this->tmpDir, '/*.json')),
                glob(Path::join($this->tmpDir, '/.*.json')),
            );

            if ($files) {
                $this->fs->remove($files);
            }
        }
    }
}
