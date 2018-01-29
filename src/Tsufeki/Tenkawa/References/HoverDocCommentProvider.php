<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Protocol\Common\MarkupContent;
use Tsufeki\Tenkawa\Protocol\Common\MarkupKind;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\Hover;
use Tsufeki\Tenkawa\Reflection\Element\Element;

class HoverDocCommentProvider implements HoverProvider
{
    /**
     * @var DocCommentHelper
     */
    private $docCommentHelper;

    /**
     * @var HoverFormatter
     */
    private $formatter;

    public function __construct(DocCommentHelper $docCommentHelper, HoverFormatter $formatter)
    {
        $this->docCommentHelper = $docCommentHelper;
        $this->formatter = $formatter;
    }

    public function getHover(Document $document, Position $position, array $nodes): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return null;
        }

        /** @var Element[] $elements */
        $elements = yield $this->docCommentHelper->getReflectionFromNodePath($nodes, $document, $position);

        if (empty($elements)) {
            return null;
        }

        $hover = new Hover();
        // TODO check client capabilities
        // $hover->contents = new MarkupContent();
        // $hover->contents->kind = MarkupKind::MARKDOWN;
        // $hover->contents->string = $this->formatter->format($elements[0]);
        $hover->contents = $this->formatter->format($elements[0]);

        return $hover;
    }
}
