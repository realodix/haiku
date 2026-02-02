<?php

namespace Realodix\Haiku\Fixer\ValueObject;

use Realodix\Haiku\Enums\Mode;

final readonly class FixerRunContext
{
    /**
     * @param \Realodix\Haiku\Enums\Mode $mode Processing mode
     * @param string|null $path File or directory path to process
     * @param string|null $cachePath Custom path to the cache file
     * @param string|null $configFile Custom path to the configuration file
     * @param bool $keepEmptyLines Keep empty lines
     * @param bool $xMode Enable experimental features
     */
    public function __construct(
        public Mode $mode,
        public ?string $path,
        public ?string $cachePath,
        public ?string $configFile,
        public bool $keepEmptyLines,
        public bool $xMode,
    ) {}
}
