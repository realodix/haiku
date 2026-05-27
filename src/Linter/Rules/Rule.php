<?php

namespace Realodix\Haiku\Linter\Rules;

/**
 * @phpstan-import-type _RuleError from \Realodix\Haiku\Linter\RuleErrorBuilder
 */
interface Rule
{
    /**
     * @param list<string> $content Line content
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @return list<_RuleError> $errors
     */
    public function check(array $content, $err): array;
}
