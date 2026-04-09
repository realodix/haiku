<?php

namespace Realodix\Haiku\Linter;

/**
 * @phpstan-import-type _RuleError from RuleErrorBuilder
 */
final class ErrorReporter
{
    /** @var array<string, list<_RuleError>> */
    private array $errors = [];

    /** @var list<string> */
    private array $globalErrors = [];

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

    public function addGlobalError(string $message): void
    {
        $this->globalErrors[] = $message;
    }

    /**
     * @return list<string>
     */
    public function getGlobalErrors(): array
    {
        return $this->globalErrors;
    }

    public function count(): int
    {
        return array_sum(array_map('count', $this->errors)) + count($this->globalErrors);
    }
}
