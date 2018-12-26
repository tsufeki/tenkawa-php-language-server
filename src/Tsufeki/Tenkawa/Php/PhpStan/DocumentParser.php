<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PHPStan\Parser\Parser;
use PHPStan\ShouldNotHappenException;
use Tsufeki\Tenkawa\Php\Parser\Ast;
use Tsufeki\Tenkawa\Php\Parser\Parser as TenkawaParser;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\SyncAsync;

class DocumentParser implements Parser, AnalysedDocumentAware
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

    public function setDocument(?Document $document): void
    {
        $this->document = $document;
    }

    public function parseFile(string $file): array
    {
        if ($this->document === null) {
            throw new ShouldNotHappenException();
        }

        if (!$this->document->getUri()->equals(Uri::fromFilesystemPath($file))) {
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
