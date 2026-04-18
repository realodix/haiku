<?php

namespace Realodix\Haiku\Linter\Rules;

/**
 * @phpstan-import-type _RuleError from \Realodix\Haiku\Linter\RuleErrorBuilder
 */
interface Rule
{
    /**
     * @param list<string> $content Line content
     * @return list<_RuleError> $errors
     */
    public function check(array $content): array;
}
