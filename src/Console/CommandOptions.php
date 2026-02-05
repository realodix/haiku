<?php

namespace Realodix\Haiku\Console;

final readonly class CommandOptions
{
    /**
     * @param string|null $cachePath Custom path to the cache file
     * @param string|null $configFile Custom path to the configuration file
     * @param bool $ignoreCache If true, the cache is ignored
     * @param string|null $path File or directory path to process
     */
    public function __construct(
        public ?string $cachePath = null,
        public ?string $configFile = null,
        public bool $ignoreCache = false,
        public ?string $path = null,
    ) {}
}
