<?php

namespace Realodix\Haiku;

use Illuminate\Container\Container;

class App
{
    const NAME = 'Haiku';
    const VERSION = '1.7.x-dev';

    public static function version(): string
    {
        $v = explode('.', self::VERSION);
        $vPatch = $v[2];

        if ($vPatch === 'x-dev') {
            $cRef = Helper::getComposerRef();

            if ($cRef === null) {
                return self::VERSION;
            }

            $cRefShort = substr($cRef, 0, 7);

            return str_replace('-dev', '-'.$cRefShort, self::VERSION);
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
