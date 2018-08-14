<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa;

use Psr\Log\LogLevel;
use Tsufeki\BlancheJsonRpc\Dispatcher\SimpleMethodRegistry;
use Tsufeki\BlancheJsonRpc\JsonRpc;
use Tsufeki\Tenkawa\Php\PhpPlugin;
use Tsufeki\Tenkawa\Server\Logger\LevelFilteringLogger;
use Tsufeki\Tenkawa\Server\Logger\StreamLogger;
use Tsufeki\Tenkawa\Server\ServerPlugin;
use Tsufeki\Tenkawa\Server\Tenkawa;

/**
 * @coversNothing
 */
class FunctionalTest extends TestCase
{
    /**
     * @var Tenkawa
     */
    private $tenkawa;

    /**
     * @var JsonRpc
     */
    protected $rpc;

    /**
     * @var SimpleMethodRegistry
     */
    protected $methodRegistry;

    protected function setUp()
    {
        parent::setUp();

        $this->async(function () {
            $this->tenkawa = new Tenkawa(
                $this->kernel,
                new LevelFilteringLogger(new StreamLogger(STDERR), LogLevel::NOTICE),
                [new ServerPlugin(), new PhpPlugin()]
            );

            $transports = DummyTransportPair::create();
            $this->methodRegistry = new SimpleMethodRegistry();
            $this->rpc = JsonRpc::create($transports[0], $this->methodRegistry);

            $options = [
                'index.memory_only' => true,
                'index.stubs' => false,
                'log.client' => false,
                'file_watcher' => false,
            ];

            yield $this->tenkawa->run($transports[1], $options);
            yield $this->rpc->call('initialize', ['rootUri' => 'file://' . __DIR__ . '/fixtures']);
            yield;
        });
    }

    protected function openDocument(string $uri, string $text): \Generator
    {
        yield $this->rpc->notify('textDocument/didOpen', [
            'textDocument' => [
                'uri' => $uri,
                'languageId' => 'php',
                'version' => 1,
                'text' => $text,
            ],
        ]);
        yield;
    }

    protected function openAndGetPositionArgs(string $text, string $uri = 'file://' . __DIR__ . '/fixtures/foo.php', string $cursorMarker = '#'): \Generator
    {
        $offset = strpos($text, $cursorMarker) ?: 0;
        $text = substr_replace($text, '', $offset, 1);
        $line = $offset ? substr_count($text, "\n", 0, $offset) : 0;
        $col = $offset - ($line ? strrpos(substr($text, 0, $offset), "\n") + 1 : 0);

        yield $this->openDocument($uri, $text);

        return [
            'textDocument' => ['uri' => $uri],
            'position' => [
                'line' => $line,
                'character' => $col,
            ],
        ];
    }

    public function test_completion_members()
    {
        $this->async(function () {
            $args = yield $this->openAndGetPositionArgs('<?php \Foo\SelfCompletion::staticMethod()->#');
            $resp = yield $this->rpc->call('textDocument/completion', $args);

            usort($resp->items, function ($a, $b) { return strcmp($a->label, $b->label); });
            $this->assertJsonEquivalent([
                'isIncomplete' => false,
                'items' => [
                    [
                        'label' => '$pubField',
                        'kind' => 10,
                        'detail' => '\\Foo\\SelfCompletion',
                        'insertText' => 'pubField',
                        'sortText' => 'pubField',
                        'filterText' => 'pubField',
                    ],
                    [
                        'label' => 'method',
                        'kind' => 2,
                        'detail' => '\\Foo\\SelfCompletion',
                        'insertText' => 'method(',
                    ],
                    [
                        'label' => 'staticMethod',
                        'kind' => 2,
                        'detail' => '\\Foo\\SelfCompletion',
                        'insertText' => 'staticMethod(',
                    ],
                ],
            ], $resp);
        });
    }

    public function test_completion_variables()
    {
        $this->async(function () {
            $args = yield $this->openAndGetPositionArgs('<?php function foo($baz) { $bar = 7; $b# }');
            $resp = yield $this->rpc->call('textDocument/completion', $args);

            usort($resp->items, function ($a, $b) { return strcmp($a->label, $b->label); });
            $this->assertJsonEquivalent([
                'isIncomplete' => false,
                'items' => [
                    [
                        'label' => '$bar',
                        'kind' => 6,
                        'detail' => 'mixed',
                        'insertText' => 'bar',
                        'textEdit' => [
                            'range' => [
                                'start' => [
                                    'line' => 0,
                                    'character' => 37,
                                ],
                                'end' => [
                                    'line' => 0,
                                    'character' => 39,
                                ],
                            ],
                            'newText' => '$bar',
                        ],
                    ],
                    [
                        'label' => '$baz',
                        'kind' => 6,
                        'insertText' => 'baz',
                        'textEdit' => [
                            'range' => [
                                'start' => [
                                    'line' => 0,
                                    'character' => 37,
                                ],
                                'end' => [
                                    'line' => 0,
                                    'character' => 39,
                                ],
                            ],
                            'newText' => '$baz',
                        ],
                    ],
                ],
            ], $resp);
        });
    }

    public function test_completion_variables_empty()
    {
        $this->async(function () {
            $args = yield $this->openAndGetPositionArgs('<?php function foo() { $#; $bar = 7; }');
            $resp = yield $this->rpc->call('textDocument/completion', $args);

            usort($resp->items, function ($a, $b) { return strcmp($a->label, $b->label); });
            $this->assertJsonEquivalent([
                'isIncomplete' => false,
                'items' => [
                    [
                        'label' => '$bar',
                        'kind' => 6,
                        'detail' => 'mixed',
                        'insertText' => 'bar',
                        'textEdit' => [
                            'range' => [
                                'start' => [
                                    'line' => 0,
                                    'character' => 23,
                                ],
                                'end' => [
                                    'line' => 0,
                                    'character' => 24,
                                ],
                            ],
                            'newText' => '$bar',
                        ],
                    ],
                ],
            ], $resp);
        });
    }

    public function test_completion_classes()
    {
        $this->async(function () {
            $args = yield $this->openAndGetPositionArgs('<?php use Foo\\SelfCompletion; new S#');
            $resp = yield $this->rpc->call('textDocument/completion', $args);

            usort($resp->items, function ($a, $b) { return strcmp($a->label, $b->label); });
            $this->assertJsonEquivalent([
                'isIncomplete' => false,
                'items' => [
                    [
                        'label' => 'Foo',
                        'kind' => 9,
                        'detail' => '\\Foo',
                        'insertText' => 'Foo\\',
                    ],
                    [
                        'label' => 'SelfCompletion',
                        'kind' => 7,
                        'detail' => '\\Foo\\SelfCompletion',
                        'insertText' => 'SelfCompletion',
                    ],
                ],
            ], $resp);
        });
    }

    public function test_completion_full_namespace()
    {
        $this->async(function () {
            $args = yield $this->openAndGetPositionArgs('<?php new \\#');
            $resp = yield $this->rpc->call('textDocument/completion', $args);

            usort($resp->items, function ($a, $b) { return strcmp($a->label, $b->label); });
            $this->assertJsonEquivalent([
                'isIncomplete' => false,
                'items' => [
                    [
                        'label' => 'Foo',
                        'kind' => 9,
                        'detail' => '\\Foo',
                        'insertText' => 'Foo\\',
                    ],
                ],
            ], $resp);
        });
    }

    public function test_completion_imported_namespace()
    {
        $this->async(function () {
            $args = yield $this->openAndGetPositionArgs('<?php namespace Bar; use Foo as FooFoo; new FooFoo\\#');
            $resp = yield $this->rpc->call('textDocument/completion', $args);

            usort($resp->items, function ($a, $b) { return strcmp($a->label, $b->label); });
            $this->assertJsonEquivalent([
                'isIncomplete' => false,
                'items' => [
                    [
                        'label' => 'SelfCompletion',
                        'kind' => 7,
                        'detail' => '\\Foo\\SelfCompletion',
                        'insertText' => 'SelfCompletion',
                    ],
                ],
            ], $resp);
        });
    }

    public function test_completion_classes_with_import()
    {
        $this->async(function () {
            $args = yield $this->openAndGetPositionArgs('<?php
namespace Bar;

new S#');
            $resp = yield $this->rpc->call('textDocument/completion', $args);

            usort($resp->items, function ($a, $b) { return strcmp($a->label, $b->label); });
            $this->assertJsonEquivalent([
                'isIncomplete' => false,
                'items' => [
                    [
                        'label' => 'SelfCompletion',
                        'kind' => 7,
                        'detail' => "use \\Foo\\SelfCompletion\n\n(auto-import)",
                        'insertText' => 'SelfCompletion',
                        'additionalTextEdits' => [[
                            'range' => [
                                'start' => [
                                    'line' => 3,
                                    'character' => 0,
                                ],
                                'end' => [
                                    'line' => 3,
                                    'character' => 0,
                                ],
                            ],
                            'newText' => "use Foo\\SelfCompletion;\n\n",
                        ]],
                    ],
                ],
            ], $resp);
        });
    }
}
