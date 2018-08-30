<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Completion;

use Tsufeki\Tenkawa\Php\Composer\ComposerService;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Feature\Common\TextEdit;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionContext;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionItem;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionItemKind;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionList;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionProvider;
use Tsufeki\Tenkawa\Server\Feature\Completion\InsertTextFormat;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;
use Tsufeki\Tenkawa\Server\Utils\Template;

class WholeFileSnippetCompletionProvider implements CompletionProvider
{
    /**
     * @var ComposerService
     */
    private $composerService;

    /**
     * @var array
     */
    private $snippets;

    public function __construct(array $wholeFileSnippets, ComposerService $composerService)
    {
        $this->snippets = $wholeFileSnippets;
        $this->composerService = $composerService;
    }

    /**
     * @resolve CompletionList
     */
    public function getCompletions(
        Document $document,
        Position $position,
        ?CompletionContext $context
    ): \Generator {
        $completions = new CompletionList();

        $text = $document->getText();
        if (preg_match('/\\A([ \\t]*[a-z]*[ \\t]*)\\n?\\z/', $text, $matches) !== 1) {
            return $completions;
        }

        $class = yield $this->composerService->getAutoloadClassForFile($document);
        if ($class === null) {
            return $completions;
        }

        foreach ($this->snippets as ['key' => $key, 'description' => $description, 'template' => $template]) {
            assert($template instanceof Template);

            $completion = new CompletionItem();
            $completion->detail = $description;
            $completion->insertText = $key;
            $completion->insertTextFormat = InsertTextFormat::PLAIN_TEXT;
            $completion->kind = CompletionItemKind::SNIPPET;
            $completion->label = $key;

            $renderedText = yield $template->render([
                'namespace' => ltrim(StringUtils::getNamespace($class), '\\'),
                'class' => StringUtils::getShortName($class),
            ]);

            $completion->textEdit = new TextEdit();
            $completion->textEdit->newText = $renderedText;
            $completion->textEdit->range = new Range(
                new Position(0, 0),
                new Position(0, strlen($matches[1]))
            );

            $completions->items[] = $completion;
        }

        return $completions;
    }

    /**
     * @return string[]
     */
    public function getTriggerCharacters(): array
    {
        return [];
    }
}
