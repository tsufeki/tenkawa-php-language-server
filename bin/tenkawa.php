#!/usr/bin/env php
<?php

use Tsufeki\Tenkawa\Server\Tenkawa;

if (PHP_MAJOR_VERSION !== 7 || PHP_MINOR_VERSION < 1) {
    fprintf(STDERR, "Tenkawa requires PHP >= 7.1\n");
    exit(1);
}

foreach ([__DIR__ . '/../../../autoload.php', __DIR__ . '/../autoload.php', __DIR__ . '/../vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        require_once $file;
        break;
    }
}
unset($file);

Tenkawa::main($argv);
