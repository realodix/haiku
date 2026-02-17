<?php

namespace Realodix\Haiku\Fixer\Classes;

/**
 * @phpstan-import-type FixerFlags from \Realodix\Haiku\Config\FixerConfig
 */
final class NetOptionTransformer
{
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

            $option = $this->migrateDeprecatedOptions($option);

            $result[] = $option;
        }

        return $result;
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
