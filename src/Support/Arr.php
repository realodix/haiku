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
    public static function sortBy(array $values, callable $callback, ?int $flags = null): array
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
    public static function uniqueSortBy(array $value, callable $callback, ?int $flags = null): array
    {
        $v = array_filter($value, static fn($s) => $s !== '');
        $v = array_unique($v);
        $v = self::sortBy($v, $callback, $flags);

        return array_values($v);
    }

    /**
     * @template T of array<string, mixed>
     *
     * @param T $input
     * @param array<string, string> $keyMap
     * @return T
     *
     * @throws \InvalidArgumentException
     */
    public static function renameKey(array $input, array $keyMap): array
    {
        foreach ($keyMap as $key => $newKey) {
            if (!array_key_exists($key, $input)) {
                continue;
            }

            if (array_key_exists($newKey, $input)) {
                throw new \InvalidArgumentException(
                    sprintf('Keys "%s" and "%s" cannot coexist.', $key, $newKey),
                );
            }

            $input[$newKey] = $input[$key];
            unset($input[$key]);
        }

        return $input;
    }
}
