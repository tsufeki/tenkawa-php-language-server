<?php

namespace Tsufeki\Tenkawa\ProcessRunner;

class ProcessResult
{
    /**
     * @var int|null
     */
    public $exitCode = null;

    /**
     * @var int|null
     */
    public $signal = null;

    /**
     * @var string
     */
    public $stdout = '';

    /**
     * @var string
     */
    public $stderr = '';
}
