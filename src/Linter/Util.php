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
        $finder->files()->in(base_path('src/Linter/Rules'))->name('*Check.php');

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
     * Flattens a nested array structure to a single level array.
     *
     * @param array<int|string, mixed> $data The array to flatten
     * @return list<string> The flattened array
     */
    public static function flatten(array $data): array
    {
        $flat = [];
        foreach ($data as $key => $value) {
            $flat[] = is_int($key) ? $value : $key;

            if (!is_array($value)) {
                continue;
            }

            foreach ($value as $v) {
                foreach ((array) $v as $item) {
                    $flat[] = $item;
                }
            }
        }

        return array_values(array_unique($flat));
    }

    /**
     * @param array<int|string, mixed> $data
     * @param list<string>|null $includeKeys
     * @return list<string>
     */
    public static function flattenWithFilter(array $data, ?array $includeKeys = null): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            $flat[] = is_int($key) ? $value : $key;

            if (!is_array($value)) {
                continue;
            }

            foreach ($value as $type => $v) {
                // filter hanya jika associative (grouped)
                if (!is_int($type) && $includeKeys !== null && !in_array($type, $includeKeys, true)) {
                    continue;
                }

                foreach ((array) $v as $item) {
                    $flat[] = $item;
                }
            }
        }

        return array_values(array_unique($flat));
    }
}
