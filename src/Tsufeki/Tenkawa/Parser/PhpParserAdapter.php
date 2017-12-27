<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Parser;

use PhpLenientParser\LenientParser;
use PhpLenientParser\LenientParserFactory;
use PhpParser\ErrorHandler;
use PhpParser\Lexer;
use PhpParser\Node;
use React\Promise\Deferred;
use Tsufeki\Tenkawa\Document\Document;

class PhpParserAdapter implements Parser
{
    /**
     * @var LenientParser
     */
    private $parser;

    /**
     * @var Lexer
     */
    private $lexer;

    public function __construct()
    {
        $this->lexer = new Lexer\Emulative(['usedAttributes' => [
            'comments',
            'startLine', 'endLine',
            'startFilePos', 'endFilePos',
            'startTokenPos', 'endTokenPos',
        ]]);

        $this->parser = (new LenientParserFactory())->create(LenientParserFactory::ONLY_PHP7, $this->lexer);
    }

    /**
     * @resolve Node[]
     */
    public function parse(Document $document): \Generator
    {
        $astPromise = $document->get('parser.ast');
        if ($astPromise !== null) {
            return yield $astPromise;
        }

        $deferred = new Deferred();
        $document->set('parser.ast', $deferred->promise());

        $ast = new Ast();
        $errorHandler = new ErrorHandler\Collecting();
        $ast->nodes = $this->parser->parse($document->getText(), $errorHandler) ?? [];
        $ast->errors = $errorHandler->getErrors();

        $deferred->resolve($ast);

        return $ast;
    }
}
