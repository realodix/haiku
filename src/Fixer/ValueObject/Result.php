<?php

namespace Realodix\Haiku\Fixer\ValueObject;

/**
 * The result of a file processing
 */
final readonly class Result
{
    public function __construct(
        public string $path,
        public string $status, // 'processed', 'skipped', 'error'
        public ?string $hash = null,
    ) {}
}
