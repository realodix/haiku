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

    /**
     * @param \Realodix\Haiku\Config\FixerConfig $config
     */
    public static function hash(string $str, $config): string
    {
        $seed = collect($config->flags)
            ->reject(static fn($value) => $value === false || $value === null)
            ->sortKeys()->toJson();

        return hash('xxh128', $str.$seed);
    }
}
