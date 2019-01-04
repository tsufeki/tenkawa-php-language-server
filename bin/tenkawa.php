#!/usr/bin/env php
<?php

use Composer\XdebugHandler\XdebugHandler;
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

$xdebug = new XdebugHandler('tenkawa');
$xdebug->check();
unset($xdebug);

$requiredExtensions = [
    'pdo_sqlite' => 2,
    'mbstring' => 3,
];

foreach ($requiredExtensions as $ext => $errorCode) {
    if (!extension_loaded($ext)) {
        fprintf(STDERR, "Tenkawa requires $ext extension\n");
        exit($errorCode);
    }
}
unset($requiredExtensions, $ext, $errorCode);

if (!class_exists(Tenkawa::class)) {
    fprintf(STDERR, "Tenkawa was not properly installed\n");
    exit(9);
}

Tenkawa::main($argv);
