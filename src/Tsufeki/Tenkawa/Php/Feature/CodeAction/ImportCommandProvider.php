<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\CodeAction;

use Tsufeki\Tenkawa\Php\Feature\ImportHelper;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Feature\Command\CommandProvider;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\WorkspaceEdit;
use Tsufeki\Tenkawa\Server\Feature\WorkspaceEdit\WorkspaceEditFeature;
use Tsufeki\Tenkawa\Server\Uri;

class ImportCommandProvider implements CommandProvider
{
    const COMMAND = 'tenkawa.php.refactor.import';

    /**
     * @var ImportHelper
     */
    private $importHelper;

    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var WorkspaceEditFeature
     */
    private $workspaceEditFeature;

    public function __construct(
        ImportHelper $importHelper,
        DocumentStore $documentStore,
        WorkspaceEditFeature $workspaceEditFeature
    ) {
        $this->importHelper = $importHelper;
        $this->documentStore = $documentStore;
        $this->workspaceEditFeature = $workspaceEditFeature;
    }

    public function getCommand(): string
    {
        return self::COMMAND;
    }

    public function execute(Uri $uri, Position $position, string $kind, string $name, int $version = null): \Generator
    {
        $document = $this->documentStore->get($uri);
        /** @var WorkspaceEdit $edit */
        $edit = yield $this->importHelper->getImportEdit(
            $document,
            $position,
            $kind,
            $name,
            $version
        );

        yield $this->workspaceEditFeature->applyWorkspaceEdit('Import', $edit);
    }
}
