<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PHPStan\Parser\Parser;
use PHPStan\ShouldNotHappenException;
use Tsufeki\Tenkawa\Php\Parser\Ast;
use Tsufeki\Tenkawa\Php\Parser\Parser as TenkawaParser;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Utils\SyncAsyncKernel;

class DocumentParser implements Parser
{
    /**
     * @var TenkawaParser
     */
    private $parser;

    /**
     * @var SyncAsyncKernel
     */
    private $syncAsync;

    /**
     * @var Document|null
     */
    private $document;

    public function __construct(TenkawaParser $parser, SyncAsyncKernel $syncAsync)
    {
        $this->parser = $parser;
        $this->syncAsync = $syncAsync;
    }

    public function setDocument(Document $document = null)
    {
        $this->document = $document;
    }

    public function parseFile(string $file): array
    {
        if ($this->document === null) {
            throw new ShouldNotHappenException();
        }

        if ($file !== $this->document->getUri()->getFilesystemPath()) {
            throw new ShouldNotHappenException();
        }

        /** @var Ast $ast */
        $ast = $this->syncAsync->callAsync($this->parser->parse($this->document));

        return $ast->nodes;
    }

    public function parseString(string $sourceCode): array
    {
        throw new ShouldNotHappenException();
    }
}
