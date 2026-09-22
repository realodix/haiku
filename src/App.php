<?php

namespace Realodix\Haiku;

use Composer\InstalledVersions as Composer;

/**
 * @codeCoverageIgnore
 */
class App
{
    const NAME = 'Haiku';
    const VERSION = '1.13.x';

    public static function version(): string
    {
        $version = 'v'.self::VERSION;
        $cVer = Composer::getPrettyVersion('realodix/haiku');
        $cRef = Composer::getReference('realodix/haiku');

        if ($cVer === null || $cRef === null) {
            return $version;
        }

        if (str_starts_with($cVer, 'dev-')) {
            $cRefShort = substr($cRef, 0, 7);

            return str_replace('.x', ".x ({$cRefShort})", $version);
        }

        return $cVer;
    }
}
