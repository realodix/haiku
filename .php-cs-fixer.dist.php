<?php

use Realodix\Relax\Config;
use Realodix\Relax\Finder;

return Config::this()
    ->setFinder(Finder::base()->in(__DIR__))
    ->setCacheFile(__DIR__.'/.tmp/.php-cs-fixer.cache')
    ->setRules([
        '@Realodix/Relax' => true,
    ]);
