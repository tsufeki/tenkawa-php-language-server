<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Refactoring;

use Tsufeki\Tenkawa\Server\Feature\Command\CommandProvider;
use Tsufeki\Tenkawa\Server\Feature\Common\WorkspaceEdit;
use Tsufeki\Tenkawa\Server\Feature\WorkspaceEdit\WorkspaceEditFeature;

class WorkspaceEditCommandProvider implements CommandProvider
{
    const COMMAND = 'tenkawa.php.workspace_edit';

    /**
     * @var WorkspaceEditFeature
     */
    private $workspaceEditFeature;

    public function __construct(WorkspaceEditFeature $workspaceEditFeature)
    {
        $this->workspaceEditFeature = $workspaceEditFeature;
    }

    public function getCommand(): string
    {
        return self::COMMAND;
    }

    public function execute(string $label, WorkspaceEdit $edit): \Generator
    {
        yield $this->workspaceEditFeature->applyWorkspaceEdit($label, $edit);
    }
}
