<?php

namespace Realodix\Haiku\Support;

final class Arr
{
    /**
     * Flatten a multi-dimensional array by preserving top-level keys, extracting
     * all nested values regardless of depth, and removing duplicates.
     *
     * @param array<int|string, mixed> $array The array to flatten
     * @param array<string, mixed> $subKeys Optional whitelist of associative sub-keys to extract values from.
     *                                      If empty, all nested values are extracted.
     * @return list<string> The flattened array
     */
    public static function flattenWithKeys(array $array, array $subKeys = []): array
    {
        $flat = [];
        foreach ($array as $key => $value) {
            $flat[] = is_int($key) ? $value : $key;
            if (is_array($value)) {
                if ($subKeys) {
                    $value = array_filter($value, function ($subKey) use ($subKeys) {
                        // If the key is an integer (sequential array), let it pass.
                        // If the key is a string, it must be whitelisted in $subKeys.
                        return is_int($subKey) || in_array($subKey, $subKeys, true);
                    }, ARRAY_FILTER_USE_KEY);
                }

                array_walk_recursive($value, function ($item) use (&$flat) {
                    $flat[] = $item;
                });
            }
        }

        return array_values(array_unique($flat));
    }

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
