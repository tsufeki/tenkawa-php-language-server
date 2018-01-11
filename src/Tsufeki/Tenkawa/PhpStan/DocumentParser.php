<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\PhpStan;

use PHPStan\Parser\Parser;
use PHPStan\ShouldNotHappenException;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Parser\Ast;
use Tsufeki\Tenkawa\Parser\Parser as TenkawaParser;
use Tsufeki\Tenkawa\Utils\SyncAsync;

class DocumentParser implements Parser
{
    /**
     * @var TenkawaParser
     */
    private $parser;

    /**
     * @var SyncAsync
     */
    private $syncAsync;

    /**
     * @var Document|null
     */
    private $document;

    public function __construct(TenkawaParser $parser, SyncAsync $syncAsync)
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
