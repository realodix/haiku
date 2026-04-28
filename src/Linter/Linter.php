<?php

namespace Realodix\Haiku\Linter;

use Realodix\Haiku\Config\Config;
use Realodix\Haiku\Linter\Rules\Rule;
use Symfony\Component\Yaml\Yaml;

final class Linter
{
    /** @var list<Rule> */
    private array $rules;

    private ErrorReporter $errorReporter;

    public function __construct(
        private Config $config,
    ) {
        $this->errorReporter = new ErrorReporter;
        $this->rules = Util::loadLinterRules();
    }

    /**
     * Run the linter against the configured paths.
     *
     * @param \Realodix\Haiku\Console\CommandOptions $cmdOpt CLI runtime options
     * @param (callable(int): void)|null $onStart Callback executed before starting analysis,
     *                                            receives the total number of files
     * @param (callable(): void)|null $onAdvance Callback executed after each file is analysed
     */
    public function run($cmdOpt, ?callable $onStart = null, ?callable $onAdvance = null): ErrorReporter
    {
        $config = $this->config->linter($cmdOpt);

        $baselineErrors = [];
        $baselineFile = base_path('haiku-baseline.yml');
        if (!$cmdOpt->generateBaseline && file_exists($baselineFile)) {
            $baseline = Yaml::parseFile($baselineFile);
            $baselineErrors = $baseline['ignoreErrors'] ?? [];
        }

        $ignoredErrors = new IgnoredErrors($config->ignoreErrors, $baselineErrors);

        if ($onStart !== null) {
            $onStart(count($config->paths));
        }

        foreach ($config->paths as $path) {
            $this->analyseFile($path, $ignoredErrors);

            if ($onAdvance !== null) {
                $onAdvance();
            }
        }

        $ignoredErrors->reportUnmatched($this->errorReporter);

        return $this->errorReporter;
    }

    /**
     * Analyse a single file and report errors.
     *
     * @param string $path The path to the file to be analysed
     * @param IgnoredErrors $ignoredErrors The collection of errors to ignore
     */
    private function analyseFile(string $path, IgnoredErrors $ignoredErrors): void
    {
        $content = $this->read($path);

        if ($content === null) {
            $this->errorReporter->addGlobalError(sprintf('Cannot read: %s', $path));

            return;
        }

        foreach ($this->rules as $rule) {
            foreach ($rule->check($content) as $error) {
                if ($ignoredErrors->shouldIgnore($path, $error['message'])) {
                    continue;
                }
                $this->errorReporter->add($path, $error);
            }
        }
    }

    /**
     * Read file content.
     *
     * @param string $filePath Path to file
     * @return list<string>|null
     */
    private function read(string $filePath): ?array
    {
        if (!is_readable($filePath)) {
            return null;
        }

        $content = file($filePath, FILE_IGNORE_NEW_LINES);

        return $content === false ? null : $content;
    }
}
