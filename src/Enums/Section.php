<?php

namespace Realodix\Haiku\Enums;

enum Section: string
{
    case L = 'linter';
    case B = 'bundler';
    case F = 'formatter';
}
