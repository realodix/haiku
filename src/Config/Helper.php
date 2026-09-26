<?php

namespace Realodix\Haiku\Config;

use Realodix\Haiku\Support\Util;

final class Helper
{
    /**
     * Returns the absolute path to a configuration file.
     *
     * If no configuration file is specified, it defaults to the path of the
     * `haiku.yml` file.
     *
     * @param string|null $path Custom path to the configuration file
     */
    public static function resolveConfigPath(?string $path): ?string
    {
        if ($path !== null) {
            return base_path($path);
        }

        $discoverableConfigNames = [
            'haiku.yml',
            'haiku.yml.dist',
        ];

        foreach ($discoverableConfigNames as $filename) {
            $filepath = base_path($filename);
            if (is_file($filepath)) {
                return $filepath;
            }
        }

        return null;
    }

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
