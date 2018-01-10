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
     * @var Document
     */
    private $document;

    public function __construct(TenkawaParser $parser, SyncAsync $syncAsync, Document $document)
    {
        $this->parser = $parser;
        $this->syncAsync = $syncAsync;
        $this->document = $document;
    }

    public function parseFile(string $file): array
    {
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
