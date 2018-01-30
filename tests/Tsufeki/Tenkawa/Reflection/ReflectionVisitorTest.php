<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Reflection;

use PhpLenientParser\LenientParserFactory;
use PhpParser\Lexer;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PHPUnit\Framework\TestCase;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Document\Project;
use Tsufeki\Tenkawa\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Reflection\ReflectionVisitor;
use Tsufeki\Tenkawa\Uri;

/**
 * @covers \Tsufeki\Tenkawa\Reflection\ReflectionVisitor
 * @covers \Tsufeki\Tenkawa\Reflection\NameContextVisitor
 */
class ReflectionVisitorTest extends TestCase
{
    private function checkObjects($expected, $actual, string $path)
    {
        if (is_array($actual)) {
            $this->assertCount(count($actual), $expected, $path);
            foreach ($expected as $key => $value) {
                $this->checkObjects($value, $actual[$key], "{$path}[$key]");
            }
        } elseif (is_object($actual)) {
            foreach ($expected as $key => $value) {
                $this->checkObjects($value, $actual->$key, "$path.$key");
            }
        } else {
            $this->assertSame($expected, $actual, $path);
        }
    }

    /**
     * @dataProvider data
     */
    public function test(string $source, array $expected)
    {
        $lexer = new Lexer\Emulative(['usedAttributes' => [
            'comments',
            'startLine', 'endLine',
            'startFilePos', 'endFilePos',
            'startTokenPos', 'endTokenPos',
        ]]);

        $parser = (new LenientParserFactory())->create(LenientParserFactory::ONLY_PHP7, $lexer);
        $nodes = $parser->parse($source) ?? [];

        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor(new NameResolver());
        $nodeTraverser->traverse($nodes);

        $project = new Project(Uri::fromString('file:///'));
        $document = new Document(Uri::fromString('file:///foo'), 'php', $project);
        $document->update($source);

        $visitor = new ReflectionVisitor($document);
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($nodes);

        $this->checkObjects($expected['classes'] ?? [], $visitor->getClasses(), 'classes');
        $this->checkObjects($expected['functions'] ?? [], $visitor->getFunctions(), 'functions');
        $this->checkObjects($expected['consts'] ?? [], $visitor->getConsts(), 'consts');
    }

    public function data(): array
    {
        return [
            ['<?php const FOO = 1;', ['consts' => [[
                'name' => '\\FOO',
            ]]]],

            ['<?php define("FOO", 1);', ['consts' => [[
                'name' => '\\FOO',
            ]]]],

            [
                '<?php
                /** doc comment */
                const FOO = 1;',
                ['consts' => [[
                    'name' => '\\FOO',
                    'docComment' => '/** doc comment */',
                    'location' => [
                        'range' => [
                            'start' => ['line' => 2, 'character' => 22],
                            'end' => ['line' => 2, 'character' => 29],
                        ],
                    ],
                ]]],
            ],

            [
                '<?php function foo($x, &$y, ...$z): int {}',
                ['functions' => [[
                    'name' => '\\foo',
                    'params' => [
                        [
                            'name' => 'x',
                            'byRef' => false,
                            'type' => null,
                            'variadic' => false,
                            'optional' => false,
                            'defaultNull' => false,
                        ],
                        [
                            'name' => 'y',
                            'byRef' => true,
                            'type' => null,
                            'variadic' => false,
                            'optional' => false,
                            'defaultNull' => false,
                        ],
                        [
                            'name' => 'z',
                            'byRef' => false,
                            'type' => null,
                            'variadic' => true,
                            'optional' => true,
                            'defaultNull' => false,
                        ],
                    ],
                    'returnType' => ['type' => 'int'],
                    'returnByRef' => false,
                    'callsFuncGetArgs' => false,
                ]]],
            ],

            [
                '<?php
                function &foo(A $x = null, A $y, $z = 7) {
                    func_get_args();
                }',
                ['functions' => [[
                    'name' => '\\foo',
                    'params' => [
                        [
                            'name' => 'x',
                            'byRef' => false,
                            'type' => ['type' => '\\A'],
                            'variadic' => false,
                            'optional' => false,
                            'defaultNull' => true,
                        ],
                        [
                            'name' => 'y',
                            'byRef' => false,
                            'type' => ['type' => '\\A'],
                            'variadic' => false,
                            'optional' => false,
                            'defaultNull' => false,
                        ],
                        [
                            'name' => 'z',
                            'byRef' => false,
                            'type' => null,
                            'variadic' => false,
                            'optional' => true,
                            'defaultNull' => false,
                        ],
                    ],
                    'returnType' => null,
                    'returnByRef' => true,
                    'callsFuncGetArgs' => true,
                ]]],
            ],

            [
                '<?php
                function foo() {
                    $z = function () {
                        func_get_args();
                    };
                }',
                ['functions' => [[
                    'name' => '\\foo',
                    'params' => [],
                    'callsFuncGetArgs' => false,
                ]]],
            ],

            [
                '<?php
                final class C extends A\B implements I, J
                {
                    const FOO = 7;

                    private $bar;

                    public static function baz(): int {}
                }',
                ['classes' => [[
                    'name' => '\\C',
                    'isClass' => true,
                    'isInterface' => false,
                    'isTrait' => false,
                    'consts' => [[
                        'name' => 'FOO',
                        'accessibility' => ClassLike::M_PUBLIC,
                        'static' => true,
                    ]],
                    'properties' => [[
                        'name' => 'bar',
                        'accessibility' => ClassLike::M_PRIVATE,
                        'static' => false,
                    ]],
                    'methods' => [[
                        'name' => 'baz',
                        'accessibility' => ClassLike::M_PUBLIC,
                        'static' => true,
                        'abstract' => false,
                        'final' => false,
                        'returnType' => ['type' => 'int'],
                    ]],
                    'abstract' => false,
                    'final' => true,
                    'parentClass' => '\\A\\B',
                    'interfaces' => ['\\I', '\\J'],
                    'traits' => [],
                    'traitAliases' => [],
                    'traitInsteadOfs' => [],
                ]]],
            ],

            [
                '<?php
                abstract class C
                {
                    use T, U {
                        T::f as public g;
                        U::h insteadof T;
                    }
                }',
                ['classes' => [[
                    'name' => '\\C',
                    'isClass' => true,
                    'consts' => [],
                    'properties' => [],
                    'methods' => [],
                    'abstract' => true,
                    'final' => false,
                    'traits' => ['\\T', '\\U'],
                    'traitAliases' => [[
                        'trait' => '\\T',
                        'method' => 'f',
                        'newName' => 'g',
                        'newAccessibility' => ClassLike::M_PUBLIC,
                    ]],
                    'traitInsteadOfs' => [[
                        'trait' => '\\U',
                        'method' => 'h',
                        'insteadOfs' => ['\\T'],
                    ]],
                ]]],
            ],

            [
                '<?php
                interface I extends J
                {
                    function foo(): ?int;
                }',
                ['classes' => [[
                    'name' => '\\I',
                    'isClass' => false,
                    'isInterface' => true,
                    'isTrait' => false,
                    'consts' => [],
                    'properties' => [],
                    'methods' => [[
                        'name' => 'foo',
                        'accessibility' => ClassLike::M_PUBLIC,
                        'returnType' => ['type' => '?int'],
                    ]],
                    'abstract' => false,
                    'final' => false,
                    'parentClass' => null,
                    'interfaces' => ['\\J'],
                    'traits' => [],
                    'traitAliases' => [],
                    'traitInsteadOfs' => [],
                ]]],
            ],

            [
                '<?php
                namespace A\\B;

                trait T
                {
                    use \\X\\Y;

                    protected $foo;
                }',
                ['classes' => [[
                    'name' => '\\A\\B\\T',
                    'isClass' => false,
                    'isInterface' => false,
                    'isTrait' => true,
                    'consts' => [],
                    'properties' => [[
                        'name' => 'foo',
                        'accessibility' => ClassLike::M_PROTECTED,
                    ]],
                    'methods' => [],
                    'abstract' => false,
                    'final' => false,
                    'parentClass' => null,
                    'interfaces' => [],
                    'traits' => ['\\X\\Y'],
                    'traitAliases' => [],
                    'traitInsteadOfs' => [],
                ]]],
            ],

            [
                '<?php
                namespace N\\M;
                use A\\B as BB;
                use function A\\foo;
                use const A\\{ BAR, BAZ };

                class C {
                    public $x;
                }',
                ['classes' => [[
                    'name' => '\\N\\M\\C',
                    'properties' => [[
                        'name' => 'x',
                        'nameContext' => [
                            'namespace' => '\\N\\M',
                            'uses' => ['BB' => '\\A\\B'],
                            'functionUses' => ['foo' => '\\A\\foo'],
                            'constUses' => ['BAR' => '\\A\\BAR', 'BAZ' => '\\A\\BAZ'],
                            'class' => '\\N\\M\\C',
                        ],
                    ]],
                ]]],
            ],

            [
                '<?php
                class C
                {
                    public function f() {
                        function nested() {
                        }
                    }
                }',
                [
                    'classes' => [['name' => '\C']],
                    'functions' => [[
                        'name' => '\nested',
                        'nameContext' => [
                            'class' => null,
                        ],
                    ]],
                ],
            ],
        ];
    }
}
