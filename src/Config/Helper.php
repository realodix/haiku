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
    public static function resolveOverrides(array $baseConfig, array $override, string $type = 'flag'): array
    {
        // 'fmode' acts as a bulk toggle for all boolean values
        if (array_key_exists('fmode', $override)) {
            $value = (bool) $override['fmode'];
            foreach ($baseConfig as $name => $defaultValue) {
                if (is_bool($defaultValue)) {
                    $baseConfig[$name] = $value;
                }
            }
            unset($override['fmode']);
        }
        // Apply specific overrides
        foreach ($override as $name => $value) {
            if (!array_key_exists($name, $baseConfig)) {
                $hint = Util::getSuggestion(array_merge(array_keys($baseConfig), ['fmode']), $name);
                throw new InvalidConfigurationException(sprintf(
                    'Unknown %s: "%s"'.($hint ? ", did you mean '%s'?" : '.'),
                    $type, $name, $hint,
                ));
            }
            $baseConfig[$name] = $value;
        }

        return $baseConfig;
    }
}
