<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Language;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassConst;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\Element\Method;
use Tsufeki\Tenkawa\Php\Reflection\Element\Property;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Language\CompletionProvider;
use Tsufeki\Tenkawa\Server\Protocol\Common\Position;
use Tsufeki\Tenkawa\Server\Protocol\Common\TextEdit;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\CompletionContext;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\CompletionItem;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\CompletionItemKind;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\CompletionList;

class MembersCompletionProvider implements CompletionProvider
{
    /**
     * @var MembersHelper
     */
    private $membersHelper;

    /**
     * @var NodeFinder
     */
    private $nodeFinder;

    public function __construct(MembersHelper $membersHelper, NodeFinder $nodeFinder)
    {
        $this->membersHelper = $membersHelper;
        $this->nodeFinder = $nodeFinder;
    }

    public function getTriggerCharacters(): array
    {
        return ['>', ':'];
    }

    public function getCompletions(
        Document $document,
        Position $position,
        CompletionContext $context = null
    ): \Generator {
        $completions = new CompletionList();

        if ($document->getLanguage() !== 'php') {
            return $completions;
        }

        /** @var (Node|Comment)[] $nodes */
        $nodes = yield $this->nodeFinder->getNodePath($document, $position);
        /** @var MemberFetch|null $memberFetch */
        $memberFetch = yield $this->membersHelper->getMemberFetch($nodes, $document, $position, true);
        if ($memberFetch === null) {
            return $completions;
        }

        /** @var Element[] $elements */
        $elements = yield $this->membersHelper->getAllReflectionsFromMemberFetch($memberFetch, $nodes, $document);

        foreach ($elements as $element) {
            $item = new CompletionItem();
            $item->label = $element->name;
            $item->kind = $this->getKind($element);
            $item->detail = $element->nameContext->class;
            $item->insertText = $element->name;

            if ($element instanceof Method) {
                $item->insertText .= '(';
            }
            if ($element instanceof Property) {
                $item->filterText = $item->label;
                $item->sortText = $item->label;
                $item->label = '$' . $item->label;
                if ($element->static) {
                    if ($nodes[0] instanceof Expr\ClassConstFetch) {
                        $item->insertText = '$' . $item->insertText;
                    }

                    $item->textEdit = new TextEdit();
                    $item->textEdit->range = $memberFetch->nameRange;
                    $item->textEdit->newText = '$' . $element->name;
                }
            }

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
