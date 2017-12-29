#!/usr/bin/env php
<?php declare(strict_types=1);

use Recoil\Exception\StrandException;
use Recoil\React\ReactKernel;
use Tsufeki\Tenkawa\PluginFinder;
use Tsufeki\Tenkawa\Tenkawa;
use Tsufeki\Tenkawa\Transport\StreamTransport;

foreach ([__DIR__ . '/../../../autoload.php', __DIR__ . '/../autoload.php', __DIR__ . '/../vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        require_once $file;
        break;
    }
}

set_error_handler(function (int $severity, string $message, string $file, int $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }

    throw new \ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (\Throwable $e) {
    fwrite(STDERR, (string)$e);
});

$plugins = (new PluginFinder())->findPlugins();

stream_set_blocking(STDIN, false);
stream_set_blocking(STDOUT, false);

$kernel = ReactKernel::create();

$kernel->setExceptionHandler(function (\Throwable $e) {
    if (!($e instanceof StrandException)) {
        throw $e;
    }

    fwrite(STDERR, (string)$e->getPrevious());
});

$kernel->execute(function () use ($kernel, $plugins): \Generator {
    $transport = new StreamTransport(STDIN, STDOUT);
    $server = new Tenkawa();

    yield $server->run($transport, $kernel, $plugins);
});

$kernel->run();
