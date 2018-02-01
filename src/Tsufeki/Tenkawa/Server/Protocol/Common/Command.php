<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Protocol\Common;

/**
 * Represents a reference to a command.
 *
 * Provides a title which will be used to represent a command in the UI.
 * Commands are identified by a string identifier. The protocol currently
 * doesn’t specify a set of well-known commands. So executing a command
 * requires some tool extension code.
 */
class Command
{
    /**
     * Title of the command, like `save`.
     *
     * @var string
     */
    public $title;

    /**
     * The identifier of the actual command handler.
     *
     * @var string
     */
    public $command;

    /**
     * Arguments that the command handler should be invoked with.
     *
     * @var mixed[]
     */
    public $arguments = [];
}
