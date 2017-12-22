<?php declare(strict_types=1);

use Recoil\React\ReactKernel;
use Tsufeki\Tenkawa\PluginFinder;
use Tsufeki\Tenkawa\Server;
use Tsufeki\Tenkawa\Transport\StreamTransport;

foreach ([__DIR__ . '/../../../autoload.php', __DIR__ . '/../autoload.php', __DIR__ . '/../vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        require_once $file;
        break;
    }
}

$plugins = (new PluginFinder())->findPlugins();

stream_set_blocking(STDIN, false);
stream_set_blocking(STDOUT, false);

$kernel = ReactKernel::create();

$kernel->execute(function () use ($plugins): \Generator {
    $transport = new StreamTransport(STDIN, STDOUT);
    $server = new Server();

    yield $server->run($transport, $plugins);
});

$kernel->run();
