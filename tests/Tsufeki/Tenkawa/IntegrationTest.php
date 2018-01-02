<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa;

use PHPUnit\Framework\TestCase;
use Recoil\React\ReactKernel;
use Recoil\Recoil;
use Tests\Tsufeki\Tenkawa\Fixtures\DummyTransport;
use Tsufeki\BlancheJsonRpc\Json;
use Tsufeki\Tenkawa\CorePlugin;
use Tsufeki\Tenkawa\Tenkawa;

/**
 * @covers \Tsufeki\Tenkawa\Client
 * @covers \Tsufeki\Tenkawa\CorePlugin
 * @covers \Tsufeki\Tenkawa\CorePluginInit
 * @covers \Tsufeki\Tenkawa\Tenkawa
 * @covers \Tsufeki\Tenkawa\Server
 */
class IntegrationTest extends TestCase
{
    public function test()
    {
        $kernel = ReactKernel::create();
        $kernel->execute(function () use ($kernel) {
            $transport = new DummyTransport();
            $tenkawa = new Tenkawa();

            yield Recoil::execute($tenkawa->run($transport, $kernel, [new CorePlugin()]));
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
                            'save' => null,
                        ],
                        'definitionProvider' => true,
                    ],
                ],
            ]), $resp);
        });
        $kernel->run();
    }
}
