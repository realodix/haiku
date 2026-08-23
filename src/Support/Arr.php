<?php

namespace Realodix\Haiku\Support;

final class Arr
{
    /**
     * Sort the array using the given callback.
     *
     * @param array<int, string> $values
     * @return array<int, string>
     */
    public static function sortBy(array $values, ?callable $callback, ?int $flags = null): array
    {
        $results = [];
        foreach ($values as $key => $value) {
            $results[$key] = $callback($value, $key);
        }

        asort($results, $flags ?? SORT_REGULAR);
        foreach (array_keys($results) as $key) {
            $results[$key] = $values[$key];
        }

        return $results;
    }

    /**
     * Sort the array using the given callback and remove duplicates.
     *
     * @param array<int, string> $value
     * @return list<string>
     */
    public static function uniqueSortBy(array $value, ?callable $callback, ?int $flags = null): array
    {
        $v = array_filter($value, static fn($s) => $s !== '');
        $v = array_unique($v);
        $v = self::sortBy($v, $callback, $flags);

        return array_values($v);
    }
}
