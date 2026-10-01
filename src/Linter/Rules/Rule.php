<?php

namespace Realodix\Haiku\Linter\Rules;

/**
 * @phpstan-import-type _RuleError from \Realodix\Haiku\Linter\ErrorBuilder
 */
interface Rule
{
    /**
     * @param array<int, string> $content Line content
     * @param \Realodix\Haiku\Linter\ErrorBuilder $err
     * @return list<_RuleError> $errors
     */
    public function check(array $content, $err): array;
}
