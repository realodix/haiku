<?php

namespace Realodix\Haiku\Linter;

/**
 * @phpstan-import-type _ConfigIgnoredError from \Realodix\Haiku\Config\LinterConfig
 * @phpstan-type _IgnoredError array{
 *  message?: string,
 *  path?: string,
 *  isBaseline?: bool,
 * }|string
 */
final class IgnoredErrors
{
    /** @var list<_IgnoredError> */
    private array $normalizedIgnoreErrors;

    /** @var array<int, bool> */
    private array $usedIgnoreErrors = [];

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
    }

    public function shouldIgnore(string $path, string $message): bool
    {
        foreach ($this->normalizedIgnoreErrors as $index => $ignore) {
            if (is_string($ignore)) {
                if ($this->isMatch($ignore, $message)) {
                    $this->usedIgnoreErrors[$index] = true;

                    return true;
                }

                continue;
            }

            $messageMatch = !isset($ignore['message']) || $this->isMatch($ignore['message'], $message);
            $pathMatch = !isset($ignore['path']) || $this->isMatch($ignore['path'], $path);

            if ($messageMatch && $pathMatch) {
                $this->usedIgnoreErrors[$index] = true;

                return true;
            }
        }

        return false;
    }

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
     * @param list<_ConfigIgnoredError> $ignoreErrors
     * @return list<_IgnoredError>
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
