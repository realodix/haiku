<?php

namespace Realodix\Haiku;

use Composer\InstalledVersions;
use Illuminate\Container\Container;

class App
{
    const NAME = 'Haiku';
    const VERSION = '1.10.1';

    public static function version(): string
    {
        if (str_contains(self::VERSION, '.x')) {
            $cRef = InstalledVersions::getReference('realodix/haiku');

            if ($cRef === null) {
                return self::VERSION;
            }

            $cRefShort = substr($cRef, 0, 7);

            return str_replace('x', "x ({$cRefShort})", self::VERSION);
        }

        return self::VERSION;
    }

    /**
     * Register any application services.
     */
    public function register(Container $app): void
    {
        //
    }
}
