<?php

namespace Realodix\Haiku\Fixer\Classes;

/**
 * @phpstan-import-type _FixerFlags from \Realodix\Haiku\Config\FixerConfig
 */
final class NetOptionTransformer
{
    private const OPTION_CONVERSION = [
        'from' => 'domain',
        '1p' => 'first-party',
        '3p' => 'third-party',
        'strict1p' => 'strict-first-party',
        'strict3p' => 'strict-third-party',
        'css' => 'stylesheet',
        'doc' => 'document',
        'ehide' => 'elemhide',
        'frame' => 'subdocument',
        'ghide' => 'generichide',
        'shide' => 'specifichide',
        'xhr' => 'xmlhttprequest',
    ];
    private const DEPRECATED_OPTION_CONVERSION = [
        'empty' => 'redirect=nooptext',
        'mp4' => 'media,redirect=noopmp4-1s',
        'object-subrequest' => 'object',
        'queryprune' => 'removeparam',
    ];

    /** @var _FixerFlags */
    public array $flags;

    /**
     * Applies a set of dynamic rules to transform or remove a filter option.
     *
     * @param array<int, string> $options
     * @return array<int, string>
     */
    public function applyFix(array $options): array
    {
        $result = [];

        foreach ($options as $option) {
            // "_" is a noop modifier → drop entirely
            if (str_starts_with($option, '_')) {
                continue;
            }

            $option = $this->transformName($option);
            $option = $this->migrateDeprecatedOptions($option);

            $result[] = $option;
        }

        return $result;
    }

    /**
     * Transforms a filter option name according to the configured option format.
     *
     * Handles negation once, and replaces the option name with its short or
     * long name equivalent.
     */
    private function transformName(string $option): string
    {
        if ($this->flags['option_format'] === false) {
            return $option;
        }

        // Split into name and value (for $domain)
        [$rawName, $value] = explode('=', $option, 2) + [1 => null];

        // Handle negation once
        $negated = str_starts_with($rawName, '~');
        $name = $negated ? substr($rawName, 1) : $rawName;

        if ($this->flags['option_format'] === 'long') {
            $name = self::OPTION_CONVERSION[$name] ?? $name;
        }

        if ($this->flags['option_format'] === 'short') {
            static $reverse = null;

            if ($reverse === null) {
                $reverse = array_flip(self::OPTION_CONVERSION);
            }

            $name = $reverse[$name] ?? $name;
        }

        if ($negated) {
            $name = '~'.$name;
        }

        return $value !== null ? $name.'='.$value : $name;
    }

    /**
     * Migrates deprecated network filter options to their new equivalents.
     */
    private function migrateDeprecatedOptions(string $option): string
    {
        if (!$this->flags['migrate_deprecated_options']) {
            return $option;
        }

        // Split into name and value (for $queryprune)
        [$rawName, $value] = explode('=', $option, 2) + [1 => null];
        // Handle negation once
        $negated = str_starts_with($rawName, '~');
        $name = $negated ? substr($rawName, 1) : $rawName;

        $name = self::DEPRECATED_OPTION_CONVERSION[$name] ?? $name;
        if ($negated) {
            $name = '~'.$name;
        }

        return $value !== null ? $name.'='.$value : $name;
    }
}
