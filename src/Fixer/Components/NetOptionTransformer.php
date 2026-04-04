<?php

namespace Realodix\Haiku\Fixer\Components;

use Realodix\Haiku\Config\FixerConfig;

/**
 * Transforms and normalizes network filter options.
 */
final class NetOptionTransformer
{
    private const BASE_OPTION_CONVERSION = [
        'from' => 'domain',
        '3p' => 'third-party',
        'css' => 'stylesheet',
        'doc' => 'document',
        'ehide' => 'elemhide',
        'frame' => 'subdocument',
        'ghide' => 'generichide',
        'xhr' => 'xmlhttprequest',
    ];

    private const SHORTLONG_OPTION_EXTRA_CONVERSION = [
        '1p' => 'first-party',
        'strict1p' => 'strict-first-party',
        'strict3p' => 'strict-third-party',
        'shide' => 'specifichide',
    ];

    private const NATIVE_OPTION_EXTRA_CONVERSION = [
        '1p' => '~third-party',
        'first-party' => '~third-party',
    ];

    private const DEPRECATED_OPTION_CONVERSION = [
        'empty' => 'redirect=nooptext',
        'mp4' => 'media,redirect=noopmp4-1s',
        'object-subrequest' => 'object',
        'queryprune' => 'removeparam',
    ];

    public function __construct(
        private FixerConfig $config,
    ) {}

    /**
     * Transforms a list of network filter options.
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
     * Normalizes an option name according to the configured format.
     *
     * @param string $option Single option token
     * @return string Normalized option token
     */
    private function transformName(string $option): string
    {
        $format = $this->config->flags['option_format'];

        if ($format === null) {
            return $option;
        }

        // Split into name and value (e.g. $domain=example.com)
        [$rawName, $value] = explode('=', $option, 2) + [1 => null];
        // Handle negation once
        $negated = str_starts_with($rawName, '~');
        $name = $negated ? substr($rawName, 1) : $rawName;

        // Build map
        static $longMap = null;
        static $nativeMap = null;
        static $shortMap = null;
        if ($longMap === null) {
            $base = self::BASE_OPTION_CONVERSION;
            $longMap = $base + self::SHORTLONG_OPTION_EXTRA_CONVERSION;
            $nativeMap = $base + self::NATIVE_OPTION_EXTRA_CONVERSION;
            $shortMap = array_flip($longMap);
        }

        // Apply transformation
        if ($format === 'long') {
            $name = $longMap[$name] ?? $name;
        } elseif ($format === 'short') {
            // Special semantic
            if ($negated && $name === 'third-party') {
                $name = '1p';
                $negated = false;
            } else {
                $name = $shortMap[$name] ?? $name;
            }
        } elseif ($format === 'native') {
            // Special semantics
            if ($name === '1p' || $name === 'first-party') {
                if ($negated) {
                    // ~1p → third-party
                    $name = 'third-party';
                    $negated = false;
                } else {
                    // 1p → ~third-party
                    $name = '~third-party';
                }
            } else {
                // normal mapping
                $name = $nativeMap[$name] ?? $name;
            }
        }

        if ($negated && !str_starts_with($name, '~')) {
            $name = '~'.$name;
        }

        // Rebuild option
        return $value !== null ? $name.'='.$value : $name;
    }

    /**
     * Migrates deprecated network filter options to their modern equivalents.
     *
     * @param string $option Single option token
     * @return string Migrated option token
     */
    private function migrateDeprecatedOptions(string $option): string
    {
        if (!$this->config->flags['migrate_deprecated_options']) {
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
