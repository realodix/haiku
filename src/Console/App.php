<?php

namespace Realodix\Haiku\Console;

use Composer\InstalledVersions as Composer;
use Realodix\Haiku\Config\Helper;

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

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $io
     */
    public static function about($io, ?string $iConfig = null): void
    {
        $io->writeln(sprintf(
            '%s <info>%s</info> by <comment>Realodix</comment>',
            self::NAME, self::version()),
        );

        $conf = Helper::resolveConfigPath($iConfig);
        if ($conf !== null) {
            $io->writeln(sprintf('Loaded config from "%s"', $conf));
        }
    }
}
