<?php

namespace Realodix\Haiku\Linter;

final class IgnoredErrors
{
    /** @var list<array{message?: string, path?: string}|string> */
    private array $normalizedIgnoreErrors;

    /** @var array<int, bool> */
    private array $usedIgnoreErrors = [];

    /**
     * @param list<mixed> $ignoreErrors
     */
    public function __construct(array $ignoreErrors)
    {
        $this->normalizedIgnoreErrors = $this->normalizeIgnoreErrors($ignoreErrors);
        $this->usedIgnoreErrors = array_fill_keys(array_keys($this->normalizedIgnoreErrors), false);
    }

    public function shouldIgnore(string $path, string $message): bool
    {
        $path = str_replace('\\', '/', $path);

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
            if (!$used) {
                $ignore = $this->normalizedIgnoreErrors[$index];
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
                    $pattern,
                    $inPath,
                ));
            }
        }
    }

    /**
     * @param list<mixed> $ignoreErrors
     * @return list<array{message?: string, path?: string}|string>
     */
    private function normalizeIgnoreErrors(array $ignoreErrors): array
    {
        $normalized = [];
        foreach ($ignoreErrors as $ignore) {
            if (is_string($ignore)) {
                $normalized[] = $ignore;

                continue;
            }

            if (is_object($ignore)) {
                $ignore = (array) $ignore;
            }
            if (!is_array($ignore)) {
                continue;
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
