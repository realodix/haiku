<?php

namespace Realodix\Haiku\Linter;

use Realodix\Haiku\Config\Config;
use Realodix\Haiku\Linter\Rules\Rule;

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
     * @param \Realodix\Haiku\Console\CommandOptions $cmdOpt CLI runtime options
     */
    public function run($cmdOpt, ?callable $onStart = null, ?callable $onAdvance = null): ErrorReporter
    {
        $config = $this->config->linter($cmdOpt);
        $ignoredErrors = new IgnoredErrors($config->ignoreErrors);

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
