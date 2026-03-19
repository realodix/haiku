<?php

namespace Realodix\Haiku\Test\Bench;

use PhpBench\Attributes as Bench;
use Realodix\Haiku\Config\FixerConfig;
use Realodix\Haiku\Fixer\Fixer;

#[Bench\BeforeMethods('setUp')]
class FixBench
{
    private Fixer $fixer;

    private array $input;

    private string $inputFile;

    public function setUp(): void
    {
        app()->instance(FixerConfig::class, new FixerConfig);

        $this->fixer = app(Fixer::class);
        $this->inputFile = base_path('tests/Bench/storage/filter.txt');
        $this->input = file($this->inputFile, FILE_IGNORE_NEW_LINES);
    }

    /**
     * Benchmark the core fix() method.
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(1)]
    #[Bench\Assert('(mode(variant.time.avg) < mode(baseline.time.avg) +/- 2%)')]
    public function benchFix(): void
    {
        $this->fixer->fix($this->input);
    }

    /**
     * Benchmark the core fix() method.
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(1)]
    #[Bench\Assert('(mode(variant.time.avg) < mode(baseline.time.avg) +/- 2%)')]
    public function benchMaximumFix(): void
    {
        app(FixerConfig::class)->flags = [
            'fmode' => true,
            'domain_order' => 'negated_first',
            'option_format' => 'long',
        ];

        $this->fixer->fix($this->input);
    }
}
