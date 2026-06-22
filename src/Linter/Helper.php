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

    /**
     * @param \Realodix\Haiku\Config\LinterConfig $config
     */
    public static function hash(string $str, $config): string
    {
        $seed = collect($config->rules)
            ->reject(static fn($value) => $value === false)
            ->sortKeys()->toJson();

        return hash('xxh128', $str.$seed);
    }
}
