<?php

namespace Realodix\Haiku\Linter;

/**
 * @phpstan-import-type _RuleError from RuleErrorBuilder
 */
final class ErrorReporter
{
    /** @var array<string, list<_RuleError>> */
    private array $errors = [];

    /**
     * @param string $path File path
     * @param _RuleError $error
     */
    public function add(string $path, array $error): void
    {
        $this->errors[$path][] = $error;
    }

    /**
     * @return array<string, list<_RuleError>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function count(): int
    {
        return array_sum(array_map('count', $this->errors));
    }
}
