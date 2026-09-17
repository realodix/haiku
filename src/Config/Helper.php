<?php

namespace Realodix\Haiku\Config;

use Realodix\Haiku\Support\Util;

final class Helper
{
    /**
     * Resolves and validates configuration overrides.
     *
     * @template T of array<string, mixed>
     *
     * @param T $baseConfig Current configuration array
     * @param array<string, mixed> $override Overrides to apply
     * @param string $type Type of configuration for error messages
     * @return T
     */
    public static function resolveOptions(array $baseConfig, array $override, string $type = 'flag'): array
    {
        // Acts as a bulk toggle for all boolean values
        if (array_key_exists('fmode', $override)) {
            $override['all'] = $override['fmode'];
            unset($override['fmode']);
        }

        if (array_key_exists('all', $override)) {
            $value = $override['all'];
            foreach ($baseConfig as $name => $defaultValue) {
                if (is_bool($defaultValue)) {
                    $baseConfig[$name] = $value;
                }
            }

            unset($override['all']);
        }

        // Apply specific overrides
        foreach ($override as $name => $value) {
            if (!array_key_exists($name, $baseConfig)) {
                $hint = Util::getSuggestion(array_merge(array_keys($baseConfig), ['all']), $name);

                throw new InvalidConfigurationException(sprintf(
                    'Unknown %s: "%s"'.($hint !== null ? ", did you mean '%s'?" : '.'),
                    $type, $name, $hint,
                ));
            }

            $baseConfig[$name] = $value;
        }

        return $baseConfig;
    }
}
