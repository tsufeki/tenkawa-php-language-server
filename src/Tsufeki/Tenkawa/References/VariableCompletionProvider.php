<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Parser\Ast;
use Tsufeki\Tenkawa\Parser\Parser;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\CompletionContext;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\CompletionItem;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\CompletionItemKind;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\CompletionList;
use Tsufeki\Tenkawa\TypeInference\Type;
use Tsufeki\Tenkawa\TypeInference\TypeInference;

class VariableCompletionProvider implements CompletionProvider
{
    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var TypeInference
     */
    private $typeInference;

    /**
     * @var MembersHelper
     */
    private $membersHelper;

    public function __construct(Parser $parser, TypeInference $typeInference, MembersHelper $membersHelper)
    {
        $this->parser = $parser;
        $this->typeInference = $typeInference;
        $this->membersHelper = $membersHelper;
    }

    public function getTriggerCharacters(): array
    {
        return ['$'];
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

        if (count($nodes) < 1 || !($nodes[0] instanceof Expr\Variable)) {
            return $completions;
        }

        yield $this->typeInference->infer($document);
        /** @var array<string,Type|null> $variables */
        $variables = yield $this->getVariables($nodes, $document);

        foreach ($variables as $name => $type) {
            $item = new CompletionItem();
            $item->label = '$' . $name;
            $item->kind = CompletionItemKind::VARIABLE;
            $item->detail = $type ? (string)$type : null;
            $item->insertText = $name;

            $completions->items[] = $item;
        }

        return $completions;
    }

    /**
     * @param (Node|Comment)[] $nodes
     *
     * @resolve array<string,Type|null>
     */
    private function getVariables(array $nodes, Document $document): \Generator
    {
        $statements = null;
        $variables = [];

        foreach ($nodes as $node) {
            if ($node instanceof Stmt\ClassLike) {
                $statements = [];
                break;
            }
            if ($node instanceof FunctionLike) {
                $statements = $node->getStmts() ?: [];
                foreach ($node->getParams() as $param) {
                    $variables[$param->name] = null; // TODO: type
                }

                if ($this->membersHelper->isInObjectContext($nodes)) {
                    $variables['this'] = null; // TODO: type
                }

                if ($node instanceof Expr\Closure) {
                    foreach ($node->uses as $use) {
                        $variables[$use->var] = null; // TODO: type
                    }
                }
                break;
            }
        }

        if ($statements === null) {
            /** @var Ast $ast */
            $ast = yield $this->parser->parse($document);
            $statements = $ast->nodes;
        }

        $visitor = new VariableGatheringVisitor($variables);
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($statements);
        $variables = $visitor->getVariables();

        return $variables;
    }
}
