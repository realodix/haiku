<?php

namespace Realodix\Haiku\Fixer\Classes;

/**
 * @phpstan-import-type FixerFlags from \Realodix\Haiku\Config\FixerConfig
 */
final class NetOptionTransformer
{
    /** @var array<string, string> */
    private const OPTION_CONVERSION = [
        'domain' => 'from',
        'first-party' => '1p',
        'third-party' => '3p',
        'strict-first-party' => 'strict1p',
        'strict-third-party' => 'strict3p',
        'document' => 'doc',
        'elemhide' => 'ehide',
        'generichide' => 'ghide',
        'specifichide' => 'shide',
        'stylesheet' => 'css',
        'subdocument' => 'frame',
        'xmlhttprequest' => 'xhr',
    ];

    /** @var FixerFlags */
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

        if ($this->flags['option_format'] === 'short') {
            $name = self::OPTION_CONVERSION[$name] ?? $name;
        }

        if ($this->flags['option_format'] === 'long') {
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
        if (!($this->flags['xmode'] && !$this->flags['migrate_deprecated_options'])) {
            return $option;
        }

        // https://github.com/gorhill/uBlock/wiki/Static-filter-syntax#empty
        // https://adguard.com/kb/general/ad-filtering/create-own-filters/#empty-modifier
        if ($option === 'empty') {
            return 'redirect=nooptext';
        }

        // https://github.com/gorhill/uBlock/wiki/Static-filter-syntax#mp4
        // https://adguard.com/kb/general/ad-filtering/create-own-filters/#mp4-modifier
        if ($option === 'mp4') {
            return 'media,redirect=noopmp4-1s';
        }

        // https://adguard.com/kb/general/ad-filtering/create-own-filters/#object-subrequest-modifier
        // https://github.com/gorhill/uBlock/blob/f1689a9ab/src/js/static-filtering-parser.js#L263
        if ($option === 'object-subrequest') {
            return 'object';
        }

        // https://github.com/gorhill/uBlock/wiki/Static-filter-syntax#removeparam
        // https://adguard.com/kb/general/ad-filtering/create-own-filters/#removeparam-modifier
        if (str_starts_with($option, 'queryprune')) {
            return str_replace('queryprune', 'removeparam', $option);
        }

        return $option;
    }
}
