<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Document;

use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\KeyValueStateTrait;

class Project
{
    use KeyValueStateTrait;

    /**
     * @var Uri
     */
    private $rootUri;

    public function __construct(Uri $rootUri)
    {
        $this->rootUri = $rootUri;
    }

    public function getRootUri(): Uri
    {
        return $this->rootUri;
    }
}
