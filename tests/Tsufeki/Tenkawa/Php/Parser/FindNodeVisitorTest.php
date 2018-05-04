<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Php\Parser;

use PhpLenientParser\LenientParserFactory;
use PhpParser\Comment;
use PhpParser\ErrorHandler;
use PhpParser\Lexer;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PHPUnit\Framework\TestCase;
use Tsufeki\Tenkawa\Php\Parser\FindNodeVisitor;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Uri;

/**
 * @covers \Tsufeki\Tenkawa\Php\Parser\FindNodeVisitor
 */
class FindNodeVisitorTest extends TestCase
{
    /**
     * @dataProvider data
     */
    public function test(string $source, int $line, int $col, array $nodeTypes, bool $stickToRightEnd = false)
    {
        $lexer = new Lexer\Emulative(['usedAttributes' => [
            'comments',
            'startLine', 'endLine',
            'startFilePos', 'endFilePos',
            'startTokenPos', 'endTokenPos',
        ]]);

        $parser = (new LenientParserFactory())->create(LenientParserFactory::ONLY_PHP7, $lexer);
        $nodes = $parser->parse($source, new ErrorHandler\Collecting()) ?? [];

        $document = new Document(Uri::fromString('file:///foo'), 'php');
        $document->update($source);

        $visitor = new FindNodeVisitor($document, new Position($line, $col), $stickToRightEnd);
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($nodes);

        $nodes = $visitor->getNodes();
        $this->assertCount(count($nodeTypes), $nodes);
        foreach ($nodeTypes as $i => $nodeType) {
            $this->assertInstanceOf($nodeType, $nodes[$i]);
        }
    }

    public function data(): array
    {
        return [
            [
                '<?php $foo = 7;',
                0, 7,
                [Expr\Variable::class, Expr\Assign::class],
            ],
            [
                '<?php $foo = 7;',
                0, 6,
                [Expr\Variable::class, Expr\Assign::class],
            ],
            [
                '<?php $foo = 7;',
                0, 9,
                [Expr\Variable::class, Expr\Assign::class],
            ],
            [
                '<?php $foo = 7;',
                0, 10,
                [Expr\Assign::class],
            ],
            [
                '<?php $foo = 7;',
                0, 5,
                [],
            ],
            [
                '<?php $;',
                0, 7,
                [Expr\Error::class, Expr\Variable::class],
                true,
            ],
            [
                '<?php if (!($a))',
                0, 13,
                [Expr\Variable::class, Expr\BooleanNot::class, Stmt\If_::class],
                true,
            ],
            [
                '<?php Ee\\;',
                0, 9,
                [Name::class, Expr\ConstFetch::class],
                true,
            ],
            [
                '<?php /* bar */ $foo = 7;',
                0, 9,
                [Comment::class, Expr\Assign::class],
            ],
            [
                '<?php [/* bar */ $foo = 7];',
                0, 10,
                [Comment::class, Expr\ArrayItem::class, Expr\Array_::class],
            ],
            [
                '<?php function f($x, A) {}',
                0, 22,
                [Name::class, Param::class, Stmt\Function_::class],
                true,
            ],
        ];
    }
}
