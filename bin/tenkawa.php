#!/usr/bin/env php
<?php declare(strict_types=1);

use Recoil\Exception\StrandException;
use Recoil\React\ReactKernel;
use Tsufeki\Tenkawa\Server\Logger\CompositeLogger;
use Tsufeki\Tenkawa\Server\Logger\StreamLogger;
use Tsufeki\Tenkawa\Server\PluginFinder;
use Tsufeki\Tenkawa\Server\Tenkawa;
use Tsufeki\Tenkawa\Server\Transport\StreamTransport;
use Tsufeki\Tenkawa\Server\Utils\SyncAsyncKernel;

foreach ([__DIR__ . '/../../../autoload.php', __DIR__ . '/../autoload.php', __DIR__ . '/../vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        require_once $file;
        break;
    }
}

// --log=null|stderr|<filepath>
$logger = new CompositeLogger();
$log = (getopt('', ['log:'])['log'] ?? false) ?: 'stderr';
if ($log !== 'null') {
    $logger->add(new StreamLogger($log === 'stderr' ? STDERR : fopen($log, 'a')));
}

set_error_handler(function (int $severity, string $message, string $file, int $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }

    throw new \ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (\Throwable $e) use ($logger) {
    $logger->critical($e->getMessage(), ['exception' => $e]);
});

$plugins = (new PluginFinder())->findPlugins();
$kernel = new SyncAsyncKernel([ReactKernel::class, 'create']);

$kernel->setExceptionHandler(function (\Throwable $e) use ($logger) {
    if (!($e instanceof StrandException)) {
        throw $e;
    }

    $logger->error($e->getMessage(), ['exception' => $e->getPrevious()]);
});

stream_set_blocking(STDIN, false);
stream_set_blocking(STDOUT, false);
$transport = new StreamTransport(STDIN, STDOUT);
$server = new Tenkawa($logger, $kernel, $plugins);

$kernel->start($server->run($transport));
