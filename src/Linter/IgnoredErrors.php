<?php

namespace Realodix\Haiku\Linter;

use Symfony\Component\Yaml\Yaml;

/**
 * @phpstan-import-type _ConfigIgnoredError from \Realodix\Haiku\Config\LinterConfig
 * @phpstan-type _IgnoredError array{
 *  message?: string,
 *  path?: string,
 *  count?: int,
 *  isBaseline?: bool,
 * }|string
 */
final class IgnoredErrors
{
    /** @var list<_IgnoredError> */
    private array $normalizedIgnoreErrors;

    /** @var array<int, bool> */
    private array $usedIgnoreErrors = [];

    /** @var array<int, int> */
    private array $usedCount = [];

    /**
     * @param list<_ConfigIgnoredError> $ignoreErrors
     * @param list<_ConfigIgnoredError> $baselineErrors
     */
    public function __construct(array $ignoreErrors, array $baselineErrors = [])
    {
        $this->normalizedIgnoreErrors = array_merge(
            $this->normalizeIgnoreErrors($ignoreErrors),
            $this->normalizeIgnoreErrors($baselineErrors, isBaseline: true),
        );
        $this->usedIgnoreErrors = array_fill_keys(array_keys($this->normalizedIgnoreErrors), false);
        $this->usedCount = array_fill_keys(array_keys($this->normalizedIgnoreErrors), 0);
    }

    /**
     * Load ignored errors from configuration and baseline.
     *
     * @param \Realodix\Haiku\Config\LinterConfig $config
     * @param \Realodix\Haiku\Console\CommandOptions $cmdOpt CLI runtime options
     */
    public static function load($config, $cmdOpt): self
    {
        $baselineErrors = [];
        $baselineFile = base_path('haiku-baseline.yml');

        if (!$cmdOpt->generateBaseline && file_exists($baselineFile)) {
            $baseline = Yaml::parseFile($baselineFile);
            $baselineErrors = $baseline['ignoreErrors'] ?? [];
        }

        return new self($config->ignoreErrors, $baselineErrors);
    }

    /**
     * Check if an error should be ignored.
     *
     * @param string $path The path to the file
     * @param string $message The error message
     * @return bool True if the error should be ignored, false otherwise
     */
    public function shouldIgnore(string $path, string $message): bool
    {
        foreach ($this->normalizedIgnoreErrors as $index => $ignore) {
            if (is_string($ignore)) {
                if ($this->isMatch($ignore, $message)) {
                    $this->usedCount[$index]++;
                    $this->usedIgnoreErrors[$index] = true;

                    return true;
                }

                continue;
            }

            $messageMatch = !isset($ignore['message']) || $this->isMatch($ignore['message'], $message);
            $pathMatch = !isset($ignore['path']) || $this->isMatch($ignore['path'], $path);

            if ($messageMatch && $pathMatch) {
                if (isset($ignore['count']) && $this->usedCount[$index] >= $ignore['count']) {
                    continue;
                }

                $this->usedCount[$index]++;
                $this->usedIgnoreErrors[$index] = true;

                return true;
            }
        }

        return false;
    }

    /**
     * Report any ignore patterns that were not matched. This is useful for
     * identifying stale ignore patterns.
     */
    public function reportUnmatched(ErrorReporter $reporter): void
    {
        foreach ($this->usedIgnoreErrors as $index => $used) {
            $ignore = $this->normalizedIgnoreErrors[$index];

            if (!$used && !(is_array($ignore) && isset($ignore['isBaseline']))) {
                $pattern = '';
                $inPath = '';

                if (is_string($ignore)) {
                    $pattern = $ignore;
                } else {
                    if (isset($ignore['message'])) {
                        $pattern = $ignore['message'];
                    }
                    if (isset($ignore['path'])) {
                        $inPath = 'in path '.$ignore['path'];

                        if (isset($ignore['message'])) {
                            $inPath = ' in path '.$ignore['path'];
                        }
                    }
                }

                $reporter->addGlobalError(sprintf(
                    'Ignored error pattern %s%s was not matched in reported errors.',
                    $pattern, $inPath,
                ));
            }
        }
    }

    /**
     * Normalize the ignore errors array.
     *
     * This expands single ignore patterns to multiple entries if needed.
     *
     * @param list<_ConfigIgnoredError> $ignoreErrors Array of ignore patterns
     * @param bool $isBaseline Whether this is for baseline
     * @return list<_IgnoredError> Normalized ignore errors
     */
    private function normalizeIgnoreErrors(array $ignoreErrors, bool $isBaseline = false): array
    {
        $normalized = [];
        foreach ($ignoreErrors as $ignore) {
            if (is_string($ignore)) {
                $normalized[] = $ignore;

                continue;
            }

            if ($isBaseline) {
                $ignore['isBaseline'] = true;
            }

            $messages = [];
            if (isset($ignore['message'])) {
                $messages[] = $ignore['message'];
            }
            if (isset($ignore['messages'])) {
                $messages = array_merge($messages, (array) $ignore['messages']);
            }

            $paths = [];
            if (isset($ignore['path'])) {
                $paths[] = $ignore['path'];
            }
            if (isset($ignore['paths'])) {
                $paths = array_merge($paths, (array) $ignore['paths']);
            }

            if (empty($messages) && empty($paths)) {
                continue;
            }

            $base = $ignore;
            unset($base['message'], $base['messages'], $base['path'], $base['paths']);

            if (empty($messages)) {
                foreach ($paths as $p) {
                    $normalized[] = array_merge($base, ['path' => $p]);
                }
            } elseif (empty($paths)) {
                foreach ($messages as $m) {
                    $normalized[] = array_merge($base, ['message' => $m]);
                }
            } else {
                foreach ($messages as $m) {
                    foreach ($paths as $p) {
                        $normalized[] = array_merge($base, ['message' => $m, 'path' => $p]);
                    }
                }
            }
        }

        return $normalized;
    }

    /**
     * Check if a value matches a pattern.
     *
     * @param string $pattern The pattern to match
     * @param string $value The value to match
     * @return bool True if the value matches the pattern, false otherwise
     */
    private function isMatch(string $pattern, string $value): bool
    {
        if (str_contains($value, $pattern)) {
            return true;
        }
        if (@preg_match($pattern, '') !== false && @preg_match($pattern, $value) === 1) {
            return true;
        }

        return false;
    }
}
