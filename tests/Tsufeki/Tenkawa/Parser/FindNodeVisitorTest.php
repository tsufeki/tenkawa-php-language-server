<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Parser;

use PhpLenientParser\LenientParserFactory;
use PhpParser\Comment;
use PhpParser\Lexer;
use PhpParser\Node\Expr;
use PhpParser\NodeTraverser;
use PHPUnit\Framework\TestCase;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Document\Project;
use Tsufeki\Tenkawa\Parser\FindNodeVisitor;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Uri;

/**
 * @covers \Tsufeki\Tenkawa\Parser\FindNodeVisitor
 */
class FindNodeVisitorTest extends TestCase
{
    /**
     * @dataProvider data
     */
    public function test(string $source, int $line, int $col, array $nodeTypes)
    {
        $lexer = new Lexer\Emulative(['usedAttributes' => [
            'comments',
            'startLine', 'endLine',
            'startFilePos', 'endFilePos',
            'startTokenPos', 'endTokenPos',
        ]]);

        $parser = (new LenientParserFactory())->create(LenientParserFactory::ONLY_PHP7, $lexer);
        $nodes = $parser->parse($source) ?? [];

        $project = new Project(Uri::fromString('file:///'));
        $document = new Document(Uri::fromString('file:///foo'), 'php', $project);
        $document->update($source);

        $visitor = new FindNodeVisitor($document, new Position($line, $col));
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
                '<?php /* bar */ $foo = 7;',
                0, 9,
                [Comment::class, Expr\Assign::class],
            ],
            [
                '<?php [/* bar */ $foo = 7];',
                0, 10,
                [Comment::class, Expr\ArrayItem::class, Expr\Array_::class],
            ],
        ];
    }
}
