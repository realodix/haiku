<?php

namespace Realodix\Haiku\Linter;

use Realodix\Haiku\Linter\Rules\Rule;
use Symfony\Component\Finder\Finder;

final class Helper
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
}
