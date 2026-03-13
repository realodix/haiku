<?php

namespace Realodix\Haiku\Test\Bench;

use PhpBench\Attributes as Bench;
use Realodix\Haiku\Config\FixerConfig;
use Realodix\Haiku\Console\Command\FixCommand;
use Realodix\Haiku\Fixer\Fixer;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[Bench\BeforeMethods('setUp')]
#[Bench\AfterMethods('tearDown')]
class GeneralBench
{
    private Fixer $fixer;

    private array $input;

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

        app()->instance(FixerConfig::class, new FixerConfig);
        $this->fixer = app(Fixer::class);

        $this->inputFile = base_path('tests/Bench/storage/filter.txt');
        $this->input = file($this->inputFile, FILE_IGNORE_NEW_LINES);

        // Silent output for command
        app()->singleton(
            \Symfony\Component\Console\Output\OutputInterface::class,
            \Symfony\Component\Console\Output\BufferedOutput::class,
        );
    }

    /**
     * Benchmark the core fix() method.
     */
    #[Bench\Revs(50)]
    #[Bench\Iterations(5)]
    public function benchFixMethod(): void
    {
        app(FixerConfig::class)->flags = [
            'fmode' => true,
            'domain_order' => 'negated_first',
            'option_format' => 'long',
        ];

        $this->fixer->fix($this->input);
    }

    /**
     * Benchmark the full fix command (file I/O, app overhead).
     */
    #[Bench\Revs(10)]
    #[Bench\Iterations(5)]
    #[Bench\RetryThreshold(5.0)]
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
