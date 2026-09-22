<?php

use Realodix\Haiku\Console\Kernel;

require_once __DIR__.'/../vendor/autoload.php';

$kernel = new Kernel;
$kernel->registerServices();

return $kernel;
