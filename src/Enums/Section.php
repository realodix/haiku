<?php

namespace Realodix\Haiku\Enums;

enum Section: string
{
    case L = 'linter';
    case B = 'builder';
    case F = 'fixer';
}
