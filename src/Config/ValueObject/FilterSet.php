<?php

namespace Realodix\Haiku\Config\ValueObject;

/**
 * A filter list configuration
 */
final readonly class FilterSet
{
    /**
     * @param string $outputPath The path to the output file
     * @param array<int, string> $source The source files for the filter list
     * @param bool $unique Removes duplicate filter rules
     */
    public function __construct(
        public string $outputPath,
        public string $header,
        public array $source,
        public bool $unique,
    ) {}
}
