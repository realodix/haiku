<?php

namespace Realodix\Haiku\Fixer\ValueObject;

final readonly class FixerRunContext
{
    /**
     * @param bool $ignoreCache If true, the cache is ignored
     * @param string|null $path File or directory path to process
     * @param string|null $cachePath Custom path to the cache file
     * @param string|null $configFile Custom path to the configuration file
     * @param bool $keepEmptyLines Keep empty lines
     * @param bool $xMode Enable experimental features
     */
    public function __construct(
        public bool $ignoreCache,
        public ?string $path,
        public ?string $cachePath,
        public ?string $configFile,
        public bool $keepEmptyLines,
        public bool $xMode,
    ) {}
}
