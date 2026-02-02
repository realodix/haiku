<?php

namespace Realodix\Haiku\Console;

use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

final class OutputLogger
{
    private string $root;

    public function __construct(
        private OutputInterface $output,
        private Statistics $stats,
    ) {
        $this->root = project_path();
    }

    public function processing(string $path): void
    {
        $path = Path::makeRelative($path, $this->root);

        $this->stats->incrementProcessing();
        $this->output->writeln("<fg=gray>[P]: $path</>");
    }

    /**
     * Logs a processed file.
     *
     * @param string $path The path to the processed file.
     */
    public function processed(string $path): void
    {
        $path = Path::makeRelative($path, $this->root);

        if ($this->stats()->getProcessing() > 0) {
            (new Cursor($this->output))->moveUp()->clearLine();
        }

        $this->output->writeln("<info>[P]: $path</info>");
        $this->stats->incrementProcessed();
    }

    public function skipped(string $path): void
    {
        $path = Path::makeRelative($path, $this->root);
        if ($this->output->isVerbose()) {
            $this->output->writeln("<fg=gray>[S]: $path</>");
        }

        $this->stats->incrementSkipped();
    }

    public function error(string $message): void
    {
        $this->output->writeln("<fg=red>[E]: $message</>");
        $this->stats->incrementError();
    }

    public function stats(): Statistics
    {
        return $this->stats;
    }
}
