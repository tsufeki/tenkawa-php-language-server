<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Recoil\React\ReactKernel;
use Recoil\Recoil;
use Tests\Tsufeki\Tenkawa\Fixtures\DummyTransport;
use Tsufeki\BlancheJsonRpc\Json;
use Tsufeki\Tenkawa\Php\PhpPlugin;
use Tsufeki\Tenkawa\Server\ServerPlugin;
use Tsufeki\Tenkawa\Server\Tenkawa;
use Tsufeki\Tenkawa\Server\Utils\SyncAsyncKernel;

/**
 * @covers \Tsufeki\Tenkawa\Server\Client
 * @covers \Tsufeki\Tenkawa\Server\ServerPlugin
 * @covers \Tsufeki\Tenkawa\Server\ServerPluginInit
 * @covers \Tsufeki\Tenkawa\Php\PhpPlugin
 * @covers \Tsufeki\Tenkawa\Php\PhpPluginInit
 * @covers \Tsufeki\Tenkawa\Server\Tenkawa
 * @covers \Tsufeki\Tenkawa\Server\Server
 */
class IntegrationTest extends TestCase
{
    public function test()
    {
        $kernel = new SyncAsyncKernel([ReactKernel::class, 'create']);
        $kernel->execute(function () use ($kernel) {
            $tenkawa = new Tenkawa(new NullLogger(), $kernel, [new ServerPlugin(), new PhpPlugin()]);
            $transport = new DummyTransport();

            $options = [
                'index.memory_only' => true,
                'index.stubs' => false,
                'log.client' => false,
            ];

            yield Recoil::execute($tenkawa->run($transport, $options));
            yield;
            yield;

            yield $transport->clientSend([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'rootUri' => 'file:///foo',
                    'capabilities' => new \stdClass(),
                    'trace' => 'off',
                ],
            ]);

            $resp = yield $transport->clientReceive();
            $this->assertJsonStringEqualsJsonString(Json::encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'capabilities' => [
                        'textDocumentSync' => [
                            'openClose' => true,
                            'change' => 1,
                            'willSave' => false,
                            'willSaveWaitUntil' => false,
                        ],
                        'hoverProvider' => true,
                        'completionProvider' => [
                            'resolveProvider' => false,
                            'triggerCharacters' => ['\\', '>', ':', '$'],
                        ],
                        'definitionProvider' => true,
                        'documentSymbolProvider' => true,
                        'codeActionProvider' => false,
                    ],
                ],
            ]), $resp);
        });
        $kernel->run();
    }
}
