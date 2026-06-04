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
    private array $ignorePatterns;

    /**
     * Tracks whether each pattern has been used at least once
     *
     * @var array<int, bool>
     */
    private array $patternMatched = [];

    /**
     * Number of times each pattern has been matched (for 'count' limits)
     *
     * @var array<int, int>
     */
    private array $patternMatchCount = [];

    /**
     * @param list<_ConfigIgnoredError> $configPatterns Ignore patterns from user configuration
     * @param list<_ConfigIgnoredError> $basePatterns Ignore patterns from baseline file
     */
    public function __construct(array $configPatterns, array $basePatterns = [])
    {
        $this->ignorePatterns = array_merge(
            $this->normalizeIgnorePatterns($configPatterns),
            $this->normalizeIgnorePatterns($basePatterns, isBaseline: true),
        );

        foreach ($this->ignorePatterns as $index => $_) {
            $this->patternMatched[$index] = false;
            $this->patternMatchCount[$index] = 0;
        }
    }

    /**
     * Load ignored errors from configuration and baseline.
     *
     * @param \Realodix\Haiku\Config\LinterConfig $config
     * @param \Realodix\Haiku\Console\CommandOptions $cmdOpt CLI runtime options
     */
    public static function load($config, $cmdOpt): self
    {
        $basePatterns = [];
        $baselineFile = base_path('haiku-baseline.yml');

        if (!$cmdOpt->generateBaseline && file_exists($baselineFile)) {
            $baseline = Yaml::parseFile($baselineFile);
            $basePatterns = $baseline['ignoreErrors'] ?? [];
        }

        return new self($config->ignoreErrors, $basePatterns);
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
        foreach ($this->ignorePatterns as $index => $pattern) {
            if (is_string($pattern)) {
                if ($this->isMatch($pattern, $message)) {
                    $this->patternMatchCount[$index]++;
                    $this->patternMatched[$index] = true;

                    return true;
                }

                continue;
            }

            $msgMatch = !isset($pattern['message']) || $this->isMatch($pattern['message'], $message);
            $pathMatch = !isset($pattern['path']) || $this->isMatch($pattern['path'], $path);

            if ($msgMatch && $pathMatch) {
                if (isset($pattern['count']) && $this->patternMatchCount[$index] >= $pattern['count']) {
                    continue;
                }

                $this->patternMatchCount[$index]++;
                $this->patternMatched[$index] = true;

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
        foreach ($this->patternMatched as $index => $used) {
            $pattern = $this->ignorePatterns[$index];

            if ($used || (is_array($pattern) && isset($pattern['isBaseline']))) {
                continue;
            }

            $patternDesc = '';
            $locDesc = '';

            if (is_string($pattern)) {
                $patternDesc = $pattern;
            } else {
                if (isset($pattern['message'])) {
                    $patternDesc = $pattern['message'];
                }
                if (isset($pattern['path'])) {
                    $locDesc = (isset($pattern['message']) ? ' ' : '').'in path '.$pattern['path'];
                }
            }

            $reporter->addGlobalError(sprintf(
                'Ignored error pattern %s%s was not matched in reported errors.',
                $patternDesc, $locDesc,
            ));
        }
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

    /**
     * Converts ignore pattern definitions into normalized runtime entries.
     *
     * @param list<_ConfigIgnoredError> $patterns Ignore pattern definitions from configuration.
     * @param bool $isBaseline Whether the patterns originate from the baseline file.
     * @return list<_IgnoredError> Normalized ignore patterns
     */
    private function normalizeIgnorePatterns(array $patterns, bool $isBaseline = false): array
    {
        $normalized = [];
        foreach ($patterns as $pattern) {
            if (is_string($pattern)) {
                $normalized[] = $pattern;

                continue;
            }

            if ($isBaseline) {
                $pattern['isBaseline'] = true;
            }

            $messages = [];
            if (isset($pattern['message'])) {
                $messages[] = $pattern['message'];
            }
            if (isset($pattern['messages'])) {
                $messages = array_merge($messages, (array) $pattern['messages']);
            }

            $paths = [];
            if (isset($pattern['path'])) {
                $paths[] = $pattern['path'];
            }
            if (isset($pattern['paths'])) {
                $paths = array_merge($paths, (array) $pattern['paths']);
            }

            if (empty($messages) && empty($paths)) {
                continue;
            }

            $base = $pattern;
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
}
