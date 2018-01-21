<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\CompletionContext;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\CompletionItem;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\CompletionItemKind;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\CompletionList;
use Tsufeki\Tenkawa\Reflection\Element\ClassConst;
use Tsufeki\Tenkawa\Reflection\Element\Element;
use Tsufeki\Tenkawa\Reflection\Element\Method;
use Tsufeki\Tenkawa\Reflection\Element\Property;

class MembersCompletionProvider implements CompletionProvider
{
    /**
     * @var MembersHelper
     */
    private $membersHelper;

    public function __construct(MembersHelper $membersHelper)
    {
        $this->membersHelper = $membersHelper;
    }

    public function getCompletions(
        Document $document,
        Position $position,
        CompletionContext $context = null,
        array $nodes
    ): \Generator {
        $completions = new CompletionList();

        if ($document->getLanguage() !== 'php') {
            return $completions;
        }

        /** @var Element[] $elements */
        $elements = yield $this->membersHelper->getAllMemberReflectionsFromNodePath($nodes, $document, $position);

        foreach ($elements as $element) {
            $item = new CompletionItem();
            $item->label = $element->name;
            $item->kind = $this->getKind($element);
            $item->detail = $element->nameContext->class;
            $item->insertText = $element->name . ($element instanceof Method ? '(' : '');

            $completions->items[] = $item;
        }

        return $completions;
    }

    private function getKind(Element $element): int
    {
        if ($element instanceof ClassConst) {
            return CompletionItemKind::VARIABLE;
        }
        if ($element instanceof Property) {
            return CompletionItemKind::PROPERTY;
        }
        if ($element instanceof Method) {
            if (in_array(strtolower($element->name), ['__construct', '__destruct'])) {
                return CompletionItemKind::CONSTRUCTOR;
            }

            return CompletionItemKind::METHOD;
        }

        return CompletionItemKind::TEXT;
    }
}
