<?php

namespace Realodix\Haiku\Test\Bench;

use PhpBench\Attributes as Bench;
use Realodix\Haiku\Config\FormatterConfig;
use Realodix\Haiku\Formatter\Formatter;

#[Bench\BeforeMethods('setUp')]
class FixBench
{
    private Formatter $fmt;

    private array $input;

    private string $inputFile;

    public function setUp(): void
    {
        app()->instance(FormatterConfig::class, new FormatterConfig);

        $this->fmt = app(Formatter::class);
        $this->inputFile = base_path('tests/Bench/storage/filter.txt');

        $file = file($this->inputFile, FILE_IGNORE_NEW_LINES);
        if ($file === false) {
            throw new \InvalidArgumentException(\sprintf('Failed to read file "%s": ', $file));
        }
        $this->input = $file;
    }

    /**
     * Benchmark the core format() method.
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(1)]
    #[Bench\Assert('(mode(variant.time.avg) < mode(baseline.time.avg) +/- 10%)')]
    public function benchFix(): void
    {
        $this->fmt->format($this->input);
    }

    /**
     * Benchmark the core format() method.
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(1)]
    #[Bench\Assert('(mode(variant.time.avg) < mode(baseline.time.avg) +/- 10%)')]
    public function benchMaximumFix(): void
    {
        app(FormatterConfig::class)->flags = [
            'all' => true,
            'option_format' => 'long',
        ];

        $this->fmt->format($this->input);
    }
}
