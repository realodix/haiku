<?php

namespace Realodix\Haiku;

use Composer\InstalledVersions;
use Illuminate\Container\Container;

/**
 * @codeCoverageIgnore
 */
class App
{
    const NAME = 'Haiku';
    const VERSION = '1.13.18-dev';

    public static function version(): string
    {
        $version = 'v'.self::VERSION;

        if (str_ends_with($version, '-dev')) {
            $cRef = InstalledVersions::getReference('realodix/haiku');

            if ($cRef === null) {
                return $version;
            }

            $cRefShort = substr($cRef, 0, 7);
            $version = str_replace('-dev', "-dev ({$cRefShort})", $version);
        }

        return $version;
    }

    /**
     * Register any application services.
     */
    public function register(Container $app): void
    {
        $app->singleton(\Realodix\Haiku\Config\Config::class);
        $app->singleton(\Realodix\Haiku\Config\FixerConfig::class);
        $app->singleton(\Realodix\Haiku\Config\LinterConfig::class);

        // parallel processing
        $app->singleton(\Realodix\Haiku\Cache\Cache::class);
    }
}
