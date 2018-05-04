<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Hover;

use Tsufeki\Tenkawa\Php\Feature\Symbol;
use Tsufeki\Tenkawa\Php\Feature\SymbolExtractor;
use Tsufeki\Tenkawa\Php\Feature\SymbolReflection;
use Tsufeki\Tenkawa\Php\Reflection\Element\Const_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\MarkupContent;
use Tsufeki\Tenkawa\Server\Feature\Common\MarkupKind;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Hover\Hover;
use Tsufeki\Tenkawa\Server\Feature\Hover\HoverProvider;

class SymbolHoverProvider implements HoverProvider
{
    /**
     * @var SymbolExtractor
     */
    private $symbolExtractor;

    /**
     * @var SymbolReflection
     */
    private $symbolReflection;

    /**
     * @var HoverFormatter
     */
    private $formatter;

    public function __construct(
        SymbolExtractor $symbolExtractor,
        SymbolReflection $symbolReflection,
        HoverFormatter $formatter
    ) {
        $this->symbolExtractor = $symbolExtractor;
        $this->symbolReflection = $symbolReflection;
        $this->formatter = $formatter;
    }

    public function getHover(Document $document, Position $position): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return null;
        }

        /** @var Symbol|null */
        $symbol = yield $this->symbolExtractor->getSymbolAt($document, $position);
        if ($symbol === null) {
            return null;
        }

        /** @var Element[] $elements */
        $elements = yield $this->symbolReflection->getReflectionFromSymbol($symbol);
        if (empty($elements) || (
            $elements[0] instanceof Const_ &&
            in_array(strtolower($elements[0]->name), ['\\null', '\\true', '\\false'], true)
        )) {
            return null;
        }

        $hover = new Hover();
        // TODO check client capabilities
        // $hover->contents = new MarkupContent();
        // $hover->contents->kind = MarkupKind::MARKDOWN;
        // $hover->contents->string = $this->formatter->format($elements[0]);
        $hover->contents = $this->formatter->format($elements[0]);
        $hover->range = $symbol->range;

        return $hover;
    }
}
