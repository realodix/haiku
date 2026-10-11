<?php

namespace Realodix\Haiku\Fixer;

final class Helper
{
    /**
     * @param array<int, string> $lines
     */
    public static function joinLines(array $lines): string
    {
        return implode("\n", $lines)."\n";
    }
}
