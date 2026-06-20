<?php

namespace Realodix\Haiku\Linter;

use Realodix\Haiku\Linter\Rules\Rule;
use Symfony\Component\Finder\Finder;

final class Util
{
    /**
     * @return list<Rule>
     */
    public static function loadLinterRules(): array
    {
        $rules = [];
        $finder = new Finder;
        $finder->files()->in(__DIR__.'/Rules')->name('*Check.php');

        foreach ($finder as $file) {
            $subPath = $file->getRelativePath();
            $subNamespace = $subPath !== '' ? str_replace('/', '\\', $subPath).'\\' : '';
            $class = 'Realodix\Haiku\Linter\Rules\\'.$subNamespace.$file->getBasename('.php');

            if (class_exists($class) && is_subclass_of($class, Rule::class)) {
                $rules[] = app($class);
            }
        }

        return $rules;
    }

    public static function isCommentOrEmpty(string $str): bool
    {
        return $str === '' || str_starts_with($str, '!');
    }

    /**
     * @return list<string>
     */
    public static function splitOptions(string $optionString): array
    {
        $knownOptions = array_merge(Registry::OPTIONS, Registry::AG_OPTIONS, [',']);
        $pattern = '/,(?=(?:\s|~)?('.implode('|', $knownOptions).')\b|$)/i';

        return preg_split($pattern, $optionString);
    }

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
}
