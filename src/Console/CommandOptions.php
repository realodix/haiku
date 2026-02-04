<?php

namespace Realodix\Haiku\Console;

final readonly class CommandOptions
{
    /**
     * @param string|null $cachePath Custom path to the cache file
     * @param string|null $configFile Custom path to the configuration file
     * @param bool $backup Create backup files before modifying
     * @param bool $ignoreCache If true, the cache is ignored
     * @param bool $keepEmptyLines Keep empty lines
     * @param string|null $path File or directory path to process
     * @param bool $xMode Enable experimental features
     */
    public function __construct(
        public ?string $cachePath = null,
        public ?string $configFile = null,
        public bool $backup = false,
        public bool $ignoreCache = false,
        public bool $keepEmptyLines = false,
        public ?string $path = null,
        public bool $xMode = false,
    ) {}
}
